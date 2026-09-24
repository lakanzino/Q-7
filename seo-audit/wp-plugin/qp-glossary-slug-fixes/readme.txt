=== Qpedia Glossary Slug Fixes — اصلاح هدفمند اسلاگ اصطلاحات ===
Contributors: qpedia
Requires at least: 6.0
Tested up to: 6.7
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html
Tags: glossary, slug, seo, redirect

فقط ۶۰ اصطلاح واژه‌نامه که اسلاگ انگلیسی نادرست دارند اصلاح می‌شوند؛ بقیهٔ مدخل‌ها
دست‌نخورده می‌مانند. آدرس قبلی هر مدخل با ۳۰۱ به آدرس تازه می‌رود.

== نتیجهٔ بررسی ۲۰۱ اصطلاح (خروجی WordPress.2026-09-24.xml) ==

* ۱۴۱ مدخل اسلاگ انگلیسی درست دارند → دست‌نخورده
* ۵۹ مدخل اسلاگ ترانسلیریت یا انگلیسیِ اشتباه دارند → اصلاح می‌شوند
* ۱ مدخل اسلاگ درست دارد ولی نام انگلیسی‌اش ناسازگار بود → فقط نام اصلاح می‌شود

نمونه:
  /glossary/kvantsh/                      →  /glossary/quantization/
  /glossary/alktrvn/                      →  /glossary/electron/
  /glossary/nyrvy-alktrvmghnatysy/        →  /glossary/electromagnetic-force/
  /glossary/shargi-cloud/                 →  /glossary/superfluidity/
  /glossary/quantum-lattice/              →  /glossary/quantum-network/
  /glossary/coopers-pair/                 →  /glossary/cooper-pair/

== نصب و اجرا ==

۱. افزونه‌ها ← افزودن ← بارگذاری افزونه ← انتخاب زیپ ← نصب ← فعال‌سازی
۲. «اصطلاحات ← اصلاح اسلاگ (سئو)»
۳. اول دکمهٔ «فقط بررسی (بدون تغییر)» را بزنید تا وضعیت هر ردیف را ببینید.
۴. بعد «جابه‌جا کردن ۵۹ مدخل آماده» را بزنید.

== چرا امن است ==

* هر ردیف با شناسه و اسلاگ فعلی تطبیق داده می‌شود؛ اگر اسلاگ مدخلی با فهرست
  نخواند، افزونه دست نمی‌زند و «دست نزدم» گزارش می‌دهد.
* اسلاگ قبلی در `_wp_old_slug` ثبت می‌شود، وردپرس خودش ۳۰۱ می‌سازد.
* فقط مدخل‌های همین فهرست تغییر می‌کنند (۵۹ مدخل)، هیچ جای دیگری از سایت.
* پس از اجرا کش واژه‌نامه و قوانین پیوند یکتا تازه می‌شوند.

== تغییرات ==

= 1.0.0 =
* نسخهٔ نخست: فهرست ۶۰ اصلاح، اجرای دسته‌ای، بررسی بدون تغییر، گزارش و تیک انتخابی.
