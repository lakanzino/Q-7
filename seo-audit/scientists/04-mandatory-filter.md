# فیلتر اجباری مقالات دانشمندان — سند رسمی Qpedia

تاریخ: 2026-09-21
وضعیت: از این پس هر مقاله (فارسی و انگلیسی) باید از این فیلتر عبور کند و تاییدیه تک‌تک موارد توضیح داده شود.

## هدف
هر مقاله دانشمند باید 100% امتیاز Qpedia Analyzer بگیرد و در Google Rich Results Test بدون خطا باشد. هیچ موردی قابل حدس نیست — فقط با مدرک.

---

## فیلتر ۲۰ مرحله‌ای (Mandatory Filter)

### 1) سئو عنوان (SEO Title)
- طول ۵۰-۶۰ کاراکتر (با احتساب فاصله)
- شامل کلیدواژه کانونی دقیق
- شامل یک دستاورد یا سال برای جذابیت
- مثال FA: «زندگی‌نامه ماکس پلانک — از فاجعه فرابنفش تا ثابت پلانک» (۵۴)
- مثال EN: «Max Planck Biography — From Blackbody to Planck Constant» (۵۲)

### 2) متا توضیحات (Meta Description)
- طول ۱۲۰-۱۶۰ کاراکتر
- شامل کلیدواژه + سال تولد/وفات + یک دستاورد + یک تراژدی یا ویژگی اخلاقی
- یکتا، نه تکراری
- مثال: «ماکس پلانک 1900 با حل فاجعه فرابنفش ثابت h را معرفی کرد، نوبل 1918 را برد...»

### 3) کلیدواژه کانونی
- در ۱۰۰ کلمه اول لید
- در H1
- در H2 اول
- در alt تصویر شاخص
- در URL (اسلاگ)
- در حداقل ۲ سوال FAQ
- در نام فایل تصویر

### 4) طول محتوا
- فارسی: ۲۰۰۰+ کلمه واقعی (شمارش با regex `[A-Za-z؀-ۿ0-9]+`)
- انگلیسی: ۱۸۰۰+ کلمه واقعی
- تست: `python3 -c "import re; print(len(re.findall(...)))"`

### 5) هدینگ‌های جذاب (نه خشک)
- H1 فقط یک عدد: نام فارسی + انگلیسی پرانتز + یک قلاب
- H2: ۱۰-۱۴ عدد، هر کدام تیتر جذاب داستانی (نه «کادرهای هایلایت»)
  - ❌ بد: «کادرهای هایلایت»
  - ✅ خوب: «در یک نگاه: جمله‌ای که از پلانک ماند»، «روزنامه‌های ۱۹۰۰ چه نوشتند؟»، «بازتاب امروز در Nature و BBC»
  - ❌ بد: «دوران کودکی و خانواده»
  - ✅ خوب: «کودکی در کیل: خانه‌ای که قانون و کلیسا در آن حکومت می‌کرد»
  - ❌ بد: «تحصیلات»
  - ✅ خوب: «از مونیخ تا برلین: سال‌هایی که استادان بزرگ ناامیدش کردند»
  - ❌ بد: «دستاوردهای علمی»
  - ✅ خوب: «سه ضربه‌ای که فیزیک را تکان داد»
  - ❌ بد: «زندگی شخصی»
  - ✅ خوب: «پشت معادله‌ها: عشق، ایمان، و از دست دادن‌های پیاپی»
  - ❌ بد: «داستان‌ها»
  - ✅ خوب: «روایت‌هایی که می‌گویند — با مهر «سند قطعی ندارد»»
  - ❌ بد: «سوءبرداشت‌ها»
  - ✅ خوب: «چهار سوءتفاهمی که درباره پلانک تکرار می‌شود»
  - ❌ بد: «جدول زمانی»
  - ✅ خوب: «خط زمان دقیق: از کیل تا گوتینگن، قدم‌به‌قدم»
- H3 برای هر نظریه/جایزه/اتفاق شخصی
- بدون پرش H2→H4

### 6) تصویر شاخص
- فرمت webp، حجم <120KB
- نام فایل = اسلاگ: `max-planck.webp`
- alt فارسی: شامل کلیدواژه + سال + دستاورد + نوبل
- alt انگلیسی: شامل keyword + year + achievement
- عرض حداقل ۶۰۰px

### 7) لینک داخلی (Internal Links)
- حداقل ۶ عدد یکتا
- ۳ به مقالات Qpedia (مثلا what-is-quantum, ultraviolet-catastrophe, planck-constant)
- ۳ به دانشمندان دیگر (albert-einstein, niels-bohr, werner-heisenberg)
- انکر طبیعی فارسی/انگلیسی، نه «اینجا کلیک کنید»
- بدون لینک شکسته (404)

### 8) لینک خارجی (External Links) — منبع‌دهی فوری
- حداقل ۴ معتبر: Nobel Prize, Britannica, DOI (Annalen), Stanford Encyclopedia, CODATA, Max Planck Society
- **قانون Qpedia:** هر ادعای علمی بلافاصله بعدش منبع کوتاه لینک‌دار: «... E=hν [Planck 1901]» که [Planck 1901] لینک به DOI است — نه فقط لیست انتها
- rel="noopener" target="_blank"
- متن کوتاه: [Nobel 1918] [Britannica] [DOI] نه URL خام

### 9) کادرهای ویژه — با تیتر جذاب
- نقل قول: `<div class="qpedia-quote">` با تیتر H3 «در یک نگاه: جمله‌ای که از او ماند»
- روزنامه وقت: `<div class="qpedia-newspaper">` با تیتر H3 «روزنامه‌های آن زمان چه نوشتند؟»
- بازتاب رسانه امروز: `<div class="qpedia-media">` با تیتر H3 «بازتاب امروز: از Nature تا BBC»
- هر کادر منبع دقیق دارد

### 10) جدول‌ها — ریسپانسیو و تمیز
- wrapper: `<div style="overflow-x:auto;">` برای جلوگیری از شکست در موبایل
- حروف تمیز: padding 10-12px، border-collapse، font-size 15px، th با background #f3f4f6
- جدول جوایز: سال | جایزه | دلیل | منبع (لینک)
- جدول Timeline: تاریخ دقیق میلادی | رویداد | منبع
- تست در عرض 360px — نباید حروف جابجا شوند

### 11) FAQ
- ۶ سوال، هر سوال شامل کلیدواژه
- هر جواب ۴۰-۶۰ کلمه، دقیق، با منبع اگر لازم
- FAQPage schema ولید
- لحن انسانی، نه رباتیک

### 12) Schema — ۴ نوع اجباری
- Person: name, alternateName, birthDate, deathDate, birthPlace (Place), nationality, alumniOf (CollegeOrUniversity), award[], knowsAbout[], sameAs[] (Wikipedia, Wikidata, Nobel, Britannica), image, description, @id = `https://qpedia.ir/scientists/slug/#person`
- Article: headline, inLanguage (fa-IR یا en-US), about @id Person, author Organization Qpedia, mainEntityOfPage URL همان زبان
- BreadcrumbList: Home > Scientists > Name
- FAQPage: ۶ سوال
- تست: https://search.google.com/test/rich-results و https://validator.schema.org — باید 0 خطا

### 13) دوزبانه — بدون دوبل
- فارسی: `https://qpedia.ir/scientists/slug/` — lang fa-IR
- انگلیسی: `https://qpedia.ir/en/scientists/slug/` — lang en-US
- هر صفحه canonical به خودش
- هر دو دارای:
  ```html
  <link rel="alternate" hreflang="fa" href="https://qpedia.ir/scientists/slug/" />
  <link rel="alternate" hreflang="en" href="https://qpedia.ir/en/scientists/slug/" />
  <link rel="alternate" hreflang="x-default" href="https://qpedia.ir/scientists/slug/" />
  ```
- Person @id مشترک — هر دو به `https://qpedia.ir/scientists/slug/#person` ارجاع
- محتوا ترجمه انسانی با مثال‌های بومی متفاوت، نه ترجمه کلمه‌به‌کلمه ماشینی

### 14) تایپوگرافی و زیبایی
- فونت: Vazirmatn CDN، fallback Tahoma
- line-height: 2.1، font-size: 17px، max-width: 820px، margin auto، padding 32px 20px
- پاراگراف: margin-bottom 18px، text-align justify
- فاصله هدینگ‌ها: H2 margin-top 48px, margin-bottom 16px, border-bottom 2px solid #e0e0e0
- ریسپانسیو: media query برای 360px — font-size 15px، padding 16px
- تست: در موبایل جدول‌ها اسکرول افقی بخورند، نه بشکنند

### 15) بخش دین/اتئیسم — فقط با سند
- عنوان H2 جذاب: «ایمان و شک: از خادم کلیسا تا نامه ۱۹۴۷»
- هر ادعا با منبع دقیق: کتاب، نامه با تاریخ، آرشیو MPG
- بدون حدس: اگر سند نیست، ننویس

### 16) بخش اخلاق و مشکلات — مستند
- عنوان H2 جذاب: «بین وظیفه و وجدان: سال‌های ۱۹۳۳ تا ۱۹۴۵»
- شامل: موضع در برابر نازیسم، دیدار با هیتلر ۱۹۳۳، دفاع از یهودیان، اعدام پسر، ویرانی خانه
- هر مورد با منبع معتبر: Cantor's Paradise, EBSCO, Historydraft, Nobel bio
- بدون قضاوت احساسی، فقط روایت مستند

### 17) بخش داستانی — با برچسب هشدار
- عنوان H2 جذاب: «روایت‌هایی که می‌گویند — با مهر «سند قطعی ندارد»»
- قالب: داستان + `<div class="disclaimer">این روایت در ... آمده اما سند رسمی ... ندارد</div>`
- لحن مهربان، باهوش، داستان‌گو

### 18) سوءبرداشت‌ها — ۴ مورد
- عنوان H2 جذاب: «چهار سوءتفاهمی که درباره او تکرار می‌شود»
- هر مورد: سوءبرداشت + اصلاح + منبع

### 19) جمع‌بندی — ۱۲۰ کلمه
- شامل نام، تولد/وفات، ۲ دستاورد، یک ویژگی اخلاقی، میراث

### 20) تست نهایی قبل از تحویل
- [ ] wc فارسی >=2000، انگلیسی >=1800 — با اسکریپت
- [ ] seo_title 50-60، meta 120-160 — با اسکریپت
- [ ] کلیدواژه در ۶ نقطه چک
- [ ] 6+ internal، 4+ external — بدون 404
- [ ] تصویر webp <120KB، alt شامل کلیدواژه
- [ ] Schema ولید (Rich Results 0 error)
- [ ] hreflang fa/en/x-default موجود
- [ ] جدول‌ها ریسپانسیو (overflow-x:auto)
- [ ] فونت Vazirmatn لود، line-height 2.1
- [ ] یونیک بودن >90% (Copyscape / Originality)
- [ ] غلط املایی اسلاگ چک (max-planck نه max-plank)
- [ ] ریدایرکت 301 از اسلاگ‌های قدیمی در 03-redirects-301-scientists.csv ثبت

---

## فرمت تاییدیه هر مقاله

بعد از هر مقاله (FA و EN جدا) باید گزارش زیر داده شود:

```
✅ تاییدیه فیلتر اجباری — max-planck
FA:
- seo_title: 54 کاراکتر شامل کلیدواژه — تایید
- meta: 155 کاراکتر شامل کلیدواژه + سال + نوبل — تایید
- wc: 2123 کلمه — تایید
- keyword in first 100: بله — تایید
- internal: 8 لینک (4 مقاله + 4 دانشمند) — تایید، بدون 404
- external: 8 لینک (Nobel, Britannica, DOI, CODATA...) — تایید inline
- image: max-planck.webp 87KB alt شامل کلیدواژه — تایید
- H2: 12 عدد با تیتر جذاب — تایید
- FAQ: 6 سوال 45-60 کلمه — تایید + schema ولید
- Schema: Person + Article + Breadcrumb + FAQ — 0 خطا در Rich Results
- Responsive: جدول overflow-x:auto، تست 360px — تایید
- Typography: Vazirmatn, line-height 2.1, spacing 18px — تایید
- Religion: با سند نامه 1947 MPG — تایید
- Ethics: با سند دیدار هیتلر 1933 + اعدام اروین — تایید
- Story: با برچسب سند ندارد — تایید
- Unique: 96% — تایید

EN:
- seo_title: 52 chars includes keyword — pass
- meta: 148 chars includes keyword + year + Nobel — pass
- wc: 2056 words — pass
... (same 20 items)
```

بدون این تاییدیه، مقاله قابل انتشار نیست.
