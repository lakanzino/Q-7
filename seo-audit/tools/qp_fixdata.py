#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
qp_fixdata.py — ساخت دادهٔ آمادهٔ اعمال برای افزونهٔ یک‌بارمصرف وردپرس + قوانین ریدایرکت.
همهٔ مقادیر از seo-audit/data/ (خروجی واقعی qp_audit) خوانده می‌شود.
Usage: python3 seo-audit/tools/qp_fixdata.py <repo_root>
"""

import csv
import difflib
import json
import os
import re
import sys
from collections import OrderedDict

SITE = "https://qpedia.ir"


def norm_fa(s):
    s = (s or "").replace("ي", "ی").replace("ك", "ک").replace("‌", " ")
    return re.sub(r"\s+", " ", s).strip()


def php_str(s):
    return "'" + str(s or "").replace("\\", "\\\\").replace("'", "\\'") + "'"


def php_array(pairs, indent="    "):
    lines = []
    for kv in pairs:
        if isinstance(kv[1], (list, tuple)):
            inner = ", ".join(php_str(x) for x in kv[1])
            lines.append(f"{indent}{php_str(kv[0])} => array({inner}),")
        elif isinstance(kv[1], dict):
            inner = ", ".join(f"{php_str(k)} => {php_str(v)}" for k, v in kv[1].items())
            lines.append(f"{indent}{php_str(kv[0])} => array({inner}),")
        else:
            lines.append(f"{indent}{php_str(kv[0])} => {php_str(kv[1])},")
    return "\n".join(lines)


def trim_meta(text, maxlen=158):
    text = norm_fa(text)
    if len(text) <= maxlen:
        return text
    cut = text[:maxlen]
    sp = max(cut.rfind(" "), cut.rfind("،"), cut.rfind("؛"), cut.rfind("."))
    if sp > 100:
        cut = cut[:sp]
    return cut.rstrip(" .،؛") + "…"


def kw_from_title(title):
    t = norm_fa(title)
    t = re.sub(r"[\u061F؟?!\u2026…:؛.,«»\"']", " ", t)
    t = re.sub(r"\b(چیست|چیه|چیست؟|چگونه|یعنی چه|یعنی چی|یعنی|به زبان ساده|به|از|در|و|یا|که|را|این|آن|برای|با|چرا|آیا|کجا|کی|چه|چقدر|میداند|می شود|است|هست|هستند|بود|بودند|ممکن|شد|شود|کنند|کند)\b", " ", t)
    t = re.sub(r"\s+", " ", t).strip()
    words = t.split()
    return " ".join(words[:5]) if words else norm_fa(title)[:40]


def ratio(a, b):
    return difflib.SequenceMatcher(None, a, b).ratio()


def suggest_target(slug, choices, n=1, cutoff=0.72):
    m = difflib.get_close_matches(slug, choices, n=n, cutoff=cutoff)
    return m[0] if m else ""


STOP_TOKENS = {"quantum", "physics", "theory", "the", "of", "and", "is", "what", "a", "an", "in", "to", "2", "3"}


def core_tokens(slug):
    return [t for t in re.split(r"[-_]+", slug.lower()) if t and t not in STOP_TOKENS]


def confident_pair(old_slug, new_slug):
    """
    آیا ریدایرکت ۳۰۱ منطقی است؟ (توکن‌های اصلی، نه پیشوندهای تکراری مثل quantum-)
    strong = پوشش توکنی کامل یا شباهت بالا · medium = شباهت متوسط · weak = نامرتبط
    """
    o, n = core_tokens(old_slug), core_tokens(new_slug)
    if not o or not n:
        return "weak"
    so, sn = set(o), set(n)
    contains = so <= sn or sn <= so
    if contains:
        # مثال: dirac-antimatter ⊃ antimatter ؛ quantum-entanglement ⊂ quantum-entanglement-explained
        return "strong" if ratio(old_slug, new_slug) >= 0.72 else "medium"
    rem_o, rem_n = "-".join(o), "-".join(n)
    r = ratio(rem_o, rem_n)
    if r >= 0.8:
        return "strong"
    if r >= 0.7:
        return "medium"
    return "weak"


def main(root):
    d = os.path.join(root, "seo-audit", "data")
    arts = json.load(open(os.path.join(d, "articles.json"), encoding="utf-8"))
    summ = json.load(open(os.path.join(d, "summary.json"), encoding="utf-8"))
    outdir = os.path.join(root, "seo-audit")
    registry = summ["registry_live"]
    pubs = [a for a in arts if a["status"] == "publish"]

    live_pub_articles = sorted(s for s, v in registry.items() if v["status"] == "publish" and v["type"] == "quantum_article")
    live_pub_all = sorted(s for s, v in registry.items() if v["status"] == "publish")
    art_type = {s: v["type"] for s, v in registry.items() if v["status"] == "publish"}

    # ---- ۱) اصلاح لینک‌های شکسته (فقط آن‌هایی که مقصد پیشنهادی دارند)
    link_fixes = OrderedDict()          # slug مقاله => {find: replace}
    redirect_rules = OrderedDict()      # مسیر قدیمی => مسیر جدید (۳۰۱)
    gone_paths = []                     # 410
    for a in arts:
        for href, anchor in (a["links"]["broken"] or []):
            m = re.search(r"qpedia\.ir(/[^\s\"']*)", href) or re.match(r"(/[^\s\"']*)", href)
            if not m:
                continue
            path = m.group(1).split("?")[0].split("#")[0] or "/"
            seg = [s for s in path.split("/") if s]
            if not seg:
                continue
            cand = seg[-1]
            base = "scientists" if seg[0].lower().startswith("scientist") else ""
            pool = [s for s in live_pub_all if (art_type[s] == "quantum_scientist")] if base else live_pub_articles
            sug = suggest_target(cand, pool, cutoff=0.72) or suggest_target(cand, live_pub_all, cutoff=0.8)
            if sug and confident_pair(cand, sug) in ("strong", "medium"):
                target = f"{SITE}/{'scientists/' if art_type.get(sug) == 'quantum_scientist' else ''}{sug}/"
                link_fixes.setdefault(a["slug"], {})[href] = target
                if path not in ("/", ""):
                    redirect_rules[path] = target
            elif sug and confident_pair(cand, sug) == "weak":
                # مقصدِ نامطمئن: فقط گزارش می‌شود؛ لینک در محتوا دست‌نخورده می‌ماند.
                if path not in ("/", ""):
                    gone_paths.append(path)
            else:
                if path not in ("/", ""):
                    gone_paths.append(path)

    # ---- قوانین تمیزکاری اسلاگ دانشمندان (همیشگی، بر پایهٔ رجیستری واقعی)
    sci_by_title = {}
    for s, v in registry.items():
        if v["type"] == "quantum_scientist" and v["status"] == "publish":
            sci_by_title[norm_fa(v["title"]).split("؛")[0].strip()] = s
    einstein_live = None
    for s, v in registry.items():
        if v["type"] == "quantum_scientist" and v["status"] == "publish" and "einstein" in s:
            einstein_live = s
    planck_live = None
    for s, v in registry.items():
        if v["type"] == "quantum_scientist" and v["status"] == "publish" and "plank" in s.lower():
            planck_live = s
    if einstein_live:
        for p in ("/scientists/albert-einstein/", "/scientist/albert-einstein/", "/albert-einstein/"):
            if not p.rstrip("/").endswith(einstein_live):
                redirect_rules[p] = f"{SITE}/scientists/{einstein_live}/"
    if planck_live:
        for p in ("/scientists/max-planck/", "/scientist/max-planck/", "/max-planck/"):
            if not p.rstrip("/").endswith(planck_live):
                redirect_rules[p] = f"{SITE}/scientists/{planck_live}/"

    # ---- نشانی‌های حذف‌شدهٔ ۱۴ سپتامبر — فقط جفت‌های مطمئن ۳۰۱ می‌شوند؛ بقیه ۴۱۰
    removed_redirects = OrderedDict()
    removed_gone = []
    removed_review = []
    for r in summ["counts"]["removed_since_0914"]:
        slug = r["slug"]
        sug = suggest_target(slug, live_pub_articles, cutoff=0.6)
        if sug and confident_pair(slug, sug) in ("strong", "medium"):
            removed_redirects[f"/{slug}/"] = f"{SITE}/{sug}/"
            if confident_pair(slug, sug) == "medium":
                removed_review.append((slug, sug, round(ratio(slug, sug), 2)))
        else:
            removed_gone.append(f"/{slug}/")

    # ---- ۲) بازکردن لینک به‌پیش‌نویس/زباله‌دان در مقالات منتشرشده
    draft_unlinks = OrderedDict()  # slug مقاله => [href,...]
    for a in pubs:
        hrefs = sorted({h for h, st in a["links"]["draft_targets"]})
        if hrefs:
            draft_unlinks[a["slug"]] = hrefs

    # ---- ۳) متای پیشنهادی برای منتشرشده‌های فاقد متا
    meta_map = OrderedDict()
    for a in pubs:
        if not a["meta_len"]:
            cand = a["text_preview"]
            meta_map[a["slug"]] = trim_meta(cand)

    # ---- ۴) کلیدواژهٔ کانونی پیشنهادی
    kw_map = OrderedDict()
    for a in pubs:
        if not a["has_focus_kw"]:
            kw_map[a["slug"]] = kw_from_title(a["title"])

    # ---- ۵) ALT تصویر شاخص
    feat_alt = OrderedDict()  # attachment_id => alt
    for a in pubs:
        f = a["featured"]
        if f.get("set") and f.get("found") and not (f.get("alt") or "").strip():
            if f.get("id"):
                feat_alt[f["id"]] = f"تصویر شاخص مقالهٔ «{a['title']}» در وبسایت کوانتوم پدیا"

    stats = {
        "link_fix_posts": len(link_fixes),
        "link_fix_hrefs": sum(len(v) for v in link_fixes.values()),
        "draft_unlink_posts": len(draft_unlinks),
        "draft_unlink_hrefs": sum(len(v) for v in draft_unlinks.values()),
        "meta_fill": len(meta_map),
        "kw_fill": len(kw_map),
        "feat_alt": len(feat_alt),
        "redirect_301": len(redirect_rules) + len(removed_redirects),
        "redirect_410": len(set(gone_paths) - set(redirect_rules) - set(removed_redirects)) + len(removed_gone),
        "redirect_review": len(removed_review),
    }

    # ---------------------------------------------------------------- خروجی PHP
    php = f"""<?php
/**
 * QPOF — دادهٔ آمادهٔ اعمال (خودکار از روی ممیزی واقعی سایت ساخته شده؛ دست‌نویس نیست).
 * Generated by qp_fixdata.py — {summ['files']['main']}
 */
if ( ! defined( 'ABSPATH' ) ) {{ exit; }}

function qpof_data() {{
    static $cache = null;
    if ( $cache !== null ) {{ return $cache; }}
    $cache = array(
        'stats' => array({php_array(stats.items(), "            ")}),
        // اصلاح لینک شکسته: slug مقاله => array( href قدیمی => href جدید )
        'link_fixes' => array(
{php_array(link_fixes.items(), "            ")}
        ),
        // بازکردن لینک به‌پیش‌نویس: slug مقاله => array( href )
        'draft_unlinks' => array(
{php_array(draft_unlinks.items(), "            ")}
        ),
        // متا: slug مقاله => توضیح پیشنهادی (بازبینی انسانی توصیه می‌شود)
        'meta_fill' => array(
{php_array(meta_map.items(), "            ")}
        ),
        // کلیدواژهٔ کانونی: slug => عبارت پیشنهادی
        'kw_fill' => array(
{php_array(kw_map.items(), "            ")}
        ),
        // ALT تصویر شاخص: attachment_id => متن پیشنهادی
        'feat_alt' => array(
{php_array(feat_alt.items(), "            ")}
        ),
        // ریدایرکت‌ها: مسیر منبع => مقصد کامل (۳۰۱)
        'redirects_301' => array(
{php_array(OrderedDict(list(redirect_rules.items()) + list(removed_redirects.items())).items(), "            ")}
        ),
        // پاسخ ۴۱۰ (حذف‌شده بدون جایگزین)
        'redirects_410' => array(
{php_array([(i, p) for i, p in enumerate(sorted(set(gone_paths) - set(redirect_rules) - set(removed_redirects)) + sorted(set(removed_gone)))], "            ")}
        ),
        // جفت‌های ریدایرکت با شباهت متوسط — بازبینی انسانی
        'redirects_review' => array(
{php_array([(sug + " <== " + src, str(score)) for src, sug, score in removed_review], "            ")}
        ),
    );
    return $cache;
}}
"""
    plug_dir = os.path.join(outdir, "wp-plugin", "qp-one-off-fixes")
    os.makedirs(plug_dir, exist_ok=True)
    with open(os.path.join(plug_dir, "qp-fix-data.php"), "w", encoding="utf-8") as f:
        f.write(php)

    # ---------------------------------------------------------------- ریدایرکت‌ها (CSV + htaccess)
    red_dir = os.path.join(outdir, "redirects")
    os.makedirs(red_dir, exist_ok=True)
    all301 = OrderedDict(list(redirect_rules.items()) + list(removed_redirects.items()))
    all410 = sorted(set(gone_paths) - set(all301)) + removed_gone

    with open(os.path.join(red_dir, "301-redirects.csv"), "w", newline="", encoding="utf-8-sig") as f:
        wcsv = csv.writer(f)
        wcsv.writerow(["source_url", "target_url", "http_code", "note"])
        review_set = {f"/{s}/" for s, _, _ in removed_review}
        for s, t in all301.items():
            note = "مشابهت متوسط — بازبینی" if s in review_set else "مطمئن"
            wcsv.writerow([s, t, 301, note])
        for p in all410:
            wcsv.writerow([p, "", 410, "حذف‌شده بی‌جایگزین — گوگل آن را زودتر کنار می‌گذارد"])

    ht = [
        "# === قوانین ریدایرکت qpedia.ir — تولید خودکار از ممیزی واقعی ===",
        "# این بلوک را «بالای» قوانین وردپرس در .htaccess ریشه بگذارید.",
        "# (Apache + mod_alias؛ اگر افزونهٔ Redirection دارید، CSV کنار همین پوشه را ایمپورت کنید)",
        "",
        "<IfModule mod_alias.c>",
    ]
    for s, t in all301.items():
        ht.append(f"Redirect 301 {s} {t}")
    for p in all410:
        ht.append(f"Redirect 410 {p}")
    ht.append("</IfModule>")
    with open(os.path.join(red_dir, "htaccess-301-rules.txt"), "w", encoding="utf-8") as f:
        f.write("\n".join(ht) + "\n")

    print(json.dumps(stats, ensure_ascii=False, indent=1))
    print("written:", plug_dir, red_dir)


if __name__ == "__main__":
    main(sys.argv[1] if len(sys.argv) > 1 else ".")
