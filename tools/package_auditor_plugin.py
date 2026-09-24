#!/usr/bin/env python3
import zipfile
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
plugin_dir = ROOT / 'article-rewrite-2026-09-22/importers/google-content-auditor/qpedia-google-content-auditor'

readme_text = """=== Qpedia Google Content Quality & EEAT Auditor ===
Contributors: qpedia
Tags: seo, google audit, eeat, helpful content, content score
Requires at least: 5.6
Tested up to: 7.1
Requires PHP: 7.4
License: GPLv2 or later

افزونه جامع سنجش کیفیت و رتبه‌بندی محتوا مطابق با جدیدترین قوانین و معیارهای رسمی گوگل (Needs Met, E-E-A-T, Information Gain, Helpful Content, Core Updates) همراه با امتیازدهی ۱۰۰ امتیازی، تحلیل موشکافانه دلایل کسر امتیاز و داشبورد مدیریتی کامل فارسی.

== Description ==
این افزونه هر مقاله را بر اساس ۴ ستون رسمی ارزیابی محتوای گوگل می‌سنجد:
1. سطح ۱: پاسخ به نیاز کاربر و ارزش اطلاعاتی (Needs Met & Information Gain - ۳۰ امتیاز)
2. سطح ۲: اعتبار علمی، تخصص و اعتماد (E-E-A-T & Trust - ۳۰ امتیاز)
3. سطح ۳: معماری محتوا و لینک‌سازی داخلی (Content Architecture & SC - ۲۰ امتیاز)
4. سطح ۴: متادیتا و استانداردهای سئو (Technical SEO & Metadata - ۲۰ امتیاز)
"""

(plugin_dir / 'README.txt').write_text(readme_text.strip() + '\n', encoding='utf-8')

zip_dest = ROOT / 'article-rewrite-2026-09-22/importers/google-content-auditor/qpedia-google-content-auditor.zip'
with zipfile.ZipFile(zip_dest, 'w', zipfile.ZIP_DEFLATED) as zf:
    for f in plugin_dir.glob('*'):
        if f.is_file():
            zf.write(f, arcname=f'qpedia-google-content-auditor/{f.name}')

print(f'Created {zip_dest} ({zip_dest.stat().st_size / 1024:.1f} KB)')

dl_dest = ROOT / 'downloads/qpedia-google-content-auditor.zip'
dl_dest.write_bytes(zip_dest.read_bytes())
print(f'Copied to {dl_dest} ({dl_dest.stat().st_size / 1024:.1f} KB)')
