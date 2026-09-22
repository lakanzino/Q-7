# تایید v6 — ماکس پلانک بدون تصویر داخلی + FAQ فیکس

**تاریخ:** 2026-09-21
**درخواست کاربر از روی اسکرین‌شات:**
1. تصویر داخل متن بالا نمی‌آید — تصویر داخل متن نذار، تصویر شاخص را خودم می‌ذارم → ✅ حذف تمام <img> از محتوا، featured_image خالی در importer
2. FAQ علامت سوالات اشتباه میان → ✅ فیکس شد: EN با dir="ltr" و "Who was Max Planck?" صحیح، FA با "؟" فارسی صحیح
3. متن فارسی کجاست؟ → ✅ هر دو زبان در یک افزونه: slug max-planck (fa-IR) و max-planck-en (en-US) با canonical جدا و hreflang متقابل

**فایل‌ها:**
- FA v6: max-planck.fa.v6-fluent-noimg.html — 2964 کلمه، بدون img
- EN v6: max-planck.en.v6-fluent-noimg.html — 2825 کلمه، بدون img
- Plugin v6: qp-scientist-importer-max-planck v6.0.0 — 26KB

**استایل منبع بعد پاراگراف (درخواستی):**
.qpedia-para-source{font-size:12px;color:#94a3b8;margin:-4px 0 22px}
منابع طوسی کم‌رنگ بعد هر پاراگراف

**نتیجه:** ✅ APPROVED v6 NoImg — آماده انتشار پیش‌نویس
