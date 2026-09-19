#!/usr/bin/env python3
"""
fa_precheck.py — deterministic pre-publication gate for Persian WordPress content.

Merged from:
  * conholdate/blog-translation-agent  -> appears_translated() / should_skip_validation() heuristics
  * sickn33/agentic-awesome-skills     -> i18n/RTL + seo audit evidence rules
  * Qpedia house standard              -> structure, tone, banned clichés

Stdlib only. Exit codes: 0 clean, 1 warnings only, 2 blocking failures.
With a --source file, untranslated paragraphs add a RETRANSLATE verdict.

Usage:
    python3 fa_precheck.py --target draft.html [--target another.html ...]
                          [--source original.md] [--allow terms.txt] [--json]
"""
from __future__ import annotations
import argparse, json, re, sys, pathlib

FA_DIGITS = "۰۱۲۳۴۵۶۷۸۹"
HARD_MARKUP = [
    (r"<style", "<style> block in a post body"),
    (r"<script", "<script> in a post body"),
    (r"<\?php", "PHP tag in a post body"),
    (r"<!\[CDATA\[", "CDATA wrapper pasted into content"),
    (r"<img(?![^>]*\balt=)[^>]*/?>", "<img> without alt attribute"),
]
RAW_AMP = re.compile(r"&(?!(?:amp|lt|gt|quot|#39|nbsp|hellip|mdash|ndash|rarr|larr|times|deg|frac|ldquo|rdquo|laquo|raquo|copy|reg|trade|middot|bull|dagger|sect|euro|pound|deg|hellip);|[a-zA-Z]+;|#\d+;)")
BANNED = ["خفن", "ترکوند", "باورکردنی نیست", "دنیا را زیرورو", "در دنیای امروز", "از دیرباز",
          "شگفت‌انگیز", "شگفت انگیز", "انقلابی", "لازم به ذکر", "گفتنی است", "بدون اغراق",
          "همان‌طور که می‌دانید", "همانطور که میدانید", "در عصر حاضر", "بدون مقدمه",
          "مقاله مرتبط", "مقاله‌ی مرتبط", "اینجا کلیک کنید", "لینک زیر", "تکنولوژی‌های پیشرو",
          "قابل توجه است", "قابل‌توجه است"]
METAPHOR = re.compile(r"(تصور کنید|مثلِ?\s+|مانندِ?\s+|بگذریم|انگار|گویی|تشبیه)")
ANALOGY_NOTE = "این فقط یک تشبیه است"
ZWNJ_HINTS = [r"می شود", r"می شوند", r"می کنم", r"می کنید", r"می دهد", r"می گیرد", r"می گیرد",
              r"می رویم", r"می دانم", r"می توانید", r"خواهد شد", r"ها است\b"]
SECTION_ORDER = ["مقدمه انسانی", "پاسخ کوتاه", "این مفهوم چیست", "بیایید قدم‌به‌قدم",
                 "یک مثال، آزمایش یا تصویر ذهنی", "این موضوع کجا به کار می‌آید",
                 "سوءبرداشت", "چه چیزهایی را هنوز نمی‌دانیم", "مقاله‌های مرتبط",
                 "جمع‌بندی", "منابع", "متداول"]
STOP_EN = {"the", "of", "and", "to", "in", "is", "are", "a", "an", "for", "with", "that",
           "this", "from", "which", "it", "as", "at", "be", "was", "were", "we", "you"}


def tight_text(html: str) -> str:
    """Tags removed without inserting spaces — used for punctuation/spacing checks only."""
    html = re.sub(r"<(script|style)[^>]*>.*?</\1>", " ", html, flags=re.S | re.I)
    html = re.sub(r"<[^>]+>", "", html).replace("&nbsp;", " ")
    return re.sub(r"[ \t]+", " ", html).strip()


def strip_tags(html: str) -> str:
    html = re.sub(r"<(script|style)[^>]*>.*?</\1>", " ", html, flags=re.S | re.I)
    html = re.sub(r"<[^>]+>", " ", html)
    html = html.replace("&nbsp;", " ").replace("&amp;", "&")
    return re.sub(r"[ \t]+", " ", html)


def split_paras(html: str):
    txt = re.sub(r"<br\s*/?>", "\n", html, flags=re.I)
    parts = re.split(r"</p>|</li>|</h[1-6]>|</summary>|</blockquote>|</details>|\n\s*\n", txt)
    return [strip_tags(p).strip() for p in parts if strip_tags(p).strip()]


def should_skip_validation(chunk: str) -> bool:
    """Upstream lang_guard: chunks whose quality cannot be measured by text diff."""
    s = chunk.strip()
    return (s.startswith("```") or s.endswith("```") or s == "---"
            or ("{{<" in s and ">}}" in s) or bool(re.fullmatch(r"https?://\S+", s)))


def appears_translated(original: str, translated: str, min_change_pct: float = 20.0) -> bool:
    """Upstream heuristic: did translation meaningfully change the text?"""
    def clean(t):
        t = re.sub(r"[*_`#]", "", t)
        t = re.sub(r"\[([^\]]+)\]\([^)]+\)", r"\1", t)
        t = re.sub(r"```[\s\S]*?```", "", t)
        t = re.sub(r"`[^`]*`", "", t)
        return re.sub(r"\s+", " ", t).strip().lower()
    o, x = clean(original), clean(translated)
    if o == x:
        return False
    ow, xw = set(o.split()), set(x.split())
    if len(ow) <= 2:
        return len(xw) > 0
    return len(ow - xw) / len(ow) * 100 >= min_change_pct


def load_allow(extra):
    words = set()
    for cand in (pathlib.Path(__file__).with_name("allowlist.fa.txt"),):
        if cand.exists():
            for line in cand.read_text(encoding="utf-8").splitlines():
                line = line.strip()
                if line and not line.startswith("#"):
                    words.add(line.lower())
    for f in (extra or []):
        for line in pathlib.Path(f).read_text(encoding="utf-8").splitlines():
            line = line.strip()
            if line and not line.startswith("#"):
                words.add(line.lower())
    return words


def latin_runs(text: str, allow: set):
    """Classify Latin runs: English sentence (error), citation-ish (info), unknown term (warn)."""
    errors, warns, infos = [], [], []
    for m in re.finditer(r"[A-Za-z][A-Za-z0-9 .'’\-_/&,()]{3,}", text):
        run = m.group(0).strip(" .,-()'")
        toks = [t for t in re.findall(r"[A-Za-z][A-Za-z0-9.'-]*", run)]
        if not toks:
            continue
        low = [t.lower() for t in toks]
        if all(t in allow for t in low):
            continue
        ctx = text[max(0, m.start() - 3): m.end() + 3]
        if re.search(r"\b(?:doi|arxiv|https?|www|10\.\d{4})", run, re.I):
            infos.append(run)
        elif ("«" in ctx and "»" in ctx) or ('"' in ctx or "”" in ctx):
            infos.append(run + "  (quoted source wording — fine when the Persian gloss is present)")
        elif len(toks) >= 4 and (set(low) & STOP_EN):
            errors.append(run)
        elif len(toks) >= 4:
            warns.append(run)
        elif not (set(low) & allow):
            warns.append(run)
    return errors, warns, infos


def check_html(path: pathlib.Path, allow: set, structure_required: bool, rep):
    raw = path.read_text(encoding="utf-8-sig")
    text = strip_tags(raw)
    tight = tight_text(raw)
    words = text.split()

    # ---- markup safety (blocking)
    for pat, why in HARD_MARKUP:
        if re.search(pat, raw, re.I):
            rep.error("markup", f"{why} — {path.name}")
    if RAW_AMP.search(raw):
        n = len(RAW_AMP.findall(raw))
        rep.error("markup", f"raw '&' outside an entity ({n}×) — write «و» or &amp; before pasting into a block")

    # ---- orthography
    for ch, name in (("ي", "Arabic yeh"), ("ك", "Arabic kaf")):
        if ch in tight:
            rep.error("orthography", f"{name} ({ch}) used instead of Persian — {tight.count(ch)}×; replace with {'ی' if ch == 'ي' else 'ک'}")
    for pat in ZWNJ_HINTS:
        hits = re.findall(pat, text)
        if hits:
            rep.warn("orthography", f"missing ZWNJ / wrong form: {sorted(set(hits))[:4]} ({len(hits)}×)")
    if re.search(r"[^«”)\s.] ,| ;|\s,\s|\s\.\s|\s؛|\s\.", tight):
        rep.warn("orthography", "Latin comma/semicolon or space before punctuation inside Persian text")
    if "  " in re.sub(r">\s+<", "", raw):
        rep.warn("orthography", "double spaces (leftover from stripped tags?)")
    lat = [w for w in words if re.search(r"[0-9]", w)]
    if lat:
        rep.info("numerals", f"{len(lat)} tokens contain Latin digits — OK inside DOI/URL/units, otherwise use {''.join(FA_DIGITS[:10])}")

    # ---- structure
    h2 = re.findall(r"<h2[^>]*>(.*?)</h2>", raw, re.S)
    h2 = [strip_tags(x).strip() for x in h2]
    if "<h1" in raw.lower():
        rep.warn("structure", "<h1> in the body: the post title field is the H1")
    if len(h2) < 3:
        rep.warn("structure", f"only {len(h2)} H2 headings")
    if structure_required:
        missing = [s for s in SECTION_ORDER if not any(s in x for x in h2) and s not in text]
        if missing:
            rep.error("structure", "required sections missing: " + ", ".join(missing))
        idx = [next((i for i, x in enumerate(h2) if any(s in x for s in SECTION_ORDER)), None) for s in SECTION_ORDER]
        seq = [i for i in idx if i is not None]
        if seq != sorted(seq):
            rep.warn("structure", "H2 order deviates from the house sequence")
        if "پاسخ کوتاه" not in raw:
            rep.error("structure", "no «پاسخ کوتاه» answer block in the first screen")

    # ---- links / sources / media
    internal = re.findall(r'href="(?:https?://[^/"]+)?(/[^"]*?)/?"', raw)
    internal = [i for i in internal if not re.match(r"^/(wp-content|wp-includes|feed)", i)]
    same = [i for i in internal if re.match(r"^/[^/]+/?$", i)]
    if structure_required and len(same) < 3:
        rep.error("links", f"only {len(same)} internal links; the standard needs ≥3")
    ext = re.findall(r'<a [^>]*href="https?://[^"]*"[^>]*>', raw)
    hosts = [re.sub(r"^https?://", "", t.split('href="')[1]).split("/")[0] for t in ext if 'href="' in t]
    own = max(set(hosts), key=hosts.count) if hosts else ""
    ext = [t for t in ext if own not in t]
    nofollow = [t for t in ext if "nofollow" not in t]
    if nofollow:
        if nofollow:
            rep.warn("links", f"{len(nofollow)} third-party link(s) without rel=\"noopener nofollow\"")
    src = re.findall(r"<li>(.*?)</li>", raw[raw.rfind("منابع"):] if "منابع" in raw else "", re.S)
    src = [s for s in src if re.search(r"https?://|doi|arxiv", s, re.I)]
    if structure_required and len(src) < 3:
        rep.error("sources", f"{len(src)} verifiable sources in «منابع»; the hard-fail list blocks <3")
    if "<img" in raw and 'alt=""' in raw:
        rep.error("media", "featured/in-body image with empty alt")
    if not re.search(r"\.(webp|jpe?g|png)", raw) and "<img" not in raw:
        rep.info("media", "no image markup here — the featured image spec (1200×675 + Persian ALT) must still be delivered separately")

    # ---- tone / clichés / readability
    for b in BANNED:
        if b in text:
            rep.error("tone", f"banned phrase: «{b}»")
    paras = [p for p in split_paras(raw)]
    long_p = [(i, len(p.split())) for i, p in enumerate(paras) if len(p.split()) > 90]
    if long_p:
        rep.warn("readability", f"paragraphs over 90 words: {long_p[:6]}")
    lens = [len(s.split()) for p in paras for s in re.split(r"[.!?؟]\s+", p) if s.strip()]
    if lens and all(abs(a - b) < 4 for a, b in zip(lens, lens[1:])) and len(lens) > 3:
        rep.warn("readability", "suspiciously uniform sentence lengths (machine rhythm)")
    if METAPHOR.search(text) and ANALOGY_NOTE not in text:
        rep.error("tone", "metaphor used without the analogy-limit note («این فقط یک تشبیه است…»)")

    # ---- untranslated Latin (blocking) — prose paragraphs only
    prose = [q for q in paras if not re.search(r"https?://|DOI|arXiv|\bISBN\b", q) and len(q.split()) >= 3]
    le, lw, li = [], [], []
    for q in prose:
        a, b, c_ = latin_runs(q, allow)
        le += a; lw += b; li += c_
    for r in le[:8]:
        rep.error("translation", f"untranslated English phrase in Persian prose: «{r[:90]}»")
    for r in lw[:6]:
        rep.warn("translation", f"Latin run to justify: «{r[:80]}»")
    if li:
        rep.info("translation", f"{len(li)} citation/DOI runs left as-is (correct)")
    rep.stats = dict(words=len(words), h2=len(h2), internal=len(same), ext=len(ext), sources=len(src))
    rep.slugs = sorted({s.strip("/") for s in same})


def check_pairing(target: pathlib.Path, source: pathlib.Path, rep):
    tparas, sparas = split_paras(target.read_text(encoding="utf-8")), split_paras(source.read_text(encoding="utf-8"))
    tparas = [p for p in tparas if not should_skip_validation(p)]
    sparas = [p for p in sparas if not should_skip_validation(p)]
    pairs = list(zip(sparas, tparas))
    if not pairs:
        rep.warn("translation", "no paragraph pairs to compare (structure differs from source)")
        return
    bad = [i for i, (o, t) in enumerate(pairs) if not appears_translated(o, t)]
    pct = len(bad) / len(pairs) * 100
    rep.pairs = dict(pairs=len(pairs), flagged=len(bad), error_pct=round(pct, 1))
    if bad:
        rep.error("translation", f"{len(bad)}/{len(pairs)} paragraphs look untranslated (Error% {pct:.1f}) — indices {bad[:10]}")
    if len(tparas) != len(sparas):
        rep.warn("translation", f"paragraph count differs: source {len(sparas)} vs target {len(tparas)}")


class Reporter:
    def __init__(self):
        self.items, self.stats, self.slugs, self.pairs = [], {}, [], {}

    def add(self, sev, area, msg):
        self.items.append(dict(severity=sev, area=area, message=msg))

    def error(self, a, m): self.add("ERROR", a, m)
    def warn(self, a, m): self.add("WARN", a, m)
    def info(self, a, m): self.add("INFO", a, m)

    def counts(self):
        return {k: sum(1 for i in self.items if i["severity"] == k) for k in ("ERROR", "WARN", "INFO")}

    def verdict(self):
        c = self.counts()
        if c["ERROR"]:
            return "RETRANSLATE" if any("untranslated" in i["message"] for i in self.items) else "FIX"
        return "PASS"


def main():
    ap = argparse.ArgumentParser(description="Persian WordPress pre-publication gate")
    ap.add_argument("--target", action="append", required=True, help="Persian HTML/Markdown file(s)")
    ap.add_argument("--source", help="original file, for untranslated-paragraph pairing")
    ap.add_argument("--allow", action="append", help="file with allowed Latin terms (one per line)")
    ap.add_argument("--no-structure", action="store_true", help="skip the Qpedia section-sequence requirements")
    ap.add_argument("--json", action="store_true")
    a = ap.parse_args()

    allow = load_allow(a.allow) | {"qpedia", "wordpress", "gutenberg"}
    rep = Reporter()
    for t in a.target:
        p = pathlib.Path(t)
        if not p.exists():
            rep.error("io", f"missing file {t}")
            continue
        check_html(p, allow, not a.no_structure, rep)
        if a.source:
            s = pathlib.Path(a.source)
            if s.exists():
                check_pairing(p, s, rep)
            else:
                rep.error("io", f"missing source {a.source}")

    c = rep.counts()
    verdict = rep.verdict()
    code = 2 if c["ERROR"] else (1 if c["WARN"] else 0)
    if a.json:
        print(json.dumps(dict(verdict=verdict, counts=c, stats=rep.stats, slugs=rep.slugs,
                              pairing=rep.pairs, findings=rep.items), ensure_ascii=False, indent=1))
    else:
        icon = {"ERROR": "✗", "WARN": "!", "INFO": "·"}
        for i in rep.items:
            print(f"  {icon[i['severity']]} [{i['area']}] {i['message']}")
        print(f"\n  stats: {rep.stats}")
        if rep.pairs:
            print(f"  pairing: {rep.pairs}")
        if rep.slugs:
            print("  internal slugs to verify: " + " ".join(rep.slugs))
            print("  → GET /wp-json/wp/v2/<post_type>?slug=" + ",".join(rep.slugs[:12]) + "&_fields=id,slug,title&per_page=100")
        print(f"\n  findings: {c['ERROR']} errors, {c['WARN']} warnings, {c['INFO']} info")
        print(f"  VERDICT: {verdict}")
    sys.exit(code)


if __name__ == "__main__":
    main()
