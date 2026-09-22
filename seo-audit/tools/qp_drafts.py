#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
qp_drafts.py — ارزیابی آمادگی انتشارِ پیش‌نویس‌ها از روی دادهٔ واقعی ممیزی.
خروجی: seo-audit/08-آمادگی-انتشار-پیش‌نویس‌ها.md
Usage: python3 seo-audit/tools/qp_drafts.py <repo_root>
"""
import json
import os
import re
import sys
from collections import Counter, defaultdict


def main(root):
    d = os.path.join(root, "seo-audit", "data")
    arts = json.load(open(os.path.join(d, "articles.json"), encoding="utf-8"))
    drafts = [a for a in arts if a["status"] == "draft"]
    pubs_by_slug = {a["slug"] for a in arts if a["status"] == "publish"}

    # ورودی هر پیش‌نویس از مقالات منتشرشده و پیش‌نویس‌های دیگر
    inb_pub = Counter(); inb_draft = Counter()
    draft_slugs = {a["slug"] for a in drafts}
    for a in arts:
        seen = set()
        for href, st in a["links"]["draft_targets"]:
            m = re.search(r"qpedia\.ir/([^/?#]+)/?", href)
            if m and m.group(1) in draft_slugs:
                if a["status"] == "publish":
                    inb_pub[m.group(1)] += 1
                else:
                    inb_draft[m.group(1)] += 1

    rows = []
    for a in drafts:
        missing = []
        if not a["meta_len"]:
            missing.append("متا")
        if not a["has_focus_kw"]:
            missing.append("کلیدواژه")
        if not a["featured"]["set"]:
            missing.append("شاخص")
        elif not a["featured"]["alt"]:
            missing.append("ALT شاخص")
        if a["words"] < 900:
            missing.append(f'حجم({a["words"]})')
        if not a["has_sources_head"]:
            missing.append("منابع")
        if a["links"]["internal_unique"] < 3:
            missing.append(f'داخلی({a["links"]["internal_unique"]})')
        n_miss = len(missing)
        pins = inb_pub.get(a["slug"], 0)
        dins = inb_draft.get(a["slug"], 0)
        if pins >= 1 and n_miss <= 1:
            tier = "A"
        elif n_miss <= 1:
            tier = "B+"
        elif n_miss <= 3:
            tier = "B"
        else:
            tier = "C"
        rows.append({
            "slug": a["slug"], "title": a["title"], "words": a["words"],
            "score": a["score"]["total"], "tier": tier, "missing": missing,
            "inb_pub": pins, "inb_draft": dins,
        })
    tier_rank = {"A": 0, "B+": 1, "B": 2, "C": 3}
    rows.sort(key=lambda r: (tier_rank[r["tier"]], -r["inb_pub"], -r["score"]))

    # تداخل با نقشهٔ ریدایرکت/۴۱۰
    import csv as _csv
    red_csv = os.path.join(root, "seo-audit", "redirects", "301-redirects.csv")
    conflicts = []
    if os.path.exists(red_csv):
        with open(red_csv, encoding="utf-8-sig") as f:
            for r in _csv.DictReader(f):
                slug = r["source_url"].strip("/")
                if slug in draft_slugs:
                    conflicts.append((r["source_url"], r["http_code"], r["target_url"]))

    tiers = Counter(r["tier"] for r in rows)
    lines = [
        "# ارزیابی آمادگی انتشار ۱۰۳ پیش‌نویس — قبل از افزونه یا بعد؟",
        "",
        "> همهٔ اعداد از دادهٔ واقعی (articles.json) استخراج شده‌اند.",
        "",
        "## نتیجهٔ کلی",
        "",
        f'- ردیف **A** (دارای لینک ورودی از مقالهٔ منتشرشده + تقریباً آماده): **{tiers.get("A",0)}** عدد → همین‌ها را اول منتشر کنید؛ با انتشارشان، لینک‌های ۴۰۴ مقالات زنده **خودبه‌خود سالم** می‌شوند و بخش ۲ افزونه (بازکردن لینک) برای آن‌ها لازم نیست.',
        f'- ردیف **B+** (آمادهٔ انتشار، بدون تقاضای لینک فعلی): **{tiers.get("B+",0)}** عدد',
        f'- ردیف **B** (با چند اصلاح کوچک آماده می‌شوند): **{tiers.get("B",0)}** عدد',
        f'- ردیف **C** (نیازمند کار جدی/تحرری قبل از انتشار): **{tiers.get("C",0)}** عدد',
        "",
        "## ⚠️ تداخل با نقشهٔ ریدایرکت (خیلی مهم قبل از انتشار)",
        "",
    ]
    if conflicts:
        lines.append(f"این نشانی‌ها در نقشهٔ ریدایرکت قوانین دارند **و هم‌زمان اسلاگ یک پیش‌نویس زنده‌اند**. اگر پیش‌نویس منتشر شود، آن قانون باید حذف شود (افزونهٔ QP خودش فقط هنگام ۴۰۴ فعال می‌شود و امن است؛ اما اگر قوانین را دستی در .htaccess بگذارید، این موارد را استثنا کنید):")
        lines.append("")
        for s, code, t in conflicts:
            lines.append(f"- `{s}` → کد {code} → {t or '—'}")
    else:
        lines.append("تداخلی یافت نشد. (پلیگر قوانین با اسلاگ پیش‌نویس‌ها صفر)")
    lines += [
        "",
        "## جدول کامل آمادگی (مرتب‌شده: ابتدا ردیف A، بر اساس تقاضای لینک و امتیاز)",
        "",
        "| ردیف | پیش‌نویس (اسلاگ) | امتیاز | کلمات | ورودی از منتشرشده | ورودی از پیش‌نویس | کمبودها قبل از انتشار |",
        "|---|---|---|---|---|---|---|",
    ]
    for r in rows:
        lines.append(
            f"| {r['tier']} | `{r['slug']}` | {r['score']} | {r['words']} | {r['inb_pub']} | {r['inb_draft']} | "
            + ("، ".join(r["missing"]) if r["missing"] else "✔ آماده") + " |"
        )
    lines += [
        "",
        "## ترتیب پیشنهادی عملیات",
        "",
        "1. **انتشار ردیف A** (بعد از چک چشمی ۵ دقیقه‌ای هر کدام در ویرایشگر)",
        "2. گرفتن خروجی تازه‌ٔ کامل از سایت (Tools → Export) و اجرای مجدد خط لولهٔ ممیزی (`qp_audit.py` → `qp_fixdata.py`) تا دادهٔ افزونه با وضعیت جدید هم‌خوان شود",
        "3. اجرای بخش‌های افزونه به ترتیب (۱ لینک‌شکسته → ۳ متا → ۴ کلیدواژه → ۵ ALT → ۶ ریدایرکت)",
        "4. انتشار دسته‌ای ردیف B/B+ به تدریج (هر هفته یک خوشهٔ موضوعی، نه همه یکجا — به علت سیگنال «تولید انبوه» و نیاز به لینک‌بافی)",
        "5. ردیف C ابتدا تحرری شود",
    ]
    out = os.path.join(root, "seo-audit", "08-آمادگی-انتشار-پیش‌نویس‌ها.md")
    with open(out, "w", encoding="utf-8") as f:
        f.write("\n".join(lines) + "\n")
    print("tiers:", dict(tiers), "| inb_pub hot:", inb_pub.most_common(10))
    print("conflicts:", conflicts)
    print("written:", out)


if __name__ == "__main__":
    main(sys.argv[1] if len(sys.argv) > 1 else ".")
