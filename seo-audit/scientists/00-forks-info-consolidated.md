# بررسی ۴ فورک جدید گیت‌هاب و ثبت اطلاعات مورد نیاز برای مرحله دانشمندان

تاریخ بررسی: 2026-09-21 18:30 UTC
ریپوهای بررسی شده (از lakanzino):

1. awesome-ai-scientists (fork از natnew/awesome-ai-scientists) — آخرین آپدیت 2026-09-21T18:26:58Z
2. bio-datasets (fork از bioml-tools/bio-datasets)
3. history-of-science (fork از emmjab/history-of-science)
4. Portfolio (fork از wguesdon/Portfolio)

## یافته‌ها

### 1) awesome-ai-scientists
- چیست: مجموعه منابع برای ساخت سیستم‌های AI Scientist — کمک به کشف علمی از طریق هوش ادبیات، تولید فرضیه، طراحی آزمایش، استفاده از ابزار، ارزیابی و ارتباط علمی
- محتوای مفید برای ما:
  - بخش Literature Intelligence & Knowledge Extraction: ابزارهای PaperQA2, OpenScholar (45M مقاله), OpenAlex (گراف دانش باز صدها میلیون اثر), Semantic Scholar API (200M+ مقاله)
  - Knowledge Graphs: OpenAlex, Semantic Scholar, S2ORC, ORKG, scite.ai, Connected Papers
  - Scientific Language Models: Galactica 120B, SciBERT, BioGPT
  - Benchmarks: ScienceAgentBench 102 task, GPQA, SciFact
- کاربرد در پروژه دانشمندان Qpedia:
  - برای صحت‌سنجی علمی: استفاده از OpenAlex + Semantic Scholar برای استخراج تاریخ دقیق تولد/وفات، جوایز، DOI مقالات اصلی
  - برای یونیک بودن: استفاده از scite.ai برای بررسی ادعاها و جلوگیری از کپی
  - برای منابع: هر نظریه علمی لینک DOI از S2ORC/OpenAlex

### 2) bio-datasets
- چیست: آوردن داده‌های زیستی (مولکول و بیشتر) به Hugging Face Datasets
- محتوای مفید:
  - ساختار استاندارد dataset با pyproject.toml، src، tests — الگوی خوب برای ساخت دیتاست بیوگرافی دانشمندان
  - مثال‌هایی از نحوه لود داده‌های زیستی که می‌توان برای تاریخ علم استفاده کرد
- کاربرد:
  - ایده ساخت یک دیتاست Hugging Face برای دانشمندان کوانتوم با فیلدهای: name, birthDate, deathDate, birthPlace, nationality, educationCountry, religion, awards[], knownFor[], sameAs[], ethicalIssues, personalTragedies
  - این دیتاست می‌تواند منبع single source of truth برای تولید مقالات فارسی و انگلیسی باشد و از حدس جلوگیری کند

### 3) history-of-science
- چیست: ویژوال‌سازی انتشارات مجلات علمی در طول زمان — اسکرپ لیست مجلات ویکی‌پدیا، جمع‌آوری ساختار داده، ویژوال‌سازی
- فایل‌ها: categories.txt (دسته‌بندی علوم: General, Physics, Astronomy, Chemistry...), journal_db_clean.csv, scrape_wiki_journals.py
- کاربرد:
  - برای بخش Timeline دانشمندان: استفاده از ایده اسکرپ ویکی‌پدیا برای استخراج تاریخ دقیق رویدادها
  - برای بخش میراث: نشان دادن رشد انتشارات بعد از کار دانشمند (مثلا بعد از 1900 Planck انتشارات کوانتوم انفجاری شد)
  - الگوی کد پایتون برای اسکرپ ویکی‌پدیا می‌تواند برای استخراج خودکار اطلاعات دانشمندان استفاده شود

### 4) Portfolio
- چیست: مجموعه پروژه‌های شخصی داده‌کاوی و زیست‌محاسباتی — شامل Bioinformatics, Data_Science, Publications
- کاربرد:
  - الگوی ارائه رزومه و بیوگرافی حرفه‌ای — می‌توان از ساختار Portfolio برای صفحه دانشمندان الهام گرفت (بخش Publications, Certificates)
  - بخش Publications می‌تواند برای لیست مقالات کلیدی دانشمند (مثلا Planck 1901 Annalen) استفاده شود

## نسخه یکپارچه ثبت اطلاعات برای استفاده در مرحله دانشمندان

برای جلوگیری از حدس و تضمین صحت، یک فایل JSON واحد می‌سازیم: `seo-audit/scientists/scientists-master-data.json`

ساختار پیشنهادی (بر اساس ترکیب ۴ فورک + نیاز Qpedia):

```json
{
  "scientists": [
    {
      "slug": "max-planck",
      "en_name": "Max Planck",
      "fa_name": "ماکس پلانک",
      "full_name": "Max Karl Ernst Ludwig Planck",
      "birth_date": "1858-04-23",
      "death_date": "1947-10-04",
      "birth_place": "Kiel, Duchy of Holstein",
      "birth_country": "Germany",
      "death_place": "Göttingen, Germany",
      "nationality": "German",
      "education": [
        {"institution": "University of Munich", "country": "Germany", "years": "1874-1877"},
        {"institution": "University of Berlin", "country": "Germany", "years": "1877-1878"}
      ],
      "education_country": "Germany",
      "known_for": ["Planck constant", "Planck's law", "Quantum theory"],
      "awards": [
        {"year": 1918, "name": "Nobel Prize in Physics", "reason": "discovery of energy quanta", "source": "https://www.nobelprize.org/prizes/physics/1918/planck/biographical/"},
        {"year": 1927, "name": "Lorentz Medal"},
        {"year": 1929, "name": "Copley Medal"}
      ],
      "religion": {
        "raised": "Lutheran",
        "role": "church elder",
        "later_view": "deist-like, deeply religious but not personal Christian God",
        "sources": ["https://www.archiv-berlin.mpg.de/201046/Max-Planck-und-ein-zweifelhaftes-Zitat", "https://en.wikiquote.org/wiki/Max_Planck"]
      },
      "personal": {
        "spouses": ["Marie Merck (1887-1909)", "Marga von Hoesslin (1911-1948)"],
        "children": ["Karl 1888-1916 killed WWI", "Emma 1889-1919 died childbirth", "Grete 1889-1917 died childbirth", "Erwin 1893-1945 executed by Gestapo", "Hermann 1911-1954"],
        "tragedies": ["wife died 1909", "son Karl killed Verdun 1916", "daughters died childbirth 1917/1919", "son Erwin executed 1945", "house destroyed 1944"],
        "character": ["incorruptible", "conservative", "idealistic", "reliable", "generous", "music lover"],
        "sources": ["https://www.wikidoc.org/index.php/Max_Planck", "https://historydraft.com/story/max-planck/erwin-his-son-was-hanged/304/1909"]
      },
      "ethics": {
        "nazi_period": "remained in Germany, met Hitler spring 1933 to plead for Jewish colleagues, failed, resigned 1937, criticized for not protesting louder but helped individuals",
        "sources": ["https://www.cantorsparadise.com/the-personal-tragedies-of-max-planck-490ef4246ee5", "https://www.ebsco.com/research-starters/history/max-planck"]
      },
      "scientific": {
        "theories": [
          {"name": "Blackbody radiation law", "year": 1900, "formula": "u(ν,T) = (8πhν³/c³)/(exp(hν/kT)-1)", "source": "https://doi.org/10.1002/andp.19013090310"},
          {"name": "Planck constant h", "value": "6.62607015e-34 J·s", "source": "https://physics.nist.gov/cuu/Constants/"}
        ]
      },
      "sameAs": ["https://en.wikipedia.org/wiki/Max_Planck", "https://www.wikidata.org/wiki/Q9021", "https://www.nobelprize.org/prizes/physics/1918/planck/biographical/"],
      "image": "max-planck.webp",
      "old_slugs": ["max-plank", "max-plank/"],
      "redirects": [["max-plank", "max-planck"]]
    }
  ]
}
```

این ساختار از ایده‌های ۴ فورک گرفته شده:
- از awesome-ai-scientists: استفاده از OpenAlex/Semantic Scholar برای sameAs و DOI
- از bio-datasets: ساختار Hugging Face dataset
- از history-of-science: اسکرپ تاریخ و دسته‌بندی
- از Portfolio: بخش Publications و Awards

## پیشنهاد workflow جدید (انگلیسی اول)

1. برای هر دانشمند، ابتدا داده‌ها را از Nobel + Britannica + Wikipedia + Wikidata + OpenAlex استخراج و در scientists-master-data.json ثبت کن (بدون حدس)
2. سپس مقاله انگلیسی کامل (1800+ کلمه) با لحن دوستانه، باهوش، مهربان بنویس — کاملا یونیک، با منابع inline [Nobel 1918] [Britannica] [DOI]
3. انگلیسی را تست یونیک بودن و صحت علمی کن (با ابزارهای فورک awesome-ai-scientists)
4. سپس ترجمه فارسی نه ماشینی، بلکه بازنویسی انسانی با همان طرح و منابع، با لحن داستان‌گویی فارسی، 2000+ کلمه
5. هر دو نسخه را با hreflang به هم وصل کن، Person @id مشترک
6. افزونه ایمپورتر برای هر دانشمند بساز که post_content، rank_math_title، rank_math_description، rank_math_focus_keyword، _thumbnail_id alt، و schema را آپدیت کند

اولین نمونه: max-planck.en.html و max-planck.fa.html در پوشه fixed-articles آماده است — منتظر تایید.
