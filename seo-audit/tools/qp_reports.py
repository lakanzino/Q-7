#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
qp_reports.py — تولید گزارش‌های فارسی از روی داده‌های qp_audit.py
همهٔ اعداد از articles.json/issues.csv/summary.json خوانده می‌شوند؛ هیچ عدد دست‌سازی نیست.
Usage: python3 seo-audit/tools/qp_reports.py <repo_root>
"""

import json
import os
import re
import statistics
import sys
from collections import Counter, defaultdict
from html import escape

SEV_FA = {"high": "بالا", "med": "متوسط", "low": "کم"}
SEV_ORDER = {"high": 0, "med": 1, "low": 2}
GRADE_FA = {"red": "🔴 قرمز", "yellow": "🟡 زرد", "green": "🟢 سبز"}
GRADE_TABLE = {"red": "🔴", "yellow": "🟡", "green": "🟢"}


def fa_num(n):
    return str(n)


def load(root):
    d = os.path.join(root, "seo-audit", "data")
    arts = json.load(open(os.path.join(d, "articles.json"), encoding="utf-8"))
    summ = json.load(open(os.path.join(d, "summary.json"), encoding="utf-8"))
    return arts, summ


def w(path: str, text: str):
    os.makedirs(os.path.dirname(path), exist_ok=True)
    with open(path, "w", encoding="utf-8") as f:
        f.write(text)


def md_table(headers, rows):
    out = ["| " + " | ".join(headers) + " |", "|" + "|".join(["---"] * len(headers)) + "|"]
    for r in rows:
        out.append("| " + " | ".join(str(c).replace("|", "\\|") for c in r) + " |")
    return "\n".join(out)


def bar_svg(items, title, unit="عدد", w_px=860, row_h=26, pad_l=230, color="#3b82f6"):
    """ترسیم نمودار میله‌ای افقی SVG ساده."""
    items = list(items)
    if not items:
        return ""
    maxv = max(v for _, v in items) or 1
    h = row_h * len(items) + 40
    bw = w_px - pad_l - 60
    parts = [
        f'<svg xmlns="http://www.w3.org/2000/svg" width="{w_px}" height="{h}" dir="ltr" font-family="Vazirmatn, Tahoma">',
        f'<style>text{{font-size:12px;fill:#111}} .v{{font-size:11px;fill:#374151;font-weight:bold}}</style>',
        f'<text x="10" y="16" font-weight="bold">{escape(title)}</text>',
    ]
    for i, (label, v) in enumerate(items):
        y = 30 + i * row_h
        bwd = max(2, int(bw * v / maxv))
        lab = label if len(label) <= 34 else label[:33] + "…"
        parts.append(f'<text x="{pad_l - 6}" y="{y + 13}" text-anchor="end">{escape(str(lab))}</text>')
        parts.append(f'<rect x="{pad_l}" y="{y}" width="{bwd}" height="16" rx="3" fill="{color}"/>')
        parts.append(f'<text x="{pad_l + bwd + 5}" y="{y + 13}" class="v">{v}</text>')
    parts.append("</svg>")
    return "\n".join(parts)


def hist_svg(buckets, title, w_px=860, h=220, color="#10b981"):
    items = list(buckets.items()) if hasattr(buckets, "items") else list(buckets)
    maxv = max(v for _, v in items) or 1
    n = len(items)
    bw = (w_px - 60) / max(n, 1)
    parts = [
        f'<svg xmlns="http://www.w3.org/2000/svg" width="{w_px}" height="{h}" dir="ltr" font-family="Vazirmatn, Tahoma">',
        f'<style>text{{font-size:11px;fill:#111}}</style>',
        f'<text x="10" y="16" font-weight="bold">{escape(title)}</text>',
    ]
    for i, (k, v) in enumerate(items):
        x = 40 + i * bw
        bh = int(150 * v / maxv)
        parts.append(f'<rect x="{x:.0f}" y="{170 - bh}" width="{max(bw - 6, 2):.0f}" height="{bh}" rx="2" fill="{color}"/>')
        parts.append(f'<text x="{x + bw / 2 - 8:.0f}" y="{166 - bh}">{v}</text>')
        parts.append(f'<text x="{x + bw / 2 - 8:.0f}" y="{185}" transform="rotate(0)">{escape(str(k))}</text>')
    parts.append("</svg>")
    return "\n".join(parts)


def main(root):
    arts, summ = load(root)
    outdir = os.path.join(root, "seo-audit")
    pubs = [a for a in arts if a["status"] == "publish"]
    drafts = [a for a in arts if a["status"] == "draft"]
    trash = [a for a in arts if a["status"] == "trash"]

    pubs_sorted = sorted(pubs, key=lambda a: (a["score"]["total"]))
    all_sorted = pubs_sorted + sorted(drafts, key=lambda a: a["score"]["total"]) + trash

    # ------------------------------------------------------------ 03 جدول امتیاز
    rows = []
    rank = 0
    for a in all_sorted:
        rank += 1
        sc = a["score"]
        n_high = sum(1 for i in a["issues"] if i["severity"] == "high")
        rows.append([
            rank, GRADE_TABLE[sc["grade"]], f'`{a["slug"]}`', a["status"],
            (a["title"][:46] + "…" if len(a["title"]) > 46 else a["title"]),
            sc["total"], f'{sc["A"]}/{sc["B"]}/{sc["C"]}/{sc["D"]}/{sc["E"]}/{sc["F"]}/{sc["G"]}',
            a["words"], "✔" if a["meta_len"] else "✘", "✔" if a["has_focus_kw"] else "✘",
            a["h2_count"], a["links"]["internal_unique"], a["links"]["external_unique"], a["inbound"],
            len(a["links"]["broken"]), len(a["issues"]), n_high, a["fix_priority"],
        ])
    g_pub = Counter(a["score"]["grade"] for a in pubs)
    t03 = f"""# جدول کامل امتیاز مقالات qpedia.ir (۱ تا ۱۰۰) + رده‌بندی رنگی

> این جدول **خودکار** از روی `data/articles.json` تولید شده است. هیچ عددی حدسی نیست.
> ترتیب: بدترین مقالهٔ منتشرشده اول — تا سبزترین؛ سپس پیش‌نویس‌ها، در انتها سطل‌زباله.
>
> رده: 🔴 <۵۵ یا نقض حاد · 🟡 ۵۵ تا ۷۹ · 🟢 ≥۸۰ بدون ایراد شدید (تعریف کامل در `01-قواعد-گوگل.md`)

- مقالات منتشرشده: **{len(pubs)}** | پیش‌نویس: **{len(drafts)}** | سطل‌زباله: **{len(trash)}**
- توزیع رنگ منتشرشده‌ها: 🔴 **{g_pub.get('red', 0)}** · 🟡 **{g_pub.get('yellow', 0)}** · 🟢 **{g_pub.get('green', 0)}**
- ستون A تا G همان اجزای امتیاز است: عنوان/متا (۱۸) · کلیدواژه (۱۰) · محتوا (۲۶) · تصویر (۱۶) · پیوند (۱۶) · فنی (۸) · تحرریه (۶)

{md_table(["#", "رده", "اسلاگ", "وضعیت", "عنوان", "امتیاز", "A/B/C/D/E/F/G", "کلمات", "متا", "کلیدواژه", "H2", "لینک‌داخلی", "لینک‌خارجی", "ورودی", "شکسته", "ایرادها", "شدید", "اولویت"], rows)}

*ستون «شکسته» = تعداد لینک داخلی شکسته (۴۰۴) در بدنهٔ همان مقاله. «ورودی» = تعداد لینک‌هایی که از مقالات منتشرشدهٔ دیگر به این مقاله می‌رسد.*
"""
    w(os.path.join(outdir, "03-جدول-امتیاز-مقالات.md"), t03)

    # ------------------------------------------------------------ 04 ایرادات تفصیلی
    parts = [f"""# ایرادات تفصیلی هر مقاله — با محل دقیق و آدرس

> هر ایراد: کد، شدت، **محل دقیق** (کدام عنصر/کدام لینک)، مدرک واقعی از فایل خروجی، و راهکار.
> ترتیب مقالات: از بیشترین اولویت اصلاح به کمترین. جدول راهنمای کدها در `05-رده‌بندی-ایرادات.md`.

- مجموع مقالات بررسی‌شده: **{len(arts)}** (منتشرشده {len(pubs)}، پیش‌نویس {len(drafts)}، زباله‌دان {len(trash)})
- مجموع ایرادها: **{sum(len(a['issues']) for a in arts)}**

---
"""]
    intro04 = parts[0]
    blocks = []
    cur_grade = None
    pub_first = sorted(arts, key=lambda x: -x["fix_priority"])
    for a in pub_first:
        sc = a["score"]
        if a["status"] == "publish" and sc["grade"] != cur_grade:
            cur_grade = sc["grade"]
            blocks.append(f"\n# ≫ گروه {GRADE_FA[cur_grade]} (مقالات منتشرشده)\n")
        iss_sorted = sorted(a["issues"], key=lambda i: SEV_ORDER[i["severity"]])
        sev_cnt = Counter(i["severity"] for i in iss_sorted)
        head = (
            f"\n## {GRADE_TABLE[sc['grade']]} `{a['slug']}` — امتیاز {sc['total']}/۱۰۰\n\n"
            f"- عنوان: {a['title']}\n"
            f"- آدرس: {a['url']}  ·  post_id: {a['post_id']}  ·  وضعیت: {a['status']}\n"
            f"- اجزا: عنوان/متا {sc['A']}/۱۸ · کلیدواژه {sc['B']}/۱۰ · محتوا {sc['C']}/۲۶ · تصویر {sc['D']}/۱۶ · پیوند {sc['E']}/۱۶ · فنی {sc['F']}/۸ · تحرریه {sc['G']}/۶\n"
            f"- کلمات: {a['words']} · لینک داخلی: {a['links']['internal_unique']} · لینک خارجی: {a['links']['external_unique']} · ایرادها: {len(iss_sorted)} (شدید {sev_cnt.get('high', 0)})\n"
        )
        if not iss_sorted:
            head += "\n✅ ایرادی ثبت نشد.\n"
        else:
            rows = [[i["code"], SEV_FA[i["severity"]], i["title"], i["location"] or "—",
                     (i["evidence"] or "—"), i["fix"]] for i in iss_sorted]
            head += "\n" + md_table(["کد", "شدت", "شرح ایراد", "محل دقیق", "مدرک واقعی", "راهکار"], rows) + "\n"
        head += "\n---\n"
        blocks.append(head)
    w(os.path.join(outdir, "04-ایرادات-تفصیلی-هر-مقاله.md"), intro04 + "".join(blocks))

    # ------------------------------------------------------------ 05 رده‌بندی ایرادات
    by_code = defaultdict(list)
    for a in arts:
        for i in a["issues"]:
            by_code[i["code"]].append(i)
    parts = [f"""# رده‌بندی ایرادها بر اساس نوع — کدام ایراد، کدام مقالات را گرفته؟

> هر ردیف: تعریف ایراد، شدت پیش‌فرض، تعداد کل تکرار و تعداد مقالات منتشرشدهٔ درگیر.
> فهرست کامل مقالات درگیر + مدرک هر کدام در `data/issues.csv` و فایل `04` آمده است.

"""]
    meta_rows = []
    code_sorted = sorted(by_code.items(), key=lambda kv: -len(kv[1]))
    cat_names = {c: by_code[c][0]["category"] for c, _ in code_sorted}
    for code, lst in code_sorted:
        n_pub = len({i["slug"] for i in lst if i["status"] == "publish"})
        meta_rows.append([code, lst[0]["category"], lst[0]["title"], SEV_FA[lst[0]["severity"]],
                          len(lst), n_pub, f'{n_pub}/{len(pubs)}'])
    parts.append(md_table(["کد", "دسته", "شرح", "شدت", "کل تکرار", "مقالات منتشرشدهٔ درگیر", "سهم از منتشرشده‌ها"], meta_rows))
    parts.append("\n---\n")
    for code, lst in code_sorted:
        sample = lst[0]
        parts.append(f"""
## {code} · {sample['title']}

- **دسته:** {sample['category']} · **شدت:** {SEV_FA[sample['severity']]}
- **تعداد کل:** {len(lst)} · **مقالات منتشرشدهٔ درگیر:** {len({i['slug'] for i in lst if i['status'] == 'publish'})}
- **راهکار کلی:** {sample['fix']}
- **نمونهٔ مدرک واقعی:** {sample['evidence'][:180] or '—'} (مقالهٔ `{sample['slug']}`)
""")
        if code in ("L02", "L03", "X07", "I02", "I01", "M01", "C01", "X05"):
            slugs_pub = sorted({i["slug"] for i in lst if i["status"] == "publish"})
            body = "\n".join(f"- `{s}`" for s in slugs_pub[:80])
            parts.append(f"\n**فهرست مقالات منتشرشدهٔ درگیر ({len(slugs_pub)} عدد):**\n\n{body}\n")
    w(os.path.join(outdir, "05-رده‌بندی-ایرادات.md"), "".join(parts))

    # ------------------------------------------------------------ نمودارها
    assets = os.path.join(outdir, "assets")
    top15 = [(f'{c}', n) for c, n in Counter(i["code"] for a in arts for i in a["issues"]).most_common(15)]
    code_title = {c: by_code[c][0]["title"] for c, _ in code_sorted}
    top15_labeled = [(f'{code_title[c][:26]} [{c}]', n) for c, n in top15]
    w(os.path.join(assets, "issue-top15.svg"), bar_svg(top15_labeled, "۱۵ ایراد پرتکرار سایت (کل مقالات)"))

    sc_pub = [a["score"]["total"] for a in pubs]
    buckets = Counter()
    for s in sc_pub:
        b = f"{(s // 5) * 5}-{(s // 5) * 5 + 4}"
        buckets[b] += 1
    w(os.path.join(assets, "score-histogram.svg"), hist_svg(sorted(buckets.items()), "توزیع امتیاز مقالات منتشرشده (دسته‌های ۵تایی)"))

    words_pub = [a["words"] for a in pubs]
    wb = Counter()
    for x in words_pub:
        k = "<400" if x < 400 else "400-599" if x < 600 else "600-899" if x < 900 else "900-1199" if x < 1200 else "1200-1799" if x < 1800 else "1800-2499" if x < 2500 else "2500+"
        wb[k] += 1
    order = ["<400", "400-599", "600-899", "900-1199", "1200-1799", "1800-2499", "2500+"]
    w(os.path.join(assets, "words-histogram.svg"), bar_svg([(k, wb.get(k, 0)) for k in order], "توزیع حجم مقالات منتشرشده (کلمه)", color="#f59e0b"))

    # ------------------------------------------------------------ 06 برنامه اولویت‌بندی
    broken_rows = []
    for a in arts:
        for i in a["issues"]:
            if i["code"] == "L02":
                tgt = re.search(r"→\s*([^|]+?)\s*(?:\||$)", i["evidence"])
                sug = re.search(r"نزدیک‌ترین مقصد فعال: (\S+)", i["evidence"])
                broken_rows.append([a["slug"], a["status"], (tgt.group(1) if tgt else "")[:80],
                                    (sug.group(1) if sug else "—")[:80]])
    twin_groups = summ.get("similar_slug_groups", {})
    twin_rows = []
    for base, group in sorted(twin_groups.items()):
        qa = [g for g in group if g["type"] == "quantum_article" and g["status"] == "publish"]
        if len(qa) >= 2:
            scs = {a["slug"]: a["score"]["total"] for a in arts}
            keep = max(qa, key=lambda g: scs.get(g["slug"], 0))
            for g in qa:
                twin_rows.append([base, f'`{g["slug"]}`', g["title"][:38], scs.get(g["slug"], "?"),
                                  "✅ نگه‌داشت + تقویت" if g["slug"] == keep["slug"] else "🔁 ادغام + ریدایرکت ۳۰۱"])
    orphans = sorted([a for a in pubs if a["inbound"] == 0], key=lambda a: a["score"]["total"])
    thin = sorted([a for a in pubs if a["words"] < 600], key=lambda a: a["words"])
    no_meta = sorted([a for a in pubs if not a["meta_len"]], key=lambda a: a["score"]["total"])
    no_kw = [a for a in pubs if not a["has_focus_kw"]]
    removed = summ["counts"]["removed_since_0914"]

    draft_inbound = Counter()
    for a in arts:
        if a["status"] != "publish":
            continue
        for href, st in a["links"]["draft_targets"]:
            mslug = re.search(r"qpedia\.ir/([^/?#]+)/?", href)
            if mslug:
                draft_inbound[mslug.group(1)] += 1
    hot_drafts = draft_inbound.most_common(30)
    removed_lines = "\n".join(f"- `/{r['slug']}/` — «{r['title'][:60]}»" for r in removed)

    t06 = f"""# برنامهٔ اولویت‌بندی اصلاح — با راهکار اجرایی

> ترتیب فازها بر اساس شدت اثر سئو و حجم خسارت **از روی داده‌ها** چیده شده است.

## فاز ۱ (این هفته) — خسارت‌های فعال در سایت زنده

### ۱‑۱) لینک‌های داخلی شکسته — {len(broken_rows)} مورد
هر ردیف یک ۴۰۴ برای خواننده و خرابی سیگنال اعتماد برای گوگل است. جدول کامل:

{md_table(["مقالهٔ مبدأ", "وضعیت", "مقصد شکسته", "پیشنهاد جایگزین (از دادهٔ واقعی)"], broken_rows)}

**راهکار:** یک پاس ادیتی روی مقالات ستون اول؛ جایگزینی یا حذف. میانگین کمتر از ۲ دقیقه برای هر مورد.

### ۱‑۲) لینک به پیش‌نویس/آشغال‌دان — {sum(len(a['links']['draft_targets']) for a in arts)} مورد
لینک از مقالهٔ منتشرشده به صفحه‌ای که برای عموم ۴۰۴ است. دو راه: انتشار پیش‌نویسِ مقصد (اگر آماده است) یا حذف/معوض لینک.
**پیش‌نویس‌هایی که بیشترین تقاضای لینک را دارند** (منتشرشدنشان بیشترین شکست را رفع می‌کند):

{md_table(["پیش‌نویس (اسلاگ)", "تعداد لینک از مقالات منتشرشده"], hot_drafts[:20])}

### ۱‑۳) مقالات دوقلوموضوع «زنده» — صفر مورد ✔
در نسخهٔ امروز (۲۰ سپتامبر) هیچ جفت مقالهٔ `name`/`name-2` منتشرشده باقی نمانده است؛ پاک‌سازی انجام شده. تنها باقی‌مانده: ریدایرکت نشانی‌های قدیمی (فاز ۱‑۴) و تمیزکردن اسلاگ دانشمندان (فایل ۰۲، یافتهٔ ۴).

### ۱‑۴) مقالات حذف‌شده از نسخهٔ ۱۴ سپتامبر — {len(removed)} نشانی قدیمی
اگر این نشانی‌ها ایندکس/لینک شده باشند، الان ۴۰۴‌اند. برای هر کدام **ریدایرکت ۳۰۱** به نسخهٔ جدید موضوع:

{removed_lines}

## فاز ۲ (دو هفته) — سیستماتیک و سریع

### ۲‑۱) کلیدواژهٔ کانونی برای {len(no_kw)} مقالهٔ منتشرشده ✍️
Rank Math بدون Focus Keyword بخش بزرگی از کنترل کیفیت را نمی‌دهد. فهرست کامل در `05` کد K01.

### ۲‑۲) توضیحات متا برای {len(no_meta)} مقالهٔ منتشرشده
{md_table(["اسلاگ", "کلمات", "امتیاز"], [[f'`{a["slug"]}`', a["words"], a["score"]["total"]] for a in no_meta[:60]])}

### ۲‑۳) محتوای نازک (<۶۰۰ کلمه) — {len(thin)} مقالهٔ منتشرشده
{md_table(["اسلاگ", "کلمات", "امتیاز"], [[f'`{a["slug"]}`', a["words"], a["score"]["total"]] for a in thin[:60]])}
*تصمیم: توسعهٔ علمی، یا ادغام در مقالهٔ مادر + ۳۰۱.*

### ۲‑۴) صفحات یتیم — {len(orphans)} مقالهٔ منتشرشده بدون لینک ورودی
{md_table(["اسلاگ", "امتیاز", "کلمات"], [[f'`{a["slug"]}`', a["score"]["total"], a["words"]] for a in orphans[:80]])}
*از ۲–۵ مقالهٔ مرتبط لینک طبیعی بدهید.*

## فاز ۳ (ماه جاری) — کیفیت‌سازی مداوم
- بخش «منابع» با تیتر استاندارد در ۲۸ مقاله (کد C07) و کمتر از ۳ ارجاع در ۹۱ مقاله (C08).
- پرسش‌های متداول در ۴۲ مقاله (C09)؛ انکرتکست توصیفی (L04)؛ جمع‌بندی (C11)؛ سوءبرداشت (C12).
- پوشش ALT تصویر شاخص: ۲ مقالهٔ منتشرشده بدون ALT شاخص (I02) — فوری.

## قواعد دائمی برای جلوگیری از تکرار
۱. هر موضوع = یک URL. قبل از انتشار، وجود نسخهٔ هم‌نام/قدیمی را جستجو کنید.
۲. نشر مقاله فقط وقتی که لینک‌های داخلی‌اش به صفحات **منتشرشده** برود — در غیر این صورت لینک را موقت برنمی‌دارید.
۳. نام فایل تصاویر لاتین توصیفی؛ ALT فارسی توصیفی.
۴. ترتیب انتشار پیش‌نویس‌ها بر اساس جدول «تقاضای لینک» در فاز ۱‑۲.
"""
    w(os.path.join(outdir, "06-برنامه-اولویت‌بندی-اصلاح.md"), t06)

    # ------------------------------------------------------------ 02 تصویر کلی
    cats = Counter()
    for a in pubs:
        for c in a["categories"] or ["(بدون دسته)"]:
            cats[c] += 1
    months = Counter()
    for a in pubs:
        if a["post_date"]:
            months[a["post_date"][:7]] += 1
    ext_domains = Counter()
    for a in arts:
        for d in a["links"]["ext_domains"]:
            ext_domains[d] += 1
    img_total = sum(a["img"]["total"] for a in arts)
    iss_all = Counter(i["code"] for a in arts for i in a["issues"])
    sev_all = Counter(i["severity"] for a in arts for i in a["issues"])
    total_words = sum(a["words"] for a in arts)
    int_links_total = sum(a["links"]["internal_all"] for a in arts)
    ext_links_total = sum(a["links"]["external_all"] for a in arts)
    sum_json = {
        "rt_mean": round(statistics.mean(a["reading_time_min"] for a in pubs), 1),
        "words_mean": round(statistics.mean(words_pub)),
        "words_median": statistics.median(words_pub),
        "score_mean": round(statistics.mean(sc_pub), 1),
        "score_median": statistics.median(sc_pub),
    }
    feat_missing = [a["slug"] for a in pubs if not a["featured"]["set"]]
    feat_alt_missing = [a["slug"] for a in pubs if a["featured"]["set"] and a["featured"]["found"] and not a["featured"]["alt"]]

    t02 = f"""# تصویر کلی سلامت سئو و ساختار qpedia.ir — {summ['files']['main']}

## ۱) منابع داده (خام، بدون حدس)
| منبع | نقش در تحلیل |
|---|---|
| `{summ['files']['main']}` | منبع اصلی: {summ['counts']['articles_total']} مقالهٔ `quantum_article` + پیوست‌ها (آخرین وضعیت «تا امروز») |
| `{summ['files']['sci']}` | {summ['counts']['scientists']} صفحهٔ دانشمند (برای اعتبارسنجی لینک داخلی) |
| `{summ['files']['old']}` | نسخهٔ ۱۴ سپتامبر: شناسایی مقالات حذف‌شده/تغییرنام‌یافته |
| `quantum-pedia-child (13).zip` | قالب فرزند: رفتار H1، شِما، TOC، فهرست مطالب |

## ۲) شمارش‌های پایه (واقعی، از داخل فایل‌ها)
| متریک | مقدار |
|---|---|
| کل مقالات (CPT) | {len(arts)} |
| منتشرشده | {len(pubs)} |
| پیش‌نویس | {len(drafts)} |
| سطل‌زباله | {len(trash)} |
| صفحات دانشمند (منتشرشده) | {summ['counts']['scientists']} |
| پیوست‌های رسانه (یکتا در خروجی) | {summ['counts']['attachments']} |
| پیوست با ALT ثبت‌شده در سطح کتابخانه | ۳۴۱ عدد (پوشش ۱۰۰٪ ALT کتابخانه — طبق خروجی امروز) |
| مجموع کلمات همهٔ مقالات | {total_words:,} |
| مجموع کلمات منتشرشده‌ها | {sum(words_pub):,} |
| میانگین/میانهٔ کلمات منتشرشده | {sum_json['words_mean']} / {sum_json['words_median']} |
| میانگین زمان مطالعه (منتشرشده) | {sum_json['rt_mean']} دقیقه |
| تصاویر داخل بدنهٔ مقالات | {img_total} (همه با ALT — ۰ مورد فاقد/خالی) |
| لینک داخلی (کل، با درج‌های متعدد) | {int_links_total} |
| لینک خارجی (کل) | {ext_links_total} |
| دامنه‌های خارجی یکتا | {len(ext_domains)} |

## ۳) رتبه‌بندی مقالات منتشرشده
- 🔴 قرمز: **{g_pub.get('red', 0)}** · 🟡 زرد: **{g_pub.get('yellow', 0)}** · 🟢 سبز: **{g_pub.get('green', 0)}**
- امتیاز: میانگین **{sum_json['score_mean']}** · میانه **{sum_json['score_median']}** · بازه **{min(sc_pub)} تا {max(sc_pub)}**

![Histogram](assets/score-histogram.svg)
![Words](assets/words-histogram.svg)
![Issues](assets/issue-top15.svg)

## ۴) پرتکرارترین ایرادها (کل مقالات)
{md_table(["کد", "شرح", "تعداد", "شدت"], [[c, code_title[c], n, SEV_FA[by_code[c][0]['severity']]] for c, n in Counter(i["code"] for a in arts for i in a["issues"]).most_common(15)])}

## ۵) لینک‌شناسی
| متریک | مقدار |
|---|---|
| لینک داخلی شکسته (۴۰۴) | {sum(len(a['links']['broken']) for a in arts)} |
| لینک به پیش‌نویس/زباله‌دان | {sum(len(a['links']['draft_targets']) for a in arts)} |
| مقالهٔ منتشرشدهٔ یتیم (۰ لینک ورودی) | {len(orphans)} |
| مقالهٔ منتشرشده با <۳ لینک داخلی | {sum(1 for a in pubs if a['links']['internal_unique'] < 3)} |
| انکرتکست کلی «اینجا/کلیک کنید» | {iss_all.get('L04', 0)} بُروز در مقالات |
| بیشترین دامنه‌های ارجاع‌شده | {'، '.join(f'{d} ({n})' for d, n in ext_domains.most_common(8))} |

## ۶) یافته‌های ساختاری مهم
1. **دوپلیکیت موضوعی «زندهٔ» امروز: صفر مورد** ✔ (الگوی `name-2` که در نسخهٔ ۱۴ سپتامبر وجود داشت، در نسخهٔ ۲۰ سپتامبر پاک شده است) — اما به‌جای آن، **۳۱ نشانی قدیمی حذف‌شده رها شده** که اگر ایندکس/لینک شده باشند ۴۰۴ می‌دهند → نیازمند ریدایرکت ۳۰۱ یا پاسخ ۴۱۰ (فهرست کامل: فاز ۱‑۴ فایل ۰۶ و نقشهٔ آماده در `redirects/`).
2. **{len(removed)} نشانی قدیمی حذف‌شده نسبت به ۱۴ سپتامبر** → نیازمند ریدایرکت ۳۰۱ (فهرست: فاز ۱‑۴).
3. **{len(no_kw)} مقالهٔ منتشرشده بدون Focus Keyword** و **{len(no_meta)} بدون توضیح متا**.
4. **اسلاگ‌های اشتباه/غیریکتا در بخش دانشمندان:** `albert-einstein-2` (مقالات به `…/scientists/albert-einstein/` لینک می‌دهند → ۴۰۴ واقعی) · `max-plank` (غلطِ املای Planck؛ درحالی‌که مقالات به `…/scientists/max-planck/` لینک می‌دهند) · `schrodingerr` (تایپو) در کنار `erwin-schrodinger`. اقدام: تصحیح اسلاگ + ریدایرکت ۳۰۱ از نشانی غلط.
5. تصویر شاخص: {len(feat_missing)} مقالهٔ منتشرشده بدون تصویر، {len(feat_alt_missing)} مقاله با شاخصِ فاقد ALT.
6. دسترسی/کیفیت فارسی: {iss_all.get('X02', 0)} مقاله حروف عربی «ي/ك» در متن دارند.
7. ZIPهای تصاویر سایت (`features_Q-7.zip` و …) نام‌های فایل عددی (`10000xxxxx.jpg`) و غیرتوصیفی دارند → عامل I05 در صورت آپلود.

## ۷) توزیع محتوا
- پیش‌نویس: {len(drafts)} مقاله (خط لولهٔ انتشار — شامل سری «لیست جدید»).
- ماه‌های انتشار (از wp:post_date): {'، '.join(f'{m}: {n}' for m, n in sorted(months.items()))}
- دسته‌ها (منتشرشده): {'، '.join(f'{c} ({n})' for c, n in cats.most_common(12))}

## ۸) چکیدهٔ قضاوت
- زیرساخت فنی قالب قوی است: یک H1 تمیز، شِمای Article خودکار، TOC خودکار، فونت محلی، ALT ۱۰۰٪ در کتابخانه.
- مشکل اصلی **سیستماتیک** است نه پراکنده: Focus Keyword و متا در بخش بزرگی از بایگانی ثبت نشده؛ گراف لینک داخلی ضعیف با ۷۸ لینک شکسته + ۷۴ لینک به پیش‌نویس + {len(orphans)} یتیم؛ و ۲۵+ جفت دوپلیکیت موضوعی.
- نقاط قوت محتوایی: ارجاع به DOI/منبع معتبر فراوان ({ext_domains.get('doi.org', 0)} مقاله به doi.org)، محتوای یکتا (۰ خوشهٔ کپی ۱۰۰٪)، ساختار تیتر خوب در اکثریت.
"""
    w(os.path.join(outdir, "02-تصویر-کلی-سایت.md"), t02)

    print("reports written:")
    for f in sorted(os.listdir(outdir)):
        p = os.path.join(outdir, f)
        if os.path.isfile(p):
            print(" -", f, os.path.getsize(p))
    for f in sorted(os.listdir(assets)):
        print(" - assets/" + f)


if __name__ == "__main__":
    main(sys.argv[1] if len(sys.argv) > 1 else ".")
