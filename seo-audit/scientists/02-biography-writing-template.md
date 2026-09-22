# قالب آماده مقاله‌نویسی بیوگرافی دانشمندان کوانتوم — Qpedia

> این قالب را کپی کن، جاهای {{}} را پر کن، و طبق چک‌لیست سئو بسنج. هر مقاله فارسی ۲۰۰۰+ کلمه، انگلیسی ۱۸۰۰+ کلمه.

---

## اطلاعات پایه (برای JSON و Schema)

```json
{
  "slug_fa": "max-planck",
  "slug_en": "max-planck",
  "url_fa": "https://qpedia.ir/scientists/max-planck/",
  "url_en": "https://qpedia.ir/en/scientists/max-planck/",
  "title_fa": "ماکس پلانک (Max Planck) — پدر کوانتوم که خودش به کوانتوم باور نداشت",
  "title_en": "Max Planck — The Reluctant Father of Quantum Theory",
  "seo_title_fa": "زندگی‌نامه ماکس پلانک — از فاجعه فرابنفش تا ثابت پلانک",
  "seo_title_en": "Max Planck Biography — From Blackbody to Planck Constant",
  "meta_fa": "ماکس پلانک 1900 با حل فاجعه فرابنفش ثابت h را معرفی کرد، نوبل 1918 را برد، شاگردانش هایزنبرگ و پاولی بودند. تولد 1858 کیل، وفات 1947 گوتینگن.",
  "meta_en": "Max Planck introduced h in 1900 to solve blackbody radiation, won Nobel 1918, mentored Heisenberg. Born 1858 Kiel, died 1947 Göttingen. Full biography with sources.",
  "focus_kw_fa": "زندگی‌نامه ماکس پلانک",
  "focus_kw_en": "Max Planck biography",
  "featured_image": "max-planck.webp",
  "featured_alt_fa": "پرتره ماکس پلانک 1918 برنده نوبل فیزیک و پدر نظریه کوانتوم",
  "featured_alt_en": "Portrait of Max Planck 1918 Nobel Prize in Physics father of quantum theory",
  "birth_date": "1858-04-23",
  "death_date": "1947-10-04",
  "birth_place": "Kiel, Germany",
  "nationality": "German",
  "education_country": "Germany",
  "education": ["University of Munich", "University of Berlin"],
  "awards": [{"year":1918, "award":"Nobel Prize in Physics", "reason":"discovery of energy quanta"}],
  "religion": "Lutheran (with documented letters supporting science over dogma)",
  "sameAs": ["https://en.wikipedia.org/wiki/Max_Planck", "https://www.wikidata.org/wiki/Q9021", "https://www.nobelprize.org/prizes/physics/1918/planck/biographical/"]
}
```

## ساختار HTML مقاله فارسی (الگو)

```html
<!-- H1 فقط یک بار -->
<h1>ماکس پلانک (Max Planck) — پدر کوانتومی که خودش به کوانتوم باور نداشت</h1>

<!-- لید 180 کلمه شامل کلیدواژه -->
<p><strong>نکات کلیدی:</strong> زندگی‌نامه ماکس پلانک ...</p>
<p>ماکس پلانک در 14 دسامبر 1900 در انجمن فیزیک آلمان گفت انرژی گسسته است... <a href="https://qpedia.ir/what-is-quantum/">کوانتوم یعنی چه</a></p>

<!-- Quick Facts Box -->
<div class="qpedia-infobox">
<table>
<tr><th>تولد</th><td>23 آوریل 1858 — کیل، آلمان [Britannica]</td></tr>
<tr><th>وفات</th><td>4 اکتبر 1947 — گوتینگن، آلمان [Nobel]</td></tr>
<tr><th>کشور تحصیل</th><td>آلمان — مونیخ، برلین</td></tr>
<tr><th>معروف به</th><td>ثابت پلانک، قانون تابش جسم سیاه</td></tr>
<tr><th>جایزه</th><td>نوبل فیزیک 1918 [Nobel 1918]</td></tr>
<tr><th>عقیده</th><td>لوتری، مدافع علم در برابر ایدئولوژی — نامه 1933 [Heilbron]</td></tr>
</table>
</div>

<h2>دوران کودکی و خانواده</h2>
<p>... با منبع [Encyclopedia Britannica]</p>

<h2>تحصیلات — از مونیخ تا برلین</h2>
<p>... [University of Munich archives]</p>

<h2>مسیر شغلی</h2>
<p>...</p>

<h2>دستاوردهای علمی بزرگ</h2>
<h3>1. حل فاجعه فرابنفش و تولد کوانتوم — 1900</h3>
<p>پلانک برای حل واگرایی ریلی-جینز فرض کرد E=nhν... [Planck 1901, Annalen der Physik] — این مقاله [DOI:10.1002/andp.19013090310] امروز هم مرجع است.</p>
<!-- منبع فوری بعد نظریه -->

<h3>2. ثابت پلانک h — کوچک‌ترین کنش جهان</h3>
<p>h = 6.62607015×10⁻³⁴ J·s ... [CODATA 2019]</p>

<h2>جوایز و افتخارات — تاریخ دقیق</h2>
<table><tr><th>سال</th><th>جایزه</th><th>دلیل</th></tr><tr><td>1918</td><td>نوبل فیزیک</td><td>کشف کوانتوم انرژی [Nobel 1918]</td></tr></table>

<h2>زندگی شخصی، عقاید دینی و اخلاقی</h2>
<p>پلانک لوتری بود اما... [Heilbron 2000] ...</p>
<p><strong>به چه اخلاقی معروف بود؟</strong> درستکاری، حمایت از همکاران یهودی در 1933...</p>
<p><strong>مشکلات:</strong> از دست دادن 4 فرزند، اعدام پسرش اروین در 1945 توسط نازی‌ها [Nobel biography]</p>

<h2>داستان‌ها — با هشدار سند</h2>
<p>می‌گویند روزی پلانک در کلاس گفت «فیزیک تمام شده» و بعد خودش آن را زیرورو کرد — <em>این روایت در خاطرات شاگردان آمده اما سند رسمی سخنرانی ندارد، با این حال بارها نقل شده.</em></p>

<h2>کادرهای هایلایت</h2>
<blockquote class="qpedia-quote">«یک حقیقت علمی جدید با متقاعد کردن مخالفان پیروز نمی‌شود، بلکه با مرگ آن‌ها و رشد نسلی جدید که با آن آشناست.» — ماکس پلانک، 1949 [Scientific Autobiography]</blockquote>

<div class="qpedia-newspaper">📰 New York Times — 1919: «Light bends, Einstein confirmed, Planck's quantum lays groundwork» — تصویر روزنامه</div>

<div class="qpedia-media">🔬 Nature — 1901: گزارش قانون تابش پلانک — [Nature 1901]</div>

<h2>میراث و تاثیر بر فناوری امروز</h2>
<p>بدون h، ترانزیستور، لیزر، MRI ممکن نبود...</p>

<h2>جدول زمانی دقیق</h2>
<table><tr><td>1858-04-23</td><td>تولد در کیل [Britannica]</td></tr>...</table>

<h2>سوءبرداشت‌های رایج</h2>
<p><strong>سوءبرداشت 1:</strong> پلانک عاشق کوانتوم بود — نه، تا 10 سال مقاومت کرد [Kuhn 1978]</p>

<h2>جمع‌بندی</h2>
<p>...</p>

<h2>پرسش‌های متداول</h2>
<p><strong>ماکس پلانک کیست؟</strong><br>...</p>

<h2>منابع</h2>
<ol class="qpedia-scientific-sources">
<li><a href="https://www.nobelprize.org/prizes/physics/1918/planck/biographical/" target="_blank">Nobel Prize — Max Planck biographical 1918</a></li>
<li><a href="https://doi.org/10.1002/andp.19013090310" target="_blank">Planck 1901 Annalen der Physik</a></li>
<li><a href="https://www.britannica.com/biography/Max-Planck" target="_blank">Britannica — Max Planck</a></li>
<li><a href="https://plato.stanford.edu/entries/planck/" target="_blank">Stanford Encyclopedia — Planck</a></li>
</ol>

<h2>پیوندهای داخلی پیشنهادی</h2>
<p>مقالات: <a href="https://qpedia.ir/what-is-quantum/">کوانتوم چیست</a>، <a href="https://qpedia.ir/ultraviolet-catastrophe/">فاجعه فرابنفش</a>، <a href="https://qpedia.ir/planck-constant/">ثابت پلانک</a>، <a href="https://qpedia.ir/blackbody-radiation/">تابش جسم سیاه</a>، <a href="https://qpedia.ir/quantum-history/">تاریخ کوانتوم</a>، <a href="https://qpedia.ir/scientists/niels-bohr/">نیلز بور</a></p>
```

## Schema پیشنهادی (JSON-LD)

```json
{
  "@context": "https://schema.org",
  "@graph": [
    {
      "@type": "Person",
      "@id": "https://qpedia.ir/scientists/max-planck/#person",
      "name": "Max Planck",
      "alternateName": "Max Karl Ernst Ludwig Planck",
      "birthDate": "1858-04-23",
      "deathDate": "1947-10-04",
      "birthPlace": {"@type":"Place","name":"Kiel, Germany"},
      "nationality": "German",
      "alumniOf": [{"@type":"CollegeOrUniversity","name":"University of Munich"},{"@type":"CollegeOrUniversity","name":"University of Berlin"}],
      "award": "Nobel Prize in Physics 1918",
      "knowsAbout": ["Quantum theory","Blackbody radiation","Planck constant"],
      "sameAs": ["https://en.wikipedia.org/wiki/Max_Planck","https://www.wikidata.org/wiki/Q9021","https://www.nobelprize.org/prizes/physics/1918/planck/biographical/"],
      "image": "https://qpedia.ir/wp-content/uploads/max-planck.webp",
      "description": "German physicist, father of quantum theory"
    },
    {
      "@type": "Article",
      "headline": "زندگی‌نامه ماکس پلانک — از فاجعه فرابنفش تا ثابت پلانک",
      "inLanguage": "fa-IR",
      "about": {"@id":"https://qpedia.ir/scientists/max-planck/#person"},
      "author": {"@type":"Organization","name":"Qpedia"},
      "isPartOf": {"@type":"WebSite","name":"Qpedia"}
    },
    {
      "@type": "FAQPage",
      "mainEntity": [...]
    },
    {
      "@type": "BreadcrumbList",
      "itemListElement": [...]
    }
  ]
}
```

## چک‌لیست نهایی قبل از انتشار (برای هر دانشمند)

- [ ] H1 فقط نام، شامل فارسی+انگلیسی
- [ ] URL: /scientists/slug/ بدون عدد، canonical خود
- [ ] seo_title 50-60 کاراکتر شامل کلیدواژه
- [ ] meta 120-160 شامل کلیدواژه + سال + دستاورد
- [ ] کلیدواژه در 100 کلمه اول، H2 اول، alt تصویر
- [ ] 2000+ کلمه فارسی (شمارش واقعی)
- [ ] 6+ لینک داخلی (3 به مقالات، 3 به دانشمندان)
- [ ] 4+ لینک خارجی معتبر با متن کوتاه لینک‌دار (نه فقط انتها)
- [ ] هر نظریه: منبع فوری بعد جمله [Nobel] [DOI]
- [ ] کادر نقل قول + روزنامه + رسانه
- [ ] بخش عقاید دینی/اتئیست با سند
- [ ] بخش اخلاقی/مشکلات با سند، بدون حدس
- [ ] بخش داستانی با برچسب «سند قطعی ندارد»
- [ ] FAQ 5-6 سوال با schema
- [ ] Timeline جدول
- [ ] تصویر webp با alt شامل کلیدواژه
- [ ] Person schema کامل
- [ ] hreflang fa/en + x-default
- [ ] نسخه انگلیسی 1800+ کلمه، ترجمه انسانی، meta متفاوت
- [ ] تست یونیک بودن >90%
- [ ] تست صحت تاریخ‌ها با 2 منبع (Britannica + Nobel)

---

**بعد از تایید این قالب، اولین مقاله: max-planck را می‌نویسم — با تحقیق کامل، 2000+ کلمه فارسی، 1800+ انگلیسی، تمام منابع inline، و افزونه ایمپورتر.**
