# الگوی استاندارد بیوگرافی دانشمندان کوانتوم — تحقیق گوگل + افزوده‌های Qpedia

تاریخ: 2026-09-21
وضعیت: منتظر تایید شما برای شروع بازنویسی

## 1) خلاصه تحقیق از گوگل: بهترین چیدمان بیوگرافی که رتبه می‌گیرد

### منابع اصلی
- ReputationX: هر دانشمند صفحه اختصاصی با نام در URL، یک H1 با نام کامل، عکس با نام فایل فرد، بیوگرافی حداقل 500 کلمه (ترجیح 1000+)، لینک خروجی به پروفایل‌های معتبر، Person schema با sameAs [1](https://www.reputationx.com/blog/executive-bio-page)
- Schema.org Person: فیلدهای name, alternateName, birthDate, deathDate, birthPlace, nationality, alumniOf, award, knowsAbout, sameAs (Wikidata/Wikipedia), image, description, jobTitle, worksFor [2](https://www.reputationx.com/blog/person-biography-schema) [3](https://defamationdefenders.com/schema-person-markup-identity-branding/)
- Google Article & Person best practices: از Type Person برای شخص، @id پایدار، sameAs برای اثبات هویت، author.name + author.url [4](https://developers.google.com/search/docs/appearance/structured-data/article)
- ساختار استاندارد بیوگرافی: Introduction, Early Life, Education, Career, Achievements, Personal Life, Challenges, Legacy, Conclusion (chronological) [5](https://writersofthewest.net/blog/what-is-the-correct-format-for-a-biography/) [6](https://www.aimlay.com/format-of-writing-a-biography/)

### چک‌لیست گوگل برای صفحه بیوگرافی حرفه‌ای
1. URL اختصاصی: `/scientists/albert-einstein/` — نام کامل لاتین، بدون عدد
2. H1 = نام کامل (مثلا Albert Einstein / آلبرت اینشتین) — فقط یک H1
3. تصویر شاخص: `albert-einstein.webp` <120KB، alt شامل کلیدواژه
4. طول متن: حداقل 500، هدف Qpedia: **۲۰۰۰+ کلمه فارسی** (و بعد انگلیسی جدا)
5. هدینگ‌ها: H2 برای بخش‌های اصلی، H3 برای زیربخش، بدون پرش H2→H4
6. Person schema کامل + Article schema + FAQPage + BreadcrumbList
7. sameAs به Wikidata, Wikipedia, Nobel, Britannica
8. لینک داخلی ۶+ به مقالات مرتبط و دانشمندان دیگر، لینک خارجی ۴+ به منابع معتبر (DOI, Nobel, دانشگاه)
9. FAQ حداقل ۵ سوال با جواب ۴۰-۶۰ کلمه
10. منابع: هر ادعای علمی بلافاصله بعدش لینک کوتاه با نام منبع (نه فقط انتهای مقاله)

## 2) الگوی استاندارد پیشنهادی Qpedia (فارسی) — ترکیب گوگل + نیاز شما

### ساختار ۱۶ بخشی (هر بخش H2، به جز کادرها)

**H1:** نام کامل فارسی + انگلیسی پرانتز — مثلا «آلبرت اینشتین (Albert Einstein)»

**Box بالای صفحه — خلاصه سریع (Quick Facts) — جدول یا کارت:**
- نام کامل، نام دیگر، تولد (تاریخ دقیق میلادی + شمسی)، وفات، محل تولد (کشور)، ملیت، کشور محل تحصیل، رشته، معروف به، جوایز کلیدی با سال، عقاید دینی/فلسفی (فقط با منبع معتبر — مثلا Einstein: agnostic/deist با نامه 1954)، وب‌سایت/همان

**پاراگراف لید (۱۵۰-۲۰۰ کلمه):** شامل کلیدواژه کانونی در ۱۰۰ کلمه اول، خلاصه ۳ دستاورد، یک قلاب داستانی. کلیدواژه: «زندگی‌نامه آلبرت اینشتین» یا «آلبرت اینشتین کیست»

**H2-1: دوران کودکی و خانواده**
- محل تولد دقیق، خانواده، شرایط اجتماعی، اتفاق کودکی که مسیر را عوض کرد، با منبع

**H2-2: تحصیلات — کجا، چه سال‌هایی، چه کشوری**
- لیست دانشگاه‌ها با سال، کشور تحصیل، استادان مهم، با لینک sameAs دانشگاه

**H2-3: مسیر شغلی — شغل‌ها، مهاجرت‌ها، آزمایشگاه‌ها**
- کرونولوژی شغلی، با تاریخ دقیق

**H2-4: دستاوردهای علمی بزرگ (هر دستاورد H3)**
- هر H3: نام نظریه + سال + توضیح ساده + فرمول اگر لازم + **منبع فوری بعد جمله**: مثلا «اثر فوتوالکتریک 1905 که نوبل 1921 را آورد [Nobel Prize 1921]» که لینک به https://www.nobelprize.org
- حداقل ۴ منبع DOI/کتاب معتبر در این بخش

**H2-5: جوایز، افتخارات، تاریخ دقیق**
- جدول: سال | جایزه | دلیل | منبع

**H2-6: زندگی شخصی، عقاید، اخلاق، مشکلات**
- ازدواج، فرزندان، دین/اتئیسم با سند (نامه، مصاحبه)، مواضع سیاسی، مشکلات اخلاقی مستند (مثلا هایزنبرگ و پروژه اورانیوم، شرودینگر و روابط شخصی) — بدون حدس، فقط با منبع معتبر
- بخش «به چه کاری و اخلاقی معروف بودند»

**H2-7: داستان‌ها و روایت‌های عامه — با هشدار سند**
- قالب: «می‌گویند روزی نیلز بور در کپنهاگ ... — **توجه: این روایت در خاطرات X آمده اما سند رسمی ندارد**»
- لحن مهربان، باهوش، داستان‌گو

**H2-8: کادرهای هایلایت (۳ نوع)**
- `<blockquote class="qpedia-quote">` نقل قول مستقیم با منبع
- `<div class="qpedia-newspaper">` روزنامه وقت — مثلا New York Times 1919 «نور خم شد»
- `<div class="qpedia-media">` اخبار رسانه‌ها — مثلا BBC 2022

**H2-9: میراث و تاثیر بر فناوری امروز**
- از ترانزیستور تا GPS

**H2-10: جدول زمانی دقیق (Timeline)**
- 1879-03-14 تولد — اولم، آلمان — [Britannica]
- ...

**H2-11: سوءبرداشت‌های رایج**
- ۴ مورد با اصلاح

**H2-12: جمع‌بندی**

**H2-13: پرسش‌های متداول (FAQ) — ۶ سوال**
- هر سوال شامل کلیدواژه، جواب ۴۰-۶۰ کلمه، با FAQPage schema

**H2-14: منابع و مطالعه بیشتر**
- لیست نهایی ۸-۱۲ منبع معتبر: Nobel, Britannica, Stanford Encyclopedia, DOI, کتاب

**H2-15: پیوندهای داخلی پیشنهادی**
- لیست ۶ لینک داخلی به مقالات Qpedia + ۴ لینک به دانشمندان دیگر

**H2-16: نسخه انگلیسی**
- توضیح: این مقاله نسخه فارسی است، نسخه انگلیسی در `/en/scientists/albert-einstein/` با hreflang

### الزامات سئو که در همین قالب رعایت می‌شود
- عنوان سئو ۵۰-۶۰ کاراکتر شامل کلیدواژه: «زندگی‌نامه آلبرت اینشتین — از فوتوالکتریک تا نوبل»
- متا ۱۲۰-۱۶۰ کاراکتر شامل کلیدواژه + یک دستاورد + یک تاریخ
- کلیدواژه در H1، H2 اول، پاراگراف اول، alt تصویر، URL، FAQ
- تصویر: `albert-einstein.webp` alt: «پرتره آلبرت اینشتین 1921 برنده نوبل فیزیک»
- Schema: Person (birthDate, deathDate, birthPlace, nationality, alumniOf, award, knowsAbout, sameAs), Article, FAQPage, Breadcrumb
- لینک داخلی: حداقل ۶، انکر طبیعی فارسی
- لینک خارجی: حداقل ۴، با rel noopener، متن کوتاه منبع لینک‌دار (نه URL خام)
- کلمه: ۲۰۰۰+ فارسی، بعد انگلیسی 1800+ (ترجمه انسانی، نه ماشینی)
- یونیک بودن: تست با Copyscape / Originality — بالای ۹۰٪

## 3) استراتژی دوزبانه — گوگل متوجه تکراری نباشد

- فارسی: `https://qpedia.ir/scientists/albert-einstein/` — lang fa-IR, hreflang fa
- انگلیسی: `https://qpedia.ir/en/scientists/albert-einstein/` — lang en-US, hreflang en
- هر صفحه canonical به خودش
- هر دو دارای `<link rel="alternate" hreflang="fa" href=".../scientists/albert-einstein/">` و `hreflang="en"` و `hreflang="x-default"`
- Person @id مشترک: `https://qpedia.ir/scientists/albert-einstein/#person` — هر دو نسخه به همین @id ارجاع دهند تا گوگل بفهمد یک موجودیت، دو زبان
- محتوا ترجمه انسانی با مثال‌های بومی متفاوت، نه ترجمه کلمه‌به‌کلمه، تا Duplicate تلقی نشود
- meta انگلیسی متفاوت، اما حقایق یکسان با منابع یکسان

## 4) لیست فعلی دانشمندان — ۵۶ پست نوع quantum_scientist (از WordPress.2026-09-21qpedia.xml)

منبع دقیق: `wp:post_type=quantum_scientist` — ۵۶ رکورد

| # | ID | اسلاگ فعلی | عنوان فعلی | وضعیت | مشکل | اسلاگ پیشنهادی صحیح | اکشن 301 |
|---|---|---|---|---|---|---|---|
| 1 | 2060 | albert-einstein-2 | آلبرت اینشتین | publish | عدد اضافی -2، لینک دوتایی | albert-einstein | 301 از albert-einstein-2 و albert-einstein-2/albert-einstein-3 به albert-einstein |
| 2 | 2266 | schrodingerr | اروین شرودینگر | publish | غلط املایی schrodingerr + دوبل با 2328 | حذف — ادغام به erwin-schrodinger | 301 از schrodingerr و schrodingerr/* به erwin-schrodinger |
| 3 | 2328 | erwin-schrodinger | اروین شرودینگر؛ مردی که جهان را موج کرد | publish | صحیح — نگه‌داری | erwin-schrodinger | canonical |
| 4 | 265 | max-plank | ماکس پلانک | publish | غلط املایی pl**a**nk به جای planck | max-planck | 301 از max-plank به max-planck |
| 5 | 231 | david-bohm | دیوید بوهم | publish | ok | david-bohm | - |
| 6 | 2327 | louis-de-broglie | لویی دوبروی؛ شاهزاده‌ای که الکترون را موج کرد | publish | ok | louis-de-broglie | - |
| 7 | 2329 | werner-heisenberg | ورنر هایزنبرگ؛ جوانی که عدم قطعیت را کشف کرد | publish | ok | werner-heisenberg | - |
| 8 | 233 | alain-aspect | آلن اسپکت | publish | ok | alain-aspect | - |
| 9 | 2330 | max-born | ماکس بورن؛ مترجم احتمال در کوانتوم | publish | ok | max-born | - |
| 10 | 2331 | hendrik-kramers | هانس کرامرس؛ نابغهٔ گمنام مکانیک کوانتومی | publish | ok | hendrik-kramers | - |
| 11 | 2332 | richard-feynman | ریچارد فاینمن؛ مردی که کوانتوم را به تصویر کشید | publish | ok | richard-feynman | - |
| 12 | 2333 | julian-schwinger | جولیان شوینگر؛ مهارکنندهٔ بی‌نهایت‌ها | publish | ok | julian-schwinger | - |
| 13 | 2334 | sin-itiro-tomonaga | سین-ایتیرو توموناگا؛ نابغهٔ تنها در جنگ | publish | ok (بهتر shin-ichiro-tomonaga اما نگه می‌داریم) | sin-itiro-tomonaga | - |
| 14 | 2335 | eugene-wigner | یوجین ویگنر؛ زبان تقارن در فیزیک | publish | ok | eugene-wigner | - |
| 15 | 2336 | hans-bethe | هانس بته؛ مردی که راز خورشید را گشود | publish | ok | hans-bethe | - |
| 16 | 2337 | george-gamow | جرج گاموف؛ از تونل‌زنی تا مهبانگ | publish | ok | george-gamow | - |
| 17 | 2338 | lev-landau | لو لاندائو؛ نابغه‌ای که سانحه خاموشش کرد | publish | ok | lev-landau | - |
| 18 | 2339 | john-wheeler | جان ویلر؛ مردی که سیاه‌چاله را نام گذاشت | publish | ok | john-wheeler | - |
| 19 | 234 | anton-zeilinger | آنتونی زایلینگر | publish | ok | anton-zeilinger | - |
| 20 | 2340 | wojciech-zurek | وویچیخ زورک؛ توضیح‌دهندهٔ دنیای کلاسیک | publish | ok | wojciech-zurek | - |
| 21 | 2341 | john-clauser | جان کلاوزر؛ آزمایشگری که به حاشیه گوش نداد | publish | ok | john-clauser | - |
| 22 | 2342 | john-von-neumann | جان فون‌نویمان؛ مغزی که کوانتوم را بنیان نوشت | publish | ok | john-von-neumann | - |
| 23 | 2343 | paul-ehrenfest | پل اهرنفست؛ پلی که زیر پای خودش شکست | publish | ok | paul-ehrenfest | - |
| 24 | 2344 | ernest-rutherford | ارنست رادرفورد؛ کاشف هستهٔ اتم | publish | ok | ernest-rutherford | - |
| 25 | 2345 | robert-millikan | رابرت میلیکان؛ وزن کردن بار الکترون | publish | ok | robert-millikan | - |
| 26 | 2346 | clinton-davisson | کلینتون دیویسون؛ کشف تصادفی موج الکترون | publish | ok | clinton-davisson | - |
| 27 | 2347 | otto-stern | اتو اشترن؛ آزمایشی که اسپین را عینی کرد | publish | ok | otto-stern | - |
| 28 | 2348 | walther-gerlach | والتر گرلاخ؛ دستانی که اسپین را دیدند | publish | ok | walther-gerlach | - |
| 29 | 2349 | leo-esaki | لئو اساکی؛ دیودی که از دیوار عبور کرد | publish | ok | leo-esaki | - |
| 30 | 235 | arnold-sommerfeld | آرنولد زومرفلد | publish | ok | arnold-sommerfeld | 301 از schrodingerr/arnold-sommer-feld به arnold-sommerfeld |
| 31 | 2350 | gerd-binnig | گرد بینیگ؛ نخستین نگاه به اتم‌ها | publish | ok | gerd-binnig | - |
| 32 | 2351 | heinrich-rohrer | هاینریش روهرر؛ دیدن اتم‌ها با لمس | publish | ok | heinrich-rohrer | - |
| 33 | 2352 | brian-josephson | برایان جوزفسون؛ پیش‌بینی در ۲۲سالگی | publish | ok | brian-josephson | - |
| 34 | 2353 | akira-tonomura | آکیرا تونومورا؛ الکترون‌ها یکی‌یکی | publish | ok | akira-tonomura | - |
| 35 | 2354 | james-clerk-maxwell | جیمز کلرک ماکسول؛ مرد چهار معادله | publish | ok | james-clerk-maxwell | - |
| 36 | 2355 | ludwig-boltzmann | لودویگ بولتزمن؛ شمارندهٔ بی‌نظمی | publish | ok | ludwig-boltzmann | - |
| 37 | 2356 | marie-curie | ماری کوری؛ دو نوبل، یک زندگی | publish | ok | marie-curie | - |
| 38 | 2357 | emmy-noether | امی نوتر؛ زنی که تقارن را ترجمه کرد | publish | ok | emmy-noether | - |
| 39 | 2358 | lise-meitner | لیزه مایتنر؛ مادر شکافت هسته‌ای | publish | ok | lise-meitner | - |
| 40 | 236 | arthur-compton | آرتور کامپتون | publish | ok | arthur-compton | - |
| 41 | 237 | charles-wilson | چارلز ویلسون | publish | ok | charles-wilson | - |
| 42 | 238 | david-deutsch | دیوید دویچ | publish | ok | david-deutsch | - |
| 43 | 239 | david-hilbert | دیوید هیلبرت | publish | ok | david-hilbert | - |
| 44 | 240 | enrico-fermi | انریکو فرمی | publish | ok | enrico-fermi | - |
| 45 | 242 | peter-shor | پیتر شور | publish | ok | peter-shor | - |
| 46 | 266 | niels-bohr | نیلز بور | publish | ok | niels-bohr | - |
| 47 | 267 | paul-dirac | پل دیراک | publish | ok | paul-dirac | - |
| 48 | 268 | wolfgang-pauli | ولفگانگ پاولی | publish | ok | wolfgang-pauli | - |
| 49 | 269 | pascual-jordan | پاسکوال یوردان | publish | ok | pascual-jordan | - |
| 50 | 270 | hermann-weyl | هرمان وایل | publish | ok | hermann-weyl | 301 از schrodingerr/herman-weyl به hermann-weyl |
| 51 | 271 | satyendra-bose | ساتیندرا بوز | publish | ok | satyendra-bose | - |
| 52 | 272 | freeman-dyson | فریمن دایسون | publish | ok | freeman-dyson | - |
| 53 | 273 | hideki-yukawa | هیدکی یوکاوا | publish | ok | hideki-yukawa | - |
| 54 | 274 | john-bell | جان بل | publish | ok | john-bell | 301 از schrodingerr/john-bell-3 به john-bell |
| 55 | 275 | hugh-everett | هیو اورت | publish | ok | hugh-everett | - |
| 56 | 322 | isaac-newton | آیزاک نیوتن | publish | ok | isaac-newton | - |

**بعد از پاکسازی: ۵۵ دانشمند یکتا** (schrodingerr حذف)

### ترتیب پیشنهادی بازنویسی (از مهم‌ترین و پرلینک‌ترین شروع)

1. max-planck (مبدأ کوانتوم، غلط املایی دارد — اول اصلاح)
2. albert-einstein (پربازدید، لینک شکسته)
3. niels-bohr
4. erwin-schrodinger (دوبل)
5. werner-heisenberg
6. paul-dirac
7. wolfgang-pauli
8. max-born
9. louis-de-broglie
10. richard-feynman
... تا 55

## 5) موارد اضافی که ما اضافه می‌کنیم (فراتر از گوگل)

- کادر «عقاید دینی/فلسفی با سند» — مثلا اینشتین: نامه به گوتکایند 1954 agnostic، پلانک: Lutheran اما دفاع از علم
- کادر «مشکلات اخلاقی یا اتفاقات خوب/بد» — هایزنبرگ و اورانیوم‌وراین، شرودینگر و زندگی شخصی بحث‌برانگیز (با منبع، بدون قضاوت)
- کادر «به چه کاری و اخلاقی معروف بودند» — مثلا فاینمن: شوخ‌طبعی و آموزش
- بخش داستانی با برچسب «روایت عامه — سند قطعی ندارد»
- نقل قول روزنامه وقت: مثلا Times 1919 «Einstein's theory triumphs»
- منبع‌دهی فوری: بعد هر نظریه، لینک کوتاه مثل [Nobel 1921] یا [Phys. Rev. 1926]
- FAQ با لحن انسانی، نه رباتیک
- نسخه انگلیسی با همان طرح، hreflang، ترجمه انسانی

---

**در انتظار تایید شما:** اگر این الگو تایید است، فایل دوم «قالب آماده مقاله‌نویسی» را می‌سازم و بعد اولین دانشمند (max-planck) را با ۲۰۰۰+ کلمه، تحقیق کامل، منابع دقیق، و نسخه انگلیسی‌اش می‌نویسم.
