# فیکس فوری v6.2 — max-plank typo → max-planck PUBLISH

**مشکل گزارش شده:** 
- https://qpedia.ir/scientists/max-plank/ باز نمیشود / غلط املایی دارد
- https://qpedia.ir/scientists/max-planck/ فارسی باز نمیشود

**علت:**
1. اسلاگ صحیح طبق Nobel Prize و Britannica `max-planck` با c است، نه `max-plank` بدون c
2. پست قدیمی با اسلاگ `max-plank` هنوز Publish بود و پست جدید `max-planck` Draft بود → هر دو 404 / تداخل
3. افزونه 301 v1.0 نصب نشده بود

**راه‌حل v6.2:**
- افزونه `qp-scientist-importer-max-planck.zip` v6.2:
  - دکمه 1) Cleanup: پیدا کردن پست `max-plank` و Trash کردن
  - دکمه 3) FIX NOW: Trash `max-plank` + Publish کردن `max-planck` FA و `max-planck-en` EN
  - خودش 301 داخلی دارد: هر درخواست `/scientists/max-plank/` → 301 → `/scientists/max-planck/`
  - status = publish (نه draft)

- افزونه `qp-scientist-301-redirects.zip` v1.1:
  - لیست کامل 26 ریدایرکت دقیق + wildcard
  - پشتیبانی `/en/scientists/max-plank/` → `/en/scientists/max-planck/`
  - `wp_insert_post_data` filter که اگر کسی دوباره `max-plank` بسازد، خودکار به `max-planck` تبدیل می‌کند
  - باید همیشه فعال بماند

**مراحل نصب برای ادمین:**
1. نصب `qp-scientist-301-redirects.zip` v1.1 → فعال
2. نصب `qp-scientist-importer-max-planck.zip` v6.2 → فعال → Tools → QP Scientist Max Planck FIX → دکمه 3) FIX NOW
3. LiteSpeed Cache → Purge All
4. تست:
   - https://qpedia.ir/scientists/max-planck/ → باید 200 و فارسی باز شود
   - https://qpedia.ir/scientists/max-plank/ → باید 301 به max-planck شود
   - https://qpedia.ir/en/scientists/max-planck/ → EN

**نتیجه:** ✅ FIX v6.2 آماده — Publish + Trash + 301
