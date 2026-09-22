#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
qp_audit.py — ممیزی واقعی و کاملاً داده‌محور مقالات qpedia.ir
================================================================
ورودی: فایل‌های خروجی وردپرس (WXR) که در ریشه ریپو برون‌ریزی شده‌اند.
خروجی: seo-audit/data/  (articles.json / articles.csv / issues.csv / summary.json / charts/)
هیچ عددی حدس زده نمی‌شود؛ همه‌چیز از روی محتوای خام فایل‌ها شمارش می‌شود.

Usage:
    python3 seo-audit/tools/qp_audit.py "<repo_root>"

فقط از کتابخانه‌های استاندارد پایتون استفاده می‌کند (بدون pandas/lxml).
"""

import csv
import difflib
import hashlib
import json
import os
import re
import statistics
import sys
from collections import Counter, defaultdict
from html import unescape

# ---------------------------------------------------------------- ثوابت ورودی

WXR_MAIN = "WordPress.2026-09-21qpedia.xml" # خروجی تازهٔ ۲۱ سپتامبر ۲۰۲۶: ۳۱۰ مقاله (۲۱۷منتشر/۹۳پیش‌نویس)؛
WXR_SCI  = "WordPress.2026-09-21qpedia.xml" # دانشمندان هم در همان فایل کامل هستند (تکثیر بی‌ضرر: فیلتر بر پایهٔ post_type)
WXR_OLD  = "WordPress.2026-09-14 all.xml"   # فقط برای رجیستری اسلاگ‌ها/پیوست‌های قدیمی

SITE_HOSTS = ("qpedia.ir", "www.qpedia.ir", "http://qpedia.ir", "https://qpedia.ir")

GENERIC_ANCHORS = {
    "اینجا", "این‌جا", "کلیک کنید", "کلیک", "لینک", "بیشتر", "ادامه", "ادامه مطلب",
    "منبع", "source", "link", "here", "click", "click here", "read more",
    "بیشتر بخوانید", "بیشتر بدانید", "این لینک", "اینجا ببینید", "اینجا را ببینید",
    "ببینید", "مشاهده", "جزئیات بیشتر", ".", "…",
}

AUTHORITY_DOMAINS = {
    "doi.org", "dx.doi.org", "arxiv.org", "nature.com", "science.org", "sciencemag.org",
    "ieee.org", "ieeexplore.ieee.org", "aps.org", "journals.aps.org", "link.aps.org",
    "physicsworld.com", "quantamagazine.org", "phys.org", "plato.stanford.edu",
    "iep.utm.edu", "britannica.com", "wikipedia.org", "nasa.gov", "cern", "home.cern",
    "mit.edu", "stanford.edu", "springer.com", "link.springer.com", "sciencedirect.com",
    "elsevier.com", "wiley.com", "onlinelibrary.wiley.com", "researchgate.net",
    "ibm.com", "google.com", "microsoft.com", "ted.com",
}

NONDESCRIPTIVE_FILE_RE = re.compile(
    r"(?i)^(img|image|images|screenshot|screen shot|photo|picture|pic|file|files|untitled|"
    r"download|whatsapp|telegram|new|final|test|banner|slide|design|1000\d{6,}|\d{5,}|"
    r"file_[0-9a-f]{8,}|dsc\d+|pxl_|photo_| capture|clip)[\s_\-\d]*$"
)

ARABIC_CHARS_RE = re.compile("[یٺڻػؼؽ ء-غفكڪكکڵلمنهوٮيۍێېے]".replace("ی", "ي").replace(" ", ""))  # ی عربی U+064A و ک عربی U+0643
ARABIC_YA_KE = re.compile("[يىك]")

FA_DIGITS = "۰۱۲۳۴۵۶۷۸۹"

# ---------------------------------------------------------------- ابزار استخراج

def cdata_join(raw: str) -> str:
    """بازسازی محتوای CDATA که وردپرس آن را به صورت ]]]]><![CDATA[> شکسته است."""
    return raw.replace("]]]]><![CDATA[>", "]]>")


def field(item: str, tag: str, default: str = "") -> str:
    """استخراج یک فیلد (CDATA یا ساده) از داخل یک <item>."""
    open_c = f"<{tag}><![CDATA["
    i = item.find(open_c)
    if i >= 0:
        i += len(open_c)
        j = item.find("]]>" + f"</{tag}>", i)
        if j < 0:
            j = item.find(f"</{tag}>", i)
        return cdata_join(item[i:j] if j >= 0 else item[i:])
    m = re.search(rf"<{re.escape(tag)}>(.*?)</{re.escape(tag)}>", item, re.S)
    return unescape(m.group(1).strip()) if m else default


def parse_metas(item: str) -> dict:
    metas = defaultdict(list)
    for block in re.findall(r"<wp:postmeta>(.*?)</wp:postmeta>", item, re.S):
        k = field(block, "wp:meta_key")
        v = field(block, "wp:meta_value")
        if k:
            metas[k].append(v)
    return dict(metas)


def parse_terms(item: str) -> list:
    terms = []
    for m in re.finditer(r'<category domain="([^"]+)"(?: nicename="([^"]*)")?\s*>(.*?)</category>', item, re.S):
        dom, nice, val = m.group(1), m.group(2) or "", m.group(3)
        val = re.sub(r"^<!\[CDATA\[|\]\]>$", "", val)
        terms.append({"domain": dom, "nicename": nice, "name": val})
    return terms


def parse_items(path: str) -> list:
    xml = open(path, encoding="utf-8").read()
    out = []
    for m in re.finditer(r"<item>(.*?)</item>", xml, re.S):
        it = m.group(1)
        ptype = field(it, "wp:post_type")
        if not ptype:
            continue
        rec = {
            "post_id": field(it, "wp:post_id"),
            "title": field(it, "title"),
            "link": field(it, "link"),
            "guid": field(it, "guid"),
            "post_name": field(it, "wp:post_name"),
            "status": field(it, "wp:status"),
            "post_type": ptype,
            "post_parent": field(it, "wp:post_parent"),
            "pubDate": field(it, "pubDate"),
            "post_date": field(it, "wp:post_date"),
            "post_modified": field(it, "wp:post_modified"),
            "creator": field(it, "dc:creator"),
            "content": field(it, "content:encoded"),
            "excerpt": field(it, "excerpt:encoded"),
            "attachment_url": field(it, "wp:attachment_url"),
            "metas": parse_metas(it),
            "terms": parse_terms(it),
            "source_file": os.path.basename(path),
        }
        out.append(rec)
    return out

# ---------------------------------------------------------------- ابزار متن/HTML

TAG_RE = re.compile(r"<[^>]+>")
SCRIPT_STYLE_RE = re.compile(r"<(script|style)[^>]*>.*?</\1>", re.S | re.IGNORECASE)
IMG_RE = re.compile(r"<img\b[^>]*?/?>", re.IGNORECASE | re.DOTALL)
A_RE = re.compile(r"<a\b([^>]*)>(.*?)</a\s*>", re.IGNORECASE | re.DOTALL)
H_RE = re.compile(r"<(h[1-6])\b[^>]*>(.*?)</\1\s*>", re.IGNORECASE | re.DOTALL)
P_RE = re.compile(r"<p\b[^>]*>(.*?)</p\s*>", re.IGNORECASE | re.DOTALL)
ATTR_RE = lambda attr: re.compile(rf'{attr}\s*=\s*(["\'])(.*?)\1', re.IGNORECASE | re.DOTALL)


def meta_first(rec: dict, key: str) -> str:
    v = rec["metas"].get(key)
    return v[0] if v else ""


def get_attrs(tag_html: str) -> dict:
    out = {}
    for name in ("src", "alt", "href", "title", "srcset"):
        m = ATTR_RE(name).search(tag_html)
        if m:
            out[name] = unescape(m.group(2))
    return out


def norm_fa(s: str) -> str:
    s = s.replace("ي", "ی").replace("ك", "ک").replace("ک", "ک")
    s = s.replace("‌", " ")          # نیم‌فاصله
    s = re.sub(r"[\s\u00a0]+", " ", s)
    return s.strip().lower()


def strip_html(html: str) -> str:
    html = SCRIPT_STYLE_RE.sub(" ", html)
    html = re.sub(r"</(p|h[1-6]|li|blockquote|div)\s*>", "\n", html, flags=re.I)
    txt = TAG_RE.sub(" ", html)
    txt = unescape(txt)
    txt = re.sub(r"[ \t\r\f\v]+", " ", txt)
    txt = re.sub(r"\n\s*\n+", "\n", txt)
    return txt.strip()


def word_count(text: str) -> int:
    return len([t for t in re.split(r"\s+", text) if re.search(r"[A-Za-zÀ-ž؀-ۿ0-9۰-۹]", t)])


def sentence_count(text: str) -> int:
    parts = re.split(r"[.!?؟…]+", text)
    return len([p for p in parts if len(p.strip()) >= 15])


def basename(url: str) -> str:
    b = url.rstrip("/").split("/")[-1]
    return re.sub(r"\.(webp|png|jpe?g|gif|svg|avif)$", "", b, flags=re.I)


def is_internal_url(href: str) -> bool:
    h = href.lower()
    return h.startswith("/") or "qpedia.ir" in h


def host_of(href: str) -> str:
    m = re.match(r"https?://([^/?\s]+)", href.strip(), re.I)
    return (m.group(1).lower() if m else "")


def parse_php_width_height(serialized: str):
    w = re.search(r'"width";i:(\d+)', serialized)
    h = re.search(r'"height";i:(\d+)', serialized)
    fs = re.search(r'"filesize";i:(\d+)', serialized)
    return (
        int(w.group(1)) if w else 0,
        int(h.group(1)) if h else 0,
        int(fs.group(1)) if fs else 0,
    )

# ---------------------------------------------------------------- ساخت پایگاه داده

def build_datasets(repo_root: str) -> dict:
    main = parse_items(os.path.join(repo_root, WXR_MAIN))
    sci = parse_items(os.path.join(repo_root, WXR_SCI))
    old = parse_items(os.path.join(repo_root, WXR_OLD))

    articles = [r for r in main if r["post_type"] == "quantum_article"]
    scientists = [r for r in sci if r["post_type"] == "quantum_scientist"]
    others_main = [r for r in main if r["post_type"] not in ("quantum_article", "attachment")]
    old_articles = [r for r in old if r["post_type"] == "quantum_article"]

    # رجیستری «زندهٔ امروز»: فقط از خروجی‌های امروزی (مقالات + دانشمندان)
    registry = {}
    for src in (main, sci):
        for r in src:
            if r["post_type"] == "attachment":
                continue
            slug = r["post_name"] or basename(r["link"])
            if slug and slug not in registry:
                registry[slug] = {
                    "status": r["status"], "type": r["post_type"],
                    "title": r["title"], "source": r["source_file"],
                }
    # رجیستری تاریخی (نسخهٔ ۱۴ سپتامبر) فقط برای تشخیص نشانی‌های حذف‌شده
    history_registry = {}
    for r in old:
        if r["post_type"] == "attachment":
            continue
        slug = r["post_name"] or basename(r["link"])
        if slug and slug not in history_registry:
            history_registry[slug] = {"status": r["status"], "type": r["post_type"], "title": r["title"]}
    # پیوند‌های کامل شناخته‌شده
    known_links = set()
    for src in (main, sci, old):
        for r in src:
            if r["post_type"] == "attachment":
                continue
            for u in (r["link"], f"https://qpedia.ir/{r['post_name']}/" if r["post_name"] else ""):
                if u:
                    known_links.add(u.rstrip("/"))

    # جدول پیوست‌ها
    attach = {}
    attach_files = set()
    for src in (main, sci, old):
        for r in src:
            if r["post_type"] != "attachment":
                continue
            f = meta_first(r, "_wp_attached_file")
            url = r["attachment_url"] or (f"https://qpedia.ir/wp-content/uploads/{f}" if f else "")
            meta_ser = meta_first(r, "_wp_attachment_metadata")
            w, h, fsize = parse_php_width_height(meta_ser)
            rec = {
                "id": r["post_id"], "url": url, "file": f,
                "alt": meta_first(r, "_wp_attachment_image_alt"),
                "title": r["title"], "width": w, "height": h, "filesize": fsize,
                "parent": r["post_parent"],
            }
            attach[r["post_id"]] = rec
            if f:
                attach_files.add(f)
            if url:
                attach[url] = rec

    taxonomy_nicenames = set()
    for src in (main, sci, old):
        for r in src:
            for t in r["terms"]:
                if t["nicename"]:
                    taxonomy_nicenames.add(t["nicename"])

    # مقالات قدیمی که در خروجی امروز نیستند (نشانی‌های حذف‌شدهٔ احتمالی)
    today_article_slugs = {a["post_name"] for a in articles}
    removed_old = [r for r in old_articles if r["post_name"] not in today_article_slugs]

    return {
        "main": main, "sci": sci, "old": old,
        "articles": articles, "scientists": scientists,
        "others_main": others_main, "old_articles": old_articles,
        "registry": registry, "history_registry": history_registry, "known_links": known_links,
        "attach": attach, "attach_files": attach_files,
        "taxonomy_nicenames": taxonomy_nicenames,
        "removed_old": removed_old,
    }

# ---------------------------------------------------------------- موتور بررسی

ISSUE_META = {  # code → (severity, category_fa, title_fa, fix_fa)
    "T01": ("high", "عنوان و متا", "عنوان خالی/تکراری صفحه", "عنوان یکتا و توصیفی بنویسید (Google: «توصیفی و مختصر»)"),
    "T02": ("med",  "عنوان و متا", "عنوان خیلی بلند", "عنوان را به حدود ۶۰ نویسه کاهش دهید؛ گوگل تیترهای بلند را می‌بُرد یا بازنویسی می‌کند"),
    "T03": ("low",  "عنوان و متا", "عنوان خیلی کوتاه/مبهم", "عنوان باید موضوع را کامل برساند"),
    "T04": ("high", "عنوان و متا", "عنوان تکراری در صفحات", "هر صفحه title یکتا می‌خواهد (Google: «اجتناب از متن تکراری یا boilerplate»)"),
    "M01": ("high", "عنوان و متا", "توضیحات متا ندارد", "توضیح متا یکتا برای صفحه بنویسید (Google: « unique descriptions for each page »)"),
    "M02": ("med",  "عنوان و متا", "توضیحات متا خیلی کوتاه", "طول متای مناسب حدود ۱۲۰ تا ۱۶۰ نویسه است"),
    "M03": ("low",  "عنوان و متا", "توضیحات متا خیلی بلند", "متا بیش از ~۱۷۰ نویسه در نتایج بریده می‌شود"),
    "M04": ("high", "عنوان و متا", "توضیحات متا تکراری", "متا باید برای هر صفحه اختصاصی باشد"),
    "K01": ("med",  "کلیدواژه", "کلیدواژه کانونی (Focus Keyword) ثبت نشده", "در Rank Math برای مقاله کلیدواژه کانونی تعریف کنید"),
    "K02": ("med",  "کلیدواژه", "کلیدواژه کانونی در عنوان نیست", "کلیدواژه را به‌طور طبیعی در عنوان بیاورید"),
    "K03": ("low",  "کلیدواژه", "کلیدواژه کانونی در متای توضیح نیست", "کلیدواژه را در توضیح متا بگنجانید"),
    "K04": ("med",  "کلیدواژه", "کلیدواژه در پاراگراف آغازین نیست", "در صد کلمهٔ نخست، موضوع (کلیدواژه) روشن شود"),
    "K05": ("low",  "کلیدواژه", "کلیدواژه در اسلاگ نیست", "اسلاگ انگلیسی کوتاه و هم‌معنا با کلیدواژه باشد"),
    "K06": ("med",  "کلیدواژه", "تراکم کلیدواژه بیش از حد", "تراکم را زیر ~۲.۵٪ نگه دارید (جلوگیری از Keyword Stuffing)"),
    "S01": ("med",  "اسلاگ", "اسلاگ غیرلاتین", "اسلاگ باید لاتین و خوانا باشد"),
    "S02": ("low",  "اسلاگ", "اسلاگ بلند", "اسلاگ را کوتاه کنید"),
    "C01": ("high", "محتوا", "محتوای بسیار نازک", "گوگل محتوای «کامل و جامع» می‌خواهد؛ صفحه محتوای کافی ندارد"),
    "C02": ("high", "محتوا", "محتوای خالی", "صفحه بدون محتوا در سایت باقی نماند"),
    "C03": ("med",  "محتوا", "ساختار تیتر ضعیف", "حداقل ۳ تیتر H2 معنادار بگذارید تا فهرست‌مطالب و اسکن‌پذیری ساخته شود"),
    "C04": ("low",  "محتوا", "پرش در سلسله‌مراتب تیتر", "سلسله‌مراتب H2→H3 را بدون پرش رعایت کنید"),
    "C05": ("med",  "محتوا", "H1 داخل بدنهٔ محتوا", "قالب خودش عنوان را با H1 چاپ می‌کند؛ داخل متن از H1 استفاده نکنید"),
    "C06": ("low",  "محتوا", "پاراگراف بسیار بلند", "پاراگراف‌های بلند را بشکنید (خوانایی/People-first)"),
    "C07": ("med",  "محتوا", "بخش منابع/ارجاع ندارد", "برای ادعای علمی، بخش «منابع» با حداقل ۳ منبع معتبر لازم است"),
    "C08": ("med",  "محتوا", "ارجاع خارجی کمتر از ۳", "حداقل ۳ لینک معتبر خارجی (DOI/دانشگاه/مرجع علمی) بدهید"),
    "C09": ("low",  "محتوا", "بخش پرسش‌های متداول ندارد", "افزودن FAQ ساخت‌یافته به پوشش نیت جستجو کمک می‌کند"),
    "C10": ("low",  "محتوا", "بدون فهرست/نقل‌قول/جعبه", "برای اسکن‌پذیری، از لیست، نقل‌قول یا جعبه نکته استفاده شود"),
    "C11": ("low",  "محتوا", "بدون بخش جمع‌بندی", "پایان‌بندی انسانی و روشن داشته باشید"),
    "C12": ("low",  "محتوا", "بدون پوشش سوءبرداشت رایج", "مطابق استاندارد Qpedia، سوءبرداشت‌های رایج اصلاح شود"),
    "I01": ("high", "تصویر", "تصویر شاخص ندارد", "تصویر شاخص اختصاصی و مرتبط تعریف کنید"),
    "I02": ("high", "تصویر", "تصویر شاخص ALT ندارد", "ALT فارسی توصیفی برای تصویر شاخص بنویسید (Google Images + WCAG)"),
    "I03": ("med",  "تصویر", "تصویر داخل متن بدون ALT", "برای هر تصویر محتوایی ALT توصیفی بنویسید؛ صرفاً تزئینی alt خالی آگاهانه"),
    "I04": ("low",  "تصویر", "ALT بیش‌ازحد بلند", "ALT را زیر ~۱۲۵ نویسه نگه دارید"),
    "I05": ("med",  "تصویر", "نام فایل تصویر غیرتوصیفی", "نام فایل را توصیفی‌لاتین کنید (Google از نام فایل هم استفاده می‌کند)"),
    "I06": ("low",  "تصویر", "تصویر از دامنهٔ خارجی (hotlink)", "تصویر را در هاست خودتان میزبانی کنید"),
    "I07": ("med",  "تصویر", "اشارهٔ شاخص به پیوست ناموجود", "تصویر شاخص را دوباره تنظیم کنید"),
    "I08": ("low",  "تصویر", "ابعاد تصویر شاخص کوچک", "برای نمایش شاخص، پهنای ~۱۲۰۰ پیکسل توصیه می‌شود"),
    "L01": ("med",  "پیوند", "لینک داخلی کمتر از ۳", "حداقل ۳ لینک داخلی مرتبط با انکرتکست طبیعی بدهید"),
    "L02": ("high", "پیوند", "لینک داخلی شکسته (۴۰۴)", "مقصد لینک در سایت وجود ندارد؛ اصلاح یا حذف شود"),
    "L03": ("high", "پیوند", "لینک به پیش‌نویس/آشغال‌دان", "مقصد در سایت عمومی نیست؛ برای خواننده ۴۰۴ می‌شود"),
    "L04": ("low",  "پیوند", "انکرتکست کلی (اینجا/کلیک کنید)", "انکرتکست توصیفی بنویسید (Google: «descriptive anchor text»)"),
    "L05": ("med",  "پیوند", "لینک بدون متن/خالی", "لینک باید متن یا ALT تصویر داشته باشد"),
    "L06": ("low",  "پیوند", "لینک به خود مقاله", "سلف‌لینک را حذف کنید"),
    "L07": ("low",  "پیوند", "لینک HTTP (غیر HTTPS)", "لینک‌ها را HTTPS کنید"),
    "X01": ("high", "یکتایی", "محتوای تکراری/هم‌پوشان", "محتوا باید برای هر صفحه یکتا باشد"),
    "X02": ("low",  "یکتایی", "حروف عربی به‌جای فارسی (ی/ک)", "ی و ک فارسی استفاده شود (کدگذاری پاک = سیگنال کیفیت)"),
    "X03": ("low",  "فنی", "بدون دسته‌بندی کوانتومی", "دستهٔ مناسب انتخاب شود"),
    "X04": ("low",  "فنی", "رمزِ شِمای Rank Math ثبت نشده", "قالب شِمای Article خودکار چاپ می‌کند ولی ثبت شمای Rank Math کامل‌تر است"),
    "X05": ("med",  "فنی", "صفحه یتیم (بدون لینک ورودی داخلی)", "از مقالات مرتبط به این صفحه لینک بدهید"),
    "X06": ("low",  "فنی", "تاریخ انتشار ثبت نشده", "pubDate/wp:post_date خالی است"),
    "X07": ("high", "یکتایی", "مقالهٔ هم‌موضوع تکراری هم‌زمان منتشرشده (الگوی name و name-2)", "ادغام محتوا در قوی‌ترین نسخه + ریدایرکت ۳۰۱ نسخهٔ ضعیف‌تر (رفع Keyword Cannibalization)"),
}


def add_issue(issues, rec, code, location, evidence):
    sev, cat, title, fix = ISSUE_META[code]
    issues.append({
        "post_id": rec["post_id"], "slug": rec["post_name"], "url": rec["link"],
        "status": rec["status"], "code": code, "severity": sev,
        "category": cat, "title": title, "location": location,
        "evidence": evidence[:600], "fix": fix,
    })


def check_article(rec: dict, db: dict) -> dict:
    issues = []
    content = rec["content"] or ""
    text = strip_html(content)
    wcount = word_count(text)

    title = rec["title"].strip()
    metas = rec["metas"]
    rm_title = meta_first(rec, "rank_math_title")
    rm_desc = meta_first(rec, "rank_math_description")
    yo_desc = meta_first(rec, "_yoast_wpseo_metadesc")
    yo_kw = meta_first(rec, "_yoast_wpseo_focuskw")
    rm_kw_raw = meta_first(rec, "rank_math_focus_keyword")
    meta_desc = rm_desc or yo_desc
    focus_kw = (rm_kw_raw.split(",")[0].strip() if rm_kw_raw else yo_kw.strip())
    schema_keys = [k for k in metas if k.startswith("rank_math_schema")]

    # --- عنوان
    title_len = len(title)
    if not title:
        add_issue(issues, rec, "T01", "post_title", "")
    elif title_len > 65:
        add_issue(issues, rec, "T02", "post_title", f"{title_len} نویسه: «{title[:90]}…»")
    elif title_len < 12:
        add_issue(issues, rec, "T03", "post_title", title)

    # --- متا
    meta_len = len(meta_desc)
    if not meta_desc:
        add_issue(issues, rec, "M01", "rank_math_description / _yoast_wpseo_metadesc", "")
    else:
        if meta_len < 100:
            add_issue(issues, rec, "M02", "meta description", f"{meta_len} نویسه: «{meta_desc}»")
        elif meta_len > 170:
            add_issue(issues, rec, "M03", "meta description", f"{meta_len} نویسه")

    # --- کلیدواژه
    nkw = norm_fa(focus_kw) if focus_kw else ""
    ntitle = norm_fa(title)
    nmeta = norm_fa(meta_desc)
    ntext = norm_fa(text)
    if not focus_kw:
        add_issue(issues, rec, "K01", "rank_math_focus_keyword / _yoast_wpseo_focuskw", "")
    else:
        if nkw and nkw not in ntitle:
            add_issue(issues, rec, "K02", "post_title", f"kw=«{focus_kw}»")
        if meta_desc and nkw and nkw not in nmeta:
            add_issue(issues, rec, "K03", "meta description", f"kw=«{focus_kw}»")
        first100 = norm_fa(" ".join(text.split()[:100]))
        if nkw and nkw not in first100:
            add_issue(issues, rec, "K04", "پاراگراف آغازین", f"kw=«{focus_kw}»")
        kw_words = [w for w in nkw.split() if w]
        slug_ascii = re.sub(r"[^a-z0-9 -]", "", norm_fa(rec["post_name"]))
        en_part = re.findall(r"[a-zA-Z ()]+", focus_kw)
        has_latin_kw = bool(en_part and en_part[0].strip())
        if has_latin_kw and norm_fa(en_part[0]) not in norm_fa(rec["post_name"].replace("-", " ")):
            add_issue(issues, rec, "K05", "post_name", f"slug=«{rec['post_name']}» kw=«{focus_kw}»")
        if wcount and nkw and len(nkw) >= 3:
            cnt = ntext.count(nkw)
            density = (cnt * len(nkw.split())) / max(wcount, 1) * 100
            if density > 2.5:
                add_issue(issues, rec, "K06", "بدنه", f"تراکم≈{density:.1f}٪ ({cnt} تکرار کلیدواژه «{focus_kw}»)")

    # --- اسلاگ
    slug = rec["post_name"]
    if not re.fullmatch(r"[a-z0-9\-]+", slug or ""):
        add_issue(issues, rec, "S01", "post_name", slug)
    if slug and (len(slug) > 60 or slug.count("-") >= 6):
        add_issue(issues, rec, "S02", "post_name", f"{len(slug)} نویسه")

    # --- تیترها
    headings = [(int(m.group(1)[1]), strip_html(m.group(2)).strip()) for m in H_RE.finditer(content)]
    h1s = [t for lv, t in headings if lv == 1]
    h2s = [t for lv, t in headings if lv == 2]
    if h1s:
        add_issue(issues, rec, "C05", "بدنهٔ محتوا", f"{len(h1s)} عدد H1 داخل متن: «{h1s[0][:60]}»")
    if len(h2s) < 3 and rec["status"] == "publish":
        add_issue(issues, rec, "C03", "ساختار تیتر", f"فقط {len(h2s)} تیتر H2")
    prev = 0
    skip_found = False
    for lv, t in headings:
        if lv == 1:
            continue
        if prev and lv > prev + 1 and not skip_found:
            add_issue(issues, rec, "C04", "سلسله‌مراتب تیتر", f"پرش H{prev} → H{lv} در «{t[:60]}»")
            skip_found = True
        if lv >= 2:
            prev = lv

    # --- محتوا
    paras = [strip_html(p) for p in P_RE.findall(content)]
    paras = [p for p in paras if p.strip()]
    long_paras = [(i + 1, p) for i, p in enumerate(paras) if len(p) > 900]
    if wcount < 20:
        add_issue(issues, rec, "C02", "بدنه", f"{wcount} کلمه")
    elif wcount < 600 and rec["status"] == "publish":
        add_issue(issues, rec, "C01", "بدنه", f"{wcount} کلمه")
    if long_paras:
        i0, p0 = long_paras[0]
        add_issue(issues, rec, "C06", f"پاراگراف #{i0}", f"{len(p0)} نویسه | آغاز: «{p0[:80]}…»")

    # --- منابع / FAQ / جمع‌بندی / سوءبرداشت
    heads_text = " ".join(t for _, t in headings)
    has_sources_head = bool(re.search(r"منابع|references", heads_text, re.I))
    ext_links_all = []
    for m in A_RE.finditer(content):
        href = get_attrs(m.group(0)).get("href", "")
        if href.startswith("http") and not is_internal_url(href):
            ext_links_all.append(href)
    if rec["status"] == "publish" and not has_sources_head:
        add_issue(issues, rec, "C07", "تیترها", "هیچ تیتری با «منابع» یافت نشد")
    if rec["status"] == "publish" and len(set(h.split("#")[0].split("?")[0] for h in ext_links_all)) < 3:
        add_issue(issues, rec, "C08", "بدنه", f"فقط {len(set(ext_links_all))} لینک خارجی یکتا")
    has_faq = ("پرسش‌های متداول" in heads_text) or ("سوالات متداول" in heads_text) or ("FAQ" in heads_text)
    if rec["status"] == "publish" and not has_faq:
        add_issue(issues, rec, "C09", "تیترها", "")
    has_listish = bool(re.search(r"<(ul|ol|blockquote)\b", content, re.I))
    if not has_listish:
        add_issue(issues, rec, "C10", "بدنه", "")
    has_summary = bool(re.search(r"جمع‌بندی|سخن پایانی|در یک نگاه|خلاصه", heads_text))
    if not has_summary:
        add_issue(issues, rec, "C11", "تیترها", "")
    has_misconception = ("سوءبرداشت" in text) or ("سو‌برداشت" in text)
    if not has_misconception:
        add_issue(issues, rec, "C12", "بدنه", "")

    # --- تصاویر داخل متن
    imgs = IMG_RE.findall(content)
    img_stats = {"total": 0, "missing_alt": 0, "empty_alt": 0, "long_alt": 0, "bad_name": 0, "hotlink": 0}
    for idx, im in enumerate(imgs, 1):
        attrs = get_attrs(im)
        src = attrs.get("src", "")
        if not src:
            continue
        img_stats["total"] += 1
        if "alt" not in attrs:
            img_stats["missing_alt"] += 1
            add_issue(issues, rec, "I03", f"تگ img #{idx}", f"src=«…{src[-70:]}» — فاقد صفت alt")
        else:
            alt = attrs["alt"].strip()
            if not alt:
                img_stats["empty_alt"] += 1
            elif len(alt) > 160:
                img_stats["long_alt"] += 1
                add_issue(issues, rec, "I04", f"تگ img #{idx}", f"{len(alt)} نویسه ALT")
        bname = basename(src)
        if NONDESCRIPTIVE_FILE_RE.match(bname):
            img_stats["bad_name"] += 1
            add_issue(issues, rec, "I05", f"تگ img #{idx}", f"نام فایل: «{bname}»")
        if src.startswith("http") and not is_internal_url(src):
            img_stats["hotlink"] += 1
            add_issue(issues, rec, "I06", f"تگ img #{idx}", f"src=«{src[:90]}»")

    # --- تصویر شاخص
    thumb_id = meta_first(rec, "_thumbnail_id")
    featured = None
    if not thumb_id:
        if rec["status"] == "publish":
            add_issue(issues, rec, "I01", "_thumbnail_id", "")
    else:
        featured = db["attach"].get(thumb_id)
        if not featured:
            add_issue(issues, rec, "I07", "_thumbnail_id", f"id={thumb_id} در هیچ خروجی یافت نشد")
        else:
            if not featured["alt"].strip():
                add_issue(issues, rec, "I02", f"تصویر شاخص (attachment #{thumb_id})", f"فایل: {featured['file'] or featured['url'][-60:]}")
            if featured["width"] and featured["width"] < 1200:
                add_issue(issues, rec, "I08", f"تصویر شاخص (attachment #{thumb_id})", f"{featured['width']}×{featured['height']}")

    # --- لینک‌ها
    int_links = []
    broken = []
    draft_targets = []
    seen_targets = set()
    generic_found = []
    self_links = 0
    http_links = 0
    empty_anchor = 0
    upload_refs = []
    for idx, m in enumerate(A_RE.finditer(content), 1):
        tag = m.group(0)
        attrs = get_attrs(tag)
        href = (attrs.get("href") or "").strip()
        anchor = strip_html(m.group(0))[0:200].strip()
        anchor_txt = re.sub(r"\s+", " ", anchor)
        if not href or href.startswith("#") or href.startswith(("mailto:", "tel:", "javascript:")):
            continue
        if href.lower().startswith("http://"):
            http_links += 1
        if not anchor_txt:
            empty_anchor += 1
            add_issue(issues, rec, "L05", f"لینک #{idx}", f"href=«{href[:80]}»")
        elif norm_fa(anchor_txt) in {norm_fa(x) for x in GENERIC_ANCHORS}:
            generic_found.append((anchor_txt, href))
        if is_internal_url(href):
            path = re.sub(r"^[a-z]+://[^/]+", "", href, flags=re.I)
            path = path.split("?")[0].split("#")[0].strip("/")
            if not path:
                int_links.append(href)
                continue
            if "wp-content/uploads" in href:
                upload_refs.append(href)
                continue
            seg = path.split("/")
            cand = seg[-1] if len(seg) > 1 else seg[0]
            if seg[0] in ("quantum_category", "category", "tag", "author"):
                int_links.append(href)
                continue
            hit = db["registry"].get(seg[0]) or db["registry"].get(cand)
            if not hit:
                broken.append((href, anchor_txt))
                ev = f"«{anchor_txt[:40]}» → {href[:110]}"
                hist = db["history_registry"].get(seg[0]) or db["history_registry"].get(cand)
                if hist:
                    ev += f" | [این نشانی در نسخهٔ ۱۴ سپتامبر «{hist['status']}» بود و حذف شده]"
                sug = db.get("suggest_fn")(cand) if db.get("suggest_fn") else ""
                if sug and sug != cand:
                    ev += f" | نزدیک‌ترین مقصد فعال: https://qpedia.ir/.../{sug}/"
                add_issue(issues, rec, "L02", f"لینک #{idx}", ev)
            else:
                int_links.append(href)
                if hit["status"] != "publish":
                    draft_targets.append((href, hit["status"]))
                    add_issue(issues, rec, "L03", f"لینک #{idx}", f"«{anchor_txt[:40]}» → {href[:90]} [{hit['status']}]")
            self_slug = rec["post_name"]
            if cand == self_slug or seg[0] == self_slug:
                self_links += 1

    if self_links:
        add_issue(issues, rec, "L06", "بدنه", f"{self_links} عدد")
    if http_links:
        add_issue(issues, rec, "L07", "بدنه", f"{http_links} عدد لینک http://")
    for a, h in generic_found[:2]:
        add_issue(issues, rec, "L04", "بدنه", f"انکر «{a[:30]}» → {h[:80]}")

    uniq_int = {h.split("?")[0].split("#")[0].rstrip("/") for h in int_links}
    # سلف‌لینک از شمارش مؤثر کم شود
    self_url = (rec["link"] or "").rstrip("/")
    if self_url in uniq_int:
        uniq_int.discard(self_url)
    n_int = len(uniq_int)
    if rec["status"] == "publish" and n_int < 3:
        add_issue(issues, rec, "L01", "بدنه", f"{n_int} لینک داخلی یکتا")

    # --- یکتایی محتوا
    normtext = re.sub(r"\s+", " ", norm_fa(text))
    content_hash = hashlib.md5(normtext.encode("utf-8")).hexdigest()

    # --- تایپوگرافی فارسی
    arabic_hits = len(ARABIC_YA_KE.findall(text))
    if arabic_hits >= 3:
        add_issue(issues, rec, "X02", "بدنه", f"{arabic_hits} بُروز «ي/ك» عربی")

    # --- دسته و شِما
    qc = [t for t in rec["terms"] if t["domain"] == "quantum_category"]
    if not qc and rec["status"] == "publish":
        add_issue(issues, rec, "X03", "quantum_category", "")
    if not schema_keys and rec["status"] == "publish":
        add_issue(issues, rec, "X04", "rank_math_schema_*", "")
    if not rec["pubDate"] and rec["status"] == "publish":
        add_issue(issues, rec, "X06", "pubDate", "")

    lead = meta_first(rec, "qpt_lead")
    nokat = meta_first(rec, "qpt_nokat")

    return {
        "post_id": rec["post_id"],
        "slug": slug,
        "url": rec["link"],
        "status": rec["status"],
        "title": title,
        "title_len": title_len,
        "seo_title": rm_title,
        "has_seo_title": bool(rm_title),
        "meta_desc": meta_desc,
        "meta_len": meta_len,
        "meta_via": ("rank_math" if rm_desc else ("yoast" if yo_desc else "")),
        "focus_kw": focus_kw,
        "has_focus_kw": bool(focus_kw),
        "slug_ascii": bool(re.fullmatch(r"[a-z0-9\-]+", slug or "")),
        "words": wcount,
        "sentences": sentence_count(text),
        "paragraphs": len(paras),
        "long_paras": len(long_paras),
        "reading_time_min": round(wcount / 200, 1),
        "h2_count": len(h2s),
        "h3_count": len([t for lv, t in headings if lv == 3]),
        "heading_total": len(headings),
        "headings_sample": [t for _, t in headings[:12]],
        "has_sources_head": has_sources_head,
        "has_faq": has_faq,
        "has_listish": has_listish,
        "has_summary": has_summary,
        "has_misconception": has_misconception,
        "has_lead": bool(lead or nokat),
        "img": img_stats,
        "featured": {
            "set": bool(thumb_id),
            "id": thumb_id,
            "found": bool(featured),
            "alt": (featured["alt"] if featured else ""),
            "width": (featured["width"] if featured else 0),
            "height": (featured["height"] if featured else 0),
            "file": (featured["file"] if featured else ""),
        },
        "links": {
            "internal_unique": n_int,
            "internal_all": len(int_links),
            "external_all": len(ext_links_all),
            "external_unique": len({h.split('#')[0].split('?')[0] for h in ext_links_all}),
            "broken": broken,
            "draft_targets": draft_targets,
            "generic": generic_found,
            "empty_anchor": empty_anchor,
            "self": self_links,
            "http": http_links,
            "upload_refs": upload_refs,
            "ext_domains": sorted({host_of(h).replace("www.", "") for h in ext_links_all}),
            "authority_hits": sum(1 for h in ext_links_all if any(d in host_of(h).lower() for d in AUTHORITY_DOMAINS)),
        },
        "categories": [t["name"] for t in qc],
        "category_slugs": [t["nicename"] for t in qc],
        "schema": bool(schema_keys),
        "pubDate": rec["pubDate"],
        "post_date": rec["post_date"],
        "post_modified": rec["post_modified"],
        "excerpt_len": len(rec["excerpt"]),
        "arabic_hits": arabic_hits,
        "content_hash": content_hash,
        "text_preview": text[:400],
        "issues": issues,
    }

# ---------------------------------------------------------------- امتیازدهی

RUBRIC = [
    # (بخش, سقف, توضیح)
    ("A. عنوان و متا", 18, "title: موجود/طول/یکتایی/SEO-title · متا: موجود/طول/یکتایی · خلاصه (excerpt)"),
    ("B. کلیدواژه", 10, "Focus Keyword + حضور در عنوان/متا/آغاز متن/اسلاگ"),
    ("C. محتوا و ساختار", 26, "حجم محتوا، تیترها، منابع، FAQ، لیست، پاراگراف"),
    ("D. تصاویر", 16, "شاخص + ALT شاخص + ALT متن + نام فایل + عدم hotlink"),
    ("E. پیوندها", 16, "داخلی ≥۳، بدون لینک شکسته، خارجی ≥۳، انکر توصیفی"),
    ("F. فنی و یکتایی", 8, "یکتایی محتوا، اسلاگ، دسته، لینک ورودی، تایپوگرافی FA، تاریخ"),
    ("G. استاندارد تحرریه Qpedia", 6, "سوءبرداشت، جمع‌بندی، Lead/نکات کلیدی"),
]


def score_article(a: dict, inbound: int, dup: bool) -> dict:
    s = {}
    iss_codes = {i["code"] for i in a["issues"]}

    tl = a["title_len"]
    s["a_title"] = (4 if 12 <= tl <= 65 else (3 if (10 <= tl < 12 or 65 < tl <= 70) else 0))
    s["a_title_uniq"] = 0 if "T04" in iss_codes else 2
    s["a_seo_title"] = 2 if a["has_seo_title"] else 0
    s["a_meta"] = 4 if a["meta_len"] else 0
    s["a_meta_len"] = (3 if 120 <= a["meta_len"] <= 170 else (2 if 100 <= a["meta_len"] <= 400 else 0))
    s["a_meta_uniq"] = 0 if "M04" in iss_codes else 1
    s["a_excerpt"] = 2 if (a["meta_len"] or a["excerpt_len"]) else 0  # حداقل یک خلاصه قابل‌استفاده برای اسنیپت
    A = sum(v for k, v in s.items() if k.startswith("a_"))

    b = {
        "b_kw": 4 if a["has_focus_kw"] else 0,
        "b_kw_title": 0 if ({"K01", "K02"} & iss_codes) else 2,
        "b_kw_meta": 0 if ({"K01", "K03"} & iss_codes and a["meta_len"]) else (1 if a["has_focus_kw"] else 0),
        "b_kw_first": 0 if ({"K01", "K04"} & iss_codes) else 2,
        "b_kw_slug": 0 if "S01" in iss_codes else 1,
    }
    B = sum(b.values())

    wc = a["words"]
    c_words = 8 if wc >= 2200 else 6 if wc >= 1200 else 4 if wc >= 900 else 2 if wc >= 600 else 0
    c = {
        "c_words": c_words,
        "c_h2": 4 if a["h2_count"] >= 3 else (2 if a["h2_count"] >= 1 else 0),
        "c_hier": 0 if "C04" in iss_codes or "C05" in iss_codes else 2,
        "c_par": 0 if "C06" in iss_codes else 2,
        "c_sources": 4 if (a["has_sources_head"] and a["links"]["external_unique"] >= 3) else (2 if a["links"]["external_unique"] >= 1 else 0),
        "c_faq": 2 if a["has_faq"] else 0,
        "c_list": 2 if a["has_listish"] else 0,
        "c_summary": 2 if a["has_summary"] else 0,
    }
    C = sum(c.values())

    f = a["featured"]
    d_noimg_bonus = 3 if a["img"]["total"] == 0 else 0
    d = {
        "d_feat": 4 if f["set"] and f["found"] else 0,
        "d_feat_alt": 4 if (f["set"] and f["found"] and f["alt"].strip()) else 0,
        "d_imgs_alt": (4 if (a["img"]["missing_alt"] == 0) else max(0, 4 - 2 * a["img"]["missing_alt"])) if a["img"]["total"] else d_noimg_bonus,
        "d_alt_len": 0 if "I04" in iss_codes else 2,
        "d_hotlink": 0 if "I06" in iss_codes else 1,
        "d_name": 0 if "I05" in iss_codes else 1,
    }
    D = sum(d.values())

    l_ = a["links"]
    e = {
        "e_int": 5 if l_["internal_unique"] >= 3 else (2 if l_["internal_unique"] >= 1 else 0),
        "e_broken": 0 if {"L02", "L03"} & iss_codes else 4,
        "e_ext": 3 if l_["external_unique"] >= 3 else (1 if l_["external_unique"] >= 1 else 0),
        "e_anchor": 0 if {"L04", "L05"} & iss_codes else 2,
        "e_clean": 0 if {"L06", "L07"} & iss_codes else 2,
    }
    E = sum(e.values())

    x = {
        "x_uniq": 0 if (dup or "X07" in iss_codes) else 2,
        "x_slug": 1 if a["slug_ascii"] else 0,
        "x_cat": 1 if a["categories"] else 0,
        "x_inbound": 2 if inbound >= 1 else 0,
        "x_typo": 0 if "X02" in iss_codes else 1,
        # pubDate خالی برای پیش‌نویس رفتار طبیعی وردپرس است و نباید جریمه شود
        "x_date": 1 if (a["pubDate"] or a["status"] != "publish") else 0,
    }
    F = sum(x.values())

    g = {
        "g_miscon": 2 if a["has_misconception"] else 0,
        "g_summary": 2 if a["has_summary"] else 0,
        "g_lead": 2 if a["has_lead"] else 0,
    }
    G = sum(g.values())

    total = A + B + C + D + E + F + G
    if dup:
        total = min(total, 59)
    if a["words"] < 20:
        total = min(total, 5)
    n_high = sum(1 for i in a["issues"] if i["severity"] == "high")
    hard_fail = bool({"X01", "X07", "C02"} & iss_codes)
    if total < 55 or hard_fail or (a["status"] == "publish" and n_high >= 4):
        grade = "red"
    elif total >= 80 and n_high == 0:
        grade = "green"
    else:
        grade = "yellow"
    return {
        "A": A, "B": B, "C": C, "D": D, "E": E, "F": F, "G": G,
        "total": total, "grade": grade,
        "detail": {**s, **b, **c, **d, **e, **x, **g},
    }

# ---------------------------------------------------------------- اجرای کلی

def run(repo_root: str):
    out_dir = os.path.join(repo_root, "seo-audit", "data")
    os.makedirs(out_dir, exist_ok=True)
    db = build_datasets(repo_root)

    live_slug_list = [s for s, v in db["registry"].items() if v["status"] == "publish"]

    def _suggest(slug: str) -> str:
        if not slug:
            return ""
        m = difflib.get_close_matches(slug, live_slug_list, n=1, cutoff=0.75)
        return m[0] if m else ""

    db["suggest_fn"] = _suggest

    # خوشه‌های اسلاگ مشابه (name / name-2) — فقط میان صفحات «زندهٔ امروز» — تشخیص دوپلیکیت موضوعی
    # پسوندهای ۴رقمی (سال، مثل ‎-2025) دوپلیکیت محسوب نمی‌شوند.
    BASE_SUFFIX_RE = re.compile(r"-([2-9]|[1-9]\d)$")

    base_groups = defaultdict(list)
    for sl, v in db["registry"].items():
        base = BASE_SUFFIX_RE.sub("", sl)
        base_groups[base].append({"slug": sl, "type": v["type"], "status": v["status"], "title": v["title"][:70]})
    similar_slug_groups = {
        k: v for k, v in base_groups.items()
        if len(v) > 1 and len({x["slug"] for x in v}) > 1 and sum(x["status"] == "publish" for x in v) >= 2
    }
    twin_articles = defaultdict(list)  # slug مقاله → دوقلوهای منتشرشدهٔ هم‌نوع
    for base, group in similar_slug_groups.items():
        arts_in = [g["slug"] for g in group if g["type"] == "quantum_article" and g["status"] == "publish"]
        if len(arts_in) >= 2:
            for sl in arts_in:
                twin_articles[sl] = [x for x in arts_in if x != sl]

    results = [check_article(a, db) for a in db["articles"]]

    # لینک‌های ورودی (فقط از مقالات «منتشرشده» به مقصدهای منتشرشده)
    inbound = Counter()
    live_slugs = {a["slug"]: a["status"] for a in results}
    for a in results:
        if a["status"] != "publish":
            continue
        for it in a["issues"]:
            pass
        for m in A_RE.finditer(db["articles"][[x["post_id"] for x in db["articles"]].index(a["post_id"])]["content"] if False else ""):
            pass
    # محاسبهٔ ورودی از روی محتوای خام (دقیق‌تر)
    for rec in db["articles"]:
        if rec["status"] != "publish":
            continue
        for m in A_RE.finditer(rec["content"] or ""):
            href = (get_attrs(m.group(0)).get("href") or "").strip()
            if not href or not is_internal_url(href) or "wp-content/uploads" in href:
                continue
            path = re.sub(r"^[a-z]+://[^/]+", "", href, flags=re.I)
            path = path.split("?")[0].split("#")[0].strip("/")
            if not path:
                continue
            seg = path.split("/")
            cand = seg[-1] if len(seg) > 1 else seg[0]
            for sl in (seg[0], cand):
                if sl in live_slugs and sl != (rec["post_name"]):
                    inbound[sl] += 1
                    break

    for a in results:
        a["inbound"] = inbound.get(a["slug"], 0)
        if a["status"] == "publish" and a["inbound"] == 0:
            add_issue(a["issues"], {"post_id": a["post_id"], "post_name": a["slug"], "link": a["url"], "status": a["status"]}, "X05", "گراف لینک داخلی", "هیچ لینک ورودی از مقالات منتشرشدهٔ دیگر")

    # خوشه‌های محتوای تکراری
    by_hash = defaultdict(list)
    for a in results:
        if a["words"] >= 50:
            by_hash[a["content_hash"]].append(a)
    dup_slugs = set()
    dup_clusters = []
    for h, group in by_hash.items():
        if len(group) > 1:
            pubs = [g for g in group if g["status"] == "publish"]
            dup_clusters.append([{"slug": g["slug"], "status": g["status"], "title": g["title"]} for g in group])
            if pubs:
                dup_slugs.update(g["slug"] for g in pubs)
    for a in results:
        if a["slug"] in dup_slugs:
            add_issue(a["issues"], {"post_id": a["post_id"], "post_name": a["slug"], "link": a["url"], "status": a["status"]}, "X01", "بدنه", "محتوای یکسان با صفحهٔ دیگر (منتشرشده)")

    # عناوین و متاهای تکراری
    title_counter = Counter(norm_fa(a["title"]) for a in results if a["status"] == "publish" and a["title"])
    meta_counter = Counter(norm_fa(a["meta_desc"]) for a in results if a["status"] == "publish" and a["meta_desc"])
    for a in results:
        if a["status"] == "publish" and title_counter[norm_fa(a["title"])] > 1:
            add_issue(a["issues"], {"post_id": a["post_id"], "post_name": a["slug"], "link": a["url"], "status": a["status"]}, "T04", "post_title", a["title"][:90])
        if a["status"] == "publish" and a["meta_desc"] and meta_counter[norm_fa(a["meta_desc"])] > 1:
            add_issue(a["issues"], {"post_id": a["post_id"], "post_name": a["slug"], "link": a["url"], "status": a["status"]}, "M04", "meta description", a["meta_desc"][:90])
        twins = twin_articles.get(a["slug"])
        if twins:
            add_issue(a["issues"], {"post_id": a["post_id"], "post_name": a["slug"], "link": a["url"], "status": a["status"]}, "X07", "post_name",
                      f"دو نسخهٔ منتشرشده از یک موضوع: /{a['slug']}/ و /{twins[0]}/")

    # امتیاز
    for a in results:
        a["score"] = score_article(a, inbound=a["inbound"], dup=(a["slug"] in dup_slugs))
        sev_rank = {"high": 5, "med": 3, "low": 1}
        a["issue_weight"] = sum(sev_rank[i["severity"]] for i in a["issues"])
        a["fix_priority"] = round((100 - a["score"]["total"]) * (1 + a["issue_weight"] / 20) * (1.0 if a["status"] == "publish" else 0.35), 1)

    results.sort(key=lambda a: (-a["fix_priority"]))

    # ---------------------------------------------------------------- خروجی‌ها
    flat_issues = []
    for a in results:
        for i in a["issues"]:
            flat_issues.append(i)

    with open(os.path.join(out_dir, "issues.csv"), "w", newline="", encoding="utf-8-sig") as f:
        w = csv.DictWriter(f, fieldnames=list(flat_issues[0].keys()))
        w.writeheader()
        w.writerows(flat_issues)

    cols = ["post_id", "slug", "url", "status", "title", "title_len", "meta_len", "focus_kw",
            "words", "sentences", "paragraphs", "reading_time_min", "h2_count", "h3_count",
            "has_sources_head", "has_faq", "has_summary", "has_misconception", "has_lead",
            "img_total", "img_missing_alt", "img_empty_alt", "img_bad_name",
            "featured_set", "featured_alt", "featured_w", "featured_h",
            "internal_unique", "external_unique", "broken_n", "draft_targets_n", "authority_hits",
            "inbound", "schema", "pubDate", "post_date", "post_modified",
            "score_A", "score_B", "score_C", "score_D", "score_E", "score_F", "score_G",
            "score_total", "grade", "issue_n", "issue_high", "issue_weight", "fix_priority"]
    with open(os.path.join(out_dir, "articles.csv"), "w", newline="", encoding="utf-8-sig") as f:
        w = csv.writer(f)
        w.writerow(cols)
        for a in results:
            sc = a["score"]
            sev_rank = {"high": 5, "med": 3, "low": 1}
            hi = sum(1 for i in a["issues"] if i["severity"] == "high")
            w.writerow([
                a["post_id"], a["slug"], a["url"], a["status"], a["title"], a["title_len"], a["meta_len"],
                a["focus_kw"], a["words"], a["sentences"], a["paragraphs"], a["reading_time_min"],
                a["h2_count"], a["h3_count"], a["has_sources_head"], a["has_faq"], a["has_summary"],
                a["has_misconception"], a["has_lead"], a["img"]["total"], a["img"]["missing_alt"],
                a["img"]["empty_alt"], a["img"]["bad_name"], a["featured"]["set"], bool(a["featured"]["alt"]),
                a["featured"]["width"], a["featured"]["height"], a["links"]["internal_unique"],
                a["links"]["external_unique"], len(a["links"]["broken"]), len(a["links"]["draft_targets"]),
                a["links"]["authority_hits"], a["inbound"], a["schema"], a["pubDate"], a["post_date"],
                a["post_modified"], sc["A"], sc["B"], sc["C"], sc["D"], sc["E"], sc["F"], sc["G"],
                sc["total"], sc["grade"], len(a["issues"]), hi, a["issue_weight"], a["fix_priority"],
            ])

    summary = {
        "files": {"main": WXR_MAIN, "sci": WXR_SCI, "old": WXR_OLD},
        "similar_slug_groups": similar_slug_groups,
        "counts": {
            "articles_total": len(results),
            "articles_publish": sum(1 for a in results if a["status"] == "publish"),
            "articles_draft": sum(1 for a in results if a["status"] == "draft"),
            "articles_trash": sum(1 for a in results if a["status"] == "trash"),
            "scientists": len(db["scientists"]),
            "attachments": len({v["id"] for k, v in db["attach"].items() if k.isdigit()}),
            "registry_slugs": len(db["registry"]),
            "removed_since_0914": [{"slug": r["post_name"], "status_then": r["status"], "title": r["title"]} for r in db["removed_old"]],
        },
        "grades": Counter(a["score"]["grade"] for a in results),
        "grades_publish": Counter(a["score"]["grade"] for a in results if a["status"] == "publish"),
        "issue_freq": Counter(i["code"] for i in flat_issues),
        "issue_by_sev": Counter(i["severity"] for i in flat_issues),
        "dup_clusters": dup_clusters,
        "rubric": [(r[0], r[1], r[2]) for r in RUBRIC],
        "thresholds": {"green": ">=80", "yellow": "55-79", "red": "<55"},
        "registry_live": db["registry"],
    }

    with open(os.path.join(out_dir, "articles.json"), "w", encoding="utf-8") as f:
        json.dump(results, f, ensure_ascii=False, indent=1)
    with open(os.path.join(out_dir, "summary.json"), "w", encoding="utf-8") as f:
        json.dump(summary, f, ensure_ascii=False, indent=1, default=lambda o: dict(o))

    # چاپ خلاصه
    pubs = [a for a in results if a["status"] == "publish"]
    print("=== OVERALL ===")
    print(json.dumps(summary["counts"], ensure_ascii=False, indent=1))
    print("grades(all):", dict(summary["grades"]))
    print("grades(publish):", dict(summary["grades_publish"]))
    if pubs:
        scores = [a["score"]["total"] for a in pubs]
        print(f"publish score: min={min(scores)} max={max(scores)} avg={statistics.mean(scores):.1f} median={statistics.median(scores)}")
        words = [a["words"] for a in pubs]
        print(f"publish words: total={sum(words)} avg={statistics.mean(words):.0f} median={statistics.median(words)}")
        print("total words incl drafts:", sum(a["words"] for a in results))
    print("issue count:", len(flat_issues), dict(summary["issue_by_sev"]))
    print("top issues:", summary["issue_freq"].most_common(15))
    print("broken links:", sum(len(a['links']['broken']) for a in results),
          "| draft targets:", sum(len(a['links']['draft_targets']) for a in results))
    print("orphans:", sum(1 for a in results if a['status'] == 'publish' and a['inbound'] == 0))
    print("dup clusters:", len(dup_clusters))
    return results, summary


if __name__ == "__main__":
    root = sys.argv[1] if len(sys.argv) > 1 else "."
    run(root)
