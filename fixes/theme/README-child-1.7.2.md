# پوستهٔ فرزند ۱.۷.۲ — چه چیزی درست شد (مقایسه با ۱.۵.۰)

منبع: `quantum-pedia-child (13).zip` که روی `main` گذاشتی (commit `78705d4`).
آینهٔ کاریِ همین نسخه در این پوشه: `fixes/theme/live-1.7.2/` (فقط PHP و CSS؛ بدون تصویر و فونت، ۱۸۰ کیلوبایت).
نسخهٔ قبلیِ همان zip یعنی `quantum-pedia-child (9).zip` روی `main` است و باید حذف شود — در این برانش اصلاً وجود نداشت، پس چیزی برای پاک‌کردن نبود.

فایل‌های تغییرکرده بین دو zip: `functions.php` (+۱۴۱۳ بایت) · `header.php` (+۷۶۷) · `footer.php` (+۱۱۴) · `style.css` (شمارهٔ نسخه) · `assets/css/custom.css` · `assets/css/qpedia-layouts.css` (+۴۵۰) · `assets/css/qpedia-guide.css` (+۵۲) · `assets/css/blocks-editor.css` (هم‌اندازه، فقط رنگ خطِ بلوک از `#0f766e` به `#3BA7F2`). بقیهٔ ۲۴ فایل بیت‌به‌بیت یکسان‌اند.

## رفع‌شده‌ها

| # | کجا | چه کار کرده | اثر |
|---|---|---|---|
| ۱ | `header.php` + `functions.php:43-50` | درخت دسته‌های هدر یک‌بار ساخته می‌شود و در transient `qpedia_header_nav_tree_v1` می‌نشیند؛ روی `created/edited/delete_quantum_category` و `saved_term` پاک می‌شود | `get_terms` تکراری در هر درخواست حذف شد ← کندیِ بارگذاری برگه‌ها |
| ۲ | `functions.php:78-90` | `qpedia-guide.css` از فهرست سراسری بیرون رفت؛ فقط روی `is_page('start'|'از-کجا-شروع-کنیم')` یا قالب `page-homepage.php` لود می‌شود | CSS کمتری در مقاله‌ها |
| ۳ | `functions.php:396-414` | `qpedia_child_find_page_url()` با `static $memo` در هر درخواست کش می‌شود | هر فوتر/هدر چندبار کوئری تکراری نمی‌زند |
| ۴ | `functions.php:280-286` | شمارهٔ flush قواعد rewrite از `qpedia-1.2.0` به `qpedia-1.7.2` رسید | بعد از آپلود، قواعد یک‌بار خودکار بازسازی می‌شود |
| ۵ | `footer.php:12-24` | فوتر بازسازی شد: لوگو + شعار، `nav` با `aria-label="صفحات سایت"`، رشته‌ها با `esc_html_e`/`esc_attr_e` | دسترس‌پذیری + قابل‌ترجمهشدن؛ ساختار `<div>` به `<nav>` |
| ۶ | `style.css` | `Version: 1.5.0 → 1.7.2`، `Tested up to: 6.5 → 6.7`؛ `QPEDIA_CHILD_VERSION` از `2026.09.16` به `1.7.2` یکدست شد | نسخهٔ واقعی در همه‌جا یکی است |
| ۷ | `assets/css/custom.css` | **همان فازی که ما دادیم، بیت‌به‌بیت** (۱۷٬۹۴۷ بایت؛ فقط خطِ آخر حذف شده) | استایل هدر/فوتر از پوسته می‌آید، نه از «CSS سفارشی» |
| ۸ | `assets/css/qpedia-layouts.css` + `qpedia-guide.css` + `blocks-editor.css` | پالت از سبز-آبی (`#0f766e`) به آبی برند (`#3BA7F2`، `#0B3D91`، `--qp-ink:#333333`، خط‌ها `rgba(59,167,242,.15)`)؛ تایپوگرافی `clamp()`؛ `.qp-single__title` صریح شد؛ فاصلهٔ گرید ۱۲→۱۶ و easing به `ease-out` | یکدست‌شدن رنگ‌ها و مقیاس‌پذیری تیترها |
| ۹ | `page-homepage.php` | این فایل در پوسته، بومِ خالیِ `Template Name: صفحه اصلی` است که `the_content()` را صدا می‌کند | پس هیچ محتوایی «قورت» داده نمی‌شود؛ نسخهٔ ۱۷۸خطیِ ما (`fixes/theme/page-homepage.php`) روی سرور نصب نیست و فقط تاریخی است |

## باقی‌مانده‌ها (به‌ترتیب اهمیت)

**الف) ۴۰۴ِ کش‌شده — تنها چیزی که هنوز کاربر می‌بیند.**
داده سالم است: `parent: 0`، عنوان «مقررات ما»، انتشار publish. نتیجهٔ آزمونِ زنده:

- `https://qpedia.ir/privacy-policy/?qp=1` ⇒ صفحه کامل رندر می‌شود ✓
- `https://qpedia.ir/privacy-policy/` ⇒ هنوز `Page Not Found`

یعنی پاسخ ۴۰۴ِ قدیمی در کش LiteSpeed مانده. کاری که باید بکنی: **ابزارها → LiteSpeed Cache → Purge All**. اگر خواستی مطمئن شوی، یک‌بار هم **تنظیمات → پیوندهای یکتا → ذخیرهٔ تغییرات**.

**ب) لینک «مقررات ما» در فوتر still به جای درست نمی‌رود.**
`footer.php:20` این اسلاگ‌ها را می‌گردد: `rules`, `terms`, `regulations`, `مقررات-ما` — هیچ‌کدام از این برگه‌ها وجود ندارد (برگه‌های منتشرشده: `start`, `home`, `privacy-policy`, `contact-us`, `about-us`) ⇒ تابع به fallback می‌افتد و لینک به **صفحهٔ اصلی** می‌رود. رفعش یک خط است، با گذاشتن `privacy-policy` اولِ فهرست:

```php
// قبل
			<a href="<?php echo esc_url( qpedia_child_find_page_url( array( 'rules', 'terms', 'regulations', 'مقررات-ما' ) ) ); ?>"><?php esc_html_e( 'مقررات ما', 'quantum-pedia-child' ); ?></a>
// بعد
			<a href="<?php echo esc_url( qpedia_child_find_page_url( array( 'privacy-policy', 'rules', 'terms', 'regulations', 'مقررات-ما' ) ) ); ?>"><?php esc_html_e( 'مقررات ما', 'quantum-pedia-child' ); ?></a>
```

نصب با گوشی: cPanel → File Manager → `public_html/wp-content/themes/quantum-pedia-child/footer.php` → راست‌کلیک → Edit → همان یک خط را عوض کن → Save Changes. **اول از همان فایل یک کپی بگیر** (`footer.php.bak`) تا راه برگشت داشته باشی. نکته: اگر افزونهٔ «ویرایش پوسته» در پیشخوان باز است، همان‌جا هم می‌شود؛ اما File Manager قابل‌اتکاتر است.

**پ) تکرار وظیفه بین mu-plugin و پوسته.**
پوسته حالا خودش در `functions.php:213-218` فهرست `start / about-us / contact-us / privacy-policy` را «برگه» می‌داند؛ mu-plugin `qpedia-pages-fix.php` همان کار را به‌علاوهٔ `home`، ۳۰۱های `starting|start-here|begin → start`، `about → about-us`، `contact → contact-us`، `privacy → privacy-policy`، آرشیو `/topics/` و ترمیم یتیم‌ها انجام می‌دهد. هر دو با هم تداخل ندارند (mu-plugin زودتر اجرا می‌شود و هر دو به یک `pagename` می‌رسند)، ولی اگر یک‌جا‌کردنی می‌خواهی: mu-plugin را نگه دار و فقط از فهرستِ `page_first_slugs` پوسته بی‌نیاز شو — فعلاً لازم نیست دست بزنی.

**ت) اگر «CSS سفارشی» هنوز پر است، خالیه‌اش کن.**
چون `custom.css` داخل پوسته رفت، نگهداشتن همان متن در **ظاهر → سفارشی‌سازی → CSS اضافی** یعنی ۱۸ کیلوبایت دوباره لود می‌شود. حذفش از پوسته چیزی کم نمی‌کند. (فقط اگر آنجا چیزی جز همان فایل نیست پاکش کن؛ `fixes/theme/custom.orig.css` برای بازگشت دست‌نخورده مانده.)
