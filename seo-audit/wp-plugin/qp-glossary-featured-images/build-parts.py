#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""ساخت دو بستهٔ کوچک‌تر از تصاویر شاخص.

منبع (این پوشه):
  template/   → قالب افزونه (نسخهٔ کامل ۵۵ تصویری که به دو بسته شکسته می‌شود)
  map.php     → ۵۵ ردیف نگاشت مقاله ← تصویر
  images/     → ۵۵ فایل WebP

خروجی:
  part-1/qp-featured-images-1/  و  part-2/qp-featured-images-2/
  و زیپ‌های نصب: qp-featured-images-1-of-2-1.2.0.zip و qp-featured-images-2-of-2-1.2.0.zip

اجرا:  python3 build-parts.py
"""
import io
import os
import re
import shutil
import subprocess
import sys
import zipfile

HERE = os.path.dirname(os.path.abspath(__file__))
REPO = os.path.abspath(os.path.join(HERE, '..', '..', '..'))
VERSION = '1.2.0'
PARTS_TOTAL = 2
ARTICLES_TOTAL = 55
BATCH_SIZE = 5

PART_CONF = {
    1: dict(num=1, prefix='qpfa', other='qpfb', items_total=2),
    2: dict(num=2, prefix='qpfb', other='qpfa', items_total=2),
}


def fa_num(value):
    """ارقام فارسی برای متن‌های ثابت."""
    table = str.maketrans('0123456789', '۰۱۲۳۴۵۶۷۸۹')
    return str(value).translate(table)


def read(path):
    return io.open(path, encoding='utf-8').read()


def write(path, text):
    os.makedirs(os.path.dirname(path), exist_ok=True)
    io.open(path, 'w', encoding='utf-8').write(text)


def map_rows():
    """ردیف‌های خام map.php را برمی‌گرداند."""
    src = read(os.path.join(HERE, 'map.php'))
    rows = re.findall(r"^\tarray\( 'slug' => '[^']+',.*\),$", src, re.M)
    if len(rows) != ARTICLES_TOTAL:
        raise SystemExit('تعداد ردیف‌های map.php غیرمنتظره است: %d' % len(rows))
    return rows


def row_slug(row):
    return re.search(r"'slug' => '([^']+)'", row).group(1)


def row_file(row):
    return re.search(r"'file' => '([^']+)'", row).group(1)


def split_rows(rows):
    """تقسیم ردیف‌ها به دو دستهٔ متوازن (تعداد مقاله + حجم تصویر)."""
    sizes = [os.path.getsize(os.path.join(HERE, 'images', row_file(r))) for r in rows]
    n = len(rows)
    best = None
    for cut in range(max(20, n // 2 - 5), min(n - 2, n // 2 + 6) + 1):
        b1, b2 = sum(sizes[:cut]), sum(sizes[cut:])
        # اختلاف تعداد مقاله در اولویت است، بعد اختلاف حجم
        score = (abs(cut - (n - cut)), abs(b1 - b2))
        if best is None or score < best[0]:
            best = (score, cut)
    cut = best[1]
    return rows[:cut], rows[cut:], sum(sizes)


def transform(src, cfg):
    """جایگزینی پیشوندهای عمومی با پیشوند مخصوص این بسته.

    متاهای مشترک بین دو بسته (‎_qpfi_kw، _qpfi_en، _qpfi_alt_source)، پوشهٔ
    پشتیبان مشترک (qp-fi-backup) و فیلتر مشترک (qpfi_alt_text) دست‌نخورده می‌مانند.
    """
    p = cfg['prefix']
    s = src
    s = s.replace('_qpfi_', '\x01').replace('qp-fi-', '\x02').replace('qpfi_alt_text', '\x03')
    s = s.replace('QPFI_', p.upper() + '_')
    s = s.replace('qpfi_', p + '_')
    s = re.sub(r'(?<![A-Za-z0-9_])qpfi(?![A-Za-z0-9_])', p, s)
    s = s.replace('QP_Featured_Images', 'QP_Featured_Images_Part_%d' % cfg['num'])
    s = s.replace('qp-featured-images', 'qp-featured-images-%d' % cfg['num'])
    return s.replace('\x01', '_qpfi_').replace('\x02', 'qp-fi-').replace('\x03', 'qpfi_alt_text')


def set_const(text, name, value, quote=True):
    if quote:
        new = "define( '%s', '%s' );" % (name, value)
    else:
        new = "define( '%s', %s );" % (name, value)
    out, n = re.subn(r"define\( '%s',[^)]*\);" % name, new, text, count=1)
    if n != 1:
        raise SystemExit('ثابت %s پیدا نشد' % name)
    return out


def header_and_docs(text, cfg, rows):
    num = cfg['num']
    count = len(rows)
    head_old = re.search(r'/\*\*.*?\n \*/', text, re.S).group(0)
    head_new = """/**
 * Plugin Name: Qpedia Featured Images — بستهٔ %(fa_num)s از %(fa_total)s (تصاویر شاخص جدید)
 * Plugin URI:  https://qpedia.ir/
 * Description: بستهٔ %(fa_num)s از %(fa_total)s — تصاویر شاخص %(fa_count)s مقاله را جای تصویر قبلی می‌گذارد، تصویر قبلی را حذف می‌کند و متن جانشین (alt) استاندارد گوگل را با کلمهٔ کلیدی هر مقاله ثبت می‌کند. فایل‌ها سبک (WebP، ۱۶:۹) هستند و اجرا مرحله‌ای است تا نصب و اجرا سریع باشد.
 * Version:     %(version)s
 * Author:      Qpedia
 * Author URI:  https://qpedia.ir/about-us/
 * License:     GPL-2.0-or-later
 * Text Domain: qp-featured-images-%(num)d
 * Requires PHP: 7.4
 *
 * @package QP_Featured_Images_Part_%(num)d
 */""" % dict(num=num, total=PARTS_TOTAL, count=count, version=VERSION,
                            fa_num=fa_num(num), fa_total=fa_num(PARTS_TOTAL), fa_count=fa_num(count))
    return text.replace(head_old, head_new)


def part_map(rows, cfg):
    count = len(rows)
    return """<?php
/**
 * نگاشت مقاله ← تصویر شاخص تازه — بستهٔ %(num)d از %(total)d (%(count)d مقاله).
 *
 * هر ردیف:
 *   slug  → اسلاگ مقاله (نام فایل تصویر هم همین است)
 *   file  → نام فایل WebP داخل includes/images/
 *   title → عنوان مقاله (برای نام تصویر در کتابخانهٔ رسانه)
 *   en    → نام انگلیسی اصطلاح
 *   kw    → کلمهٔ کلیدی کانونی مقاله (از Rank Math)
 *   alt   → متن جانشین طبق استاندارد گوگل: توصیفی، طبیعی، زیر ۱۲۵ نویسه، شامل کلمهٔ کلیدی
 *
 * @package QP_Featured_Images_Part_%(num)d
 */

defined( 'ABSPATH' ) || exit;

return array(
%(rows)s
);
""" % dict(num=cfg['num'], total=PARTS_TOTAL, count=count, rows='\n'.join(rows))


def part_readme(rows, cfg):
    num = cfg['num']
    return """=== Qpedia Featured Images — بستهٔ %(fa_num)s از %(fa_total)s ===
Contributors: qpedia
Requires at least: 6.0
Tested up to: 6.7
Requires PHP: 7.4
Stable tag: %(version)s
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html
Tags: featured-image, webp, alt-text, seo

بستهٔ %(fa_num)s از %(fa_total)s — تصاویر شاخص تازهٔ %(fa_count)s مقاله را جای تصویر قبلی
می‌گذارد، تصویر قبلی را حذف می‌کند و متن جانشین (alt) استاندارد گوگل را همراه
کلمهٔ کلیدی مقاله ثبت می‌کند.

تصاویر در دو بستهٔ کوچک‌تر تقسیم شده‌اند تا نصب سبک باشد و اجرا وسط کار
مرورگر/سرور را قطع نکند.

== چرا دو بسته؟ ==

نسخهٔ تک‌بسته‌ای ۵۵ تصویر را یک‌جا داشت؛ روی هاست اشتراکی نصب و اجرای آن طول
می‌کشید و اتصال مرورگر قطع می‌شد. حالا هر بسته سبک است و اجرای مرحله‌ای
(دستهٔ %(fa_batch)s مقاله در هر مرحله) خودکار تا پایان ادامه می‌دهد؛ سقف کار هر درخواست ۸ ثانیه است و اندازهٔ مرحله خودکار تنظیم می‌شود.

== نصب و اجرا ==

۱. افزونه‌ها ← افزودن ← بارگذاری افزونه ← نصب ← فعال‌سازی
۲. «ابزارها ← تصاویر شاخص جدید (بستهٔ %(fa_num)s)»
۳. «فقط بررسی (بدون تغییر)» برای پیش‌نمایش.
۴. «شروع اجرا (مرحله‌ای و ایمن)» — صفحه خودش مرحله‌به‌مرحله تا پایان ادامه می‌دهد.
۵. اگر وسط کار مرورگر بسته شد: همان صفحه دکمهٔ «ادامه» را نشان می‌دهد.
۶. بستهٔ دیگر را هم نصب و فعال کنید تا همهٔ %(fa_articles)s مقاله پوشش داده شود.

== تصاویر ==

* نسبت ۱۶:۹ (۱۲۰۰×۶۷۵) · WebP · میانگین ۳۳ کیلوبایت
* نام فایل: اسلاگ انگلیسی مقاله

== متن جانشین (alt) — استاندارد گوگل ==

* توصیفی و طبیعی، نه انباشت کلیدواژه
* شامل کلمهٔ کلیدی کانونی همان مقاله (از `rank_math_focus_keyword`)
* زیر ۱۲۵ نویسه (توصیهٔ گوگل)

در کتابخانهٔ رسانه ذخیره می‌شود:
`_wp_attachment_image_alt` (متن جانشین) · `_qpfi_kw` (کلمهٔ کلیدی) · `_qpfi_en` (نام انگلیسی)

== رفتار ==

* اگر نام فایل تازه با فایل فعلی یکی باشد: فایل سرجایش بازنویسی می‌شود و آدرس تصویر عوض نمی‌شود.
* وگرنه: تصویر تازه ساخته می‌شود و تصویر قبلی حذف می‌گردد.
* تصویر قبلی که با مقالهٔ دیگری مشترک است حذف نمی‌شود.
* پشتیبان اختیاری تصویر قبلی در `wp-content/uploads/qp-fi-backup/`.
* اجرای دوباره بی‌خطر و بدون ساخت تصویر تکراری است.

== تغییرات ==

= %(version)s =
* شکستن بستهٔ ۵۵ تصویری به دو بستهٔ کوچک‌تر (سبک‌شدن نصب و اجرا)
* اجرای مرحله‌ای خودکار با سقف ۸ ثانیه در هر درخواست (اندازهٔ مرحله خودکار تنظیم می‌شود)، ادامه از محل قطع
* پیش‌نمایش سریع‌تر (بارگذاری تنبل تصاویر و رد شدن محاسبهٔ سنگین در میانهٔ اجرا)

= 1.1.0 =
* متن جانشین (alt) اختصاصی و استاندارد گوگل برای هر تصویر + کلمهٔ کلیدی مقاله

= 1.0.0 =
* نسخهٔ نخست: تصاویر ۱۶:۹ WebP، بازنویسی/جانشینی، حذف تصویر قبلی
""" % dict(num=num, total=PARTS_TOTAL, count=len(rows), version=VERSION,
           fa_num=fa_num(num), fa_total=fa_num(PARTS_TOTAL), fa_count=fa_num(len(rows)),
           fa_batch=fa_num(BATCH_SIZE), fa_articles=fa_num(ARTICLES_TOTAL))


def build_part(cfg, rows):
    num = cfg['num']
    p = cfg['prefix']
    out = os.path.join(HERE, 'part-%d' % num, 'qp-featured-images-%d' % num)
    if os.path.isdir(os.path.join(HERE, 'part-%d' % num)):
        shutil.rmtree(os.path.join(HERE, 'part-%d' % num))

    # فایل اصلی
    main = transform(read(os.path.join(HERE, 'template', 'qp-glossary-featured-images.php')), cfg)
    main = set_const(main, p.upper() + '_VERSION', VERSION)
    main = set_const(main, p.upper() + '_PART', num, quote=False)
    main = set_const(main, p.upper() + '_PARTS_TOTAL', PARTS_TOTAL, quote=False)
    main = set_const(main, p.upper() + '_ARTICLES_TOTAL', ARTICLES_TOTAL, quote=False)
    main = set_const(main, p.upper() + '_OTHER_PREFIX', cfg['other'])
    main = set_const(main, p.upper() + '_BATCH_SIZE', BATCH_SIZE, quote=False)
    main = header_and_docs(main, cfg, rows)
    write(os.path.join(out, 'qp-featured-images-%d.php' % num), main)

    # includes
    for name in ('replacer.php', 'admin.php'):
        body = transform(read(os.path.join(HERE, 'template', 'includes', name)), cfg)
        write(os.path.join(out, 'includes', name), body)

    write(os.path.join(out, 'includes', 'map.php'), part_map(rows, cfg))

    # تصاویر
    for row in rows:
        f = row_file(row)
        src = os.path.join(HERE, 'images', f)
        if not os.path.exists(src):
            raise SystemExit('تصویر نیست: %s' % f)
        dst = os.path.join(out, 'includes', 'images', f)
        os.makedirs(os.path.dirname(dst), exist_ok=True)
        shutil.copyfile(src, dst)

    write(os.path.join(out, 'readme.txt'), part_readme(rows, cfg))

    # زیپ نصب
    zip_path = os.path.join(REPO, 'qp-featured-images-%d-of-%d-%s.zip' % (num, PARTS_TOTAL, VERSION))
    if os.path.exists(zip_path):
        os.remove(zip_path)
    with zipfile.ZipFile(zip_path, 'w', zipfile.ZIP_DEFLATED) as z:
        base = os.path.join(HERE, 'part-%d' % num)
        for root, _dirs, files in os.walk(base):
            for f in sorted(files):
                full = os.path.join(root, f)
                z.write(full, os.path.relpath(full, base))

    return out, zip_path


def main():
    rows = map_rows()
    part1, part2, total = split_rows(rows)

    print('ردیف‌ها: %d | بستهٔ ۱: %d | بستهٔ ۲: %d | حجم تصاویر: %.2f مگابایت'
          % (len(rows), len(part1), len(part2), total / 1024.0 / 1024.0))

    slugs1 = set(row_slug(r) for r in part1)
    slugs2 = set(row_slug(r) for r in part2)
    if slugs1 & slugs2:
        raise SystemExit('اسلاگ تکراری بین دو بسته: %s' % (slugs1 & slugs2))
    if len(slugs1) + len(slugs2) != len(rows):
        raise SystemExit('پوشش کامل نیست')

    for cfg, rows_part in ((PART_CONF[1], part1), (PART_CONF[2], part2)):
        out, zip_path = build_part(cfg, rows_part)
        print('بستهٔ %d: %s' % (cfg['num'], os.path.relpath(out, HERE)))
        print('   زیپ: %s (%.2f مگابایت)' % (os.path.relpath(zip_path, REPO),
                                             os.path.getsize(zip_path) / 1024.0 / 1024.0))

    # زیپ نسخهٔ قبلی باید برداشته شود
    old = os.path.join(REPO, 'qp-featured-images-1.1.0.zip')
    if os.path.exists(old):
        os.remove(old)
        print('زیپ قدیمی 1.1.0 حذف شد')

    # بررسی نبود پیشوند اشتباه در کدهای هر بسته
    bad = []
    for num, other in ((1, 'qpfb'), (2, 'qpfa')):
        base = os.path.join(HERE, 'part-%d' % num, 'qp-featured-images-%d' % num)
        main_file = 'qp-featured-images-%d.php' % num
        for root, _dirs, files in os.walk(base):
            for f in files:
                if not f.endswith('.php'):
                    continue
                text = read(os.path.join(root, f))
                # توکن‌های مشترک بین دو بسته عمداً دست‌نخورده‌اند
                text = text.replace('_qpfi_', '').replace('qp-fi-', '').replace('qpfi_alt_text', '')
                for token in ('QPFI_', 'qpfi_'):
                    if re.search(r'(?<![A-Za-z0-9_])%s' % re.escape(token), text):
                        bad.append('%s/%s → %s' % (os.path.basename(base), f, token))
                # پیشوند بستهٔ دیگر فقط برای مقدار ثابت OTHER_PREFIX مجاز است
                if f != main_file and re.search(r'(?<![A-Za-z0-9_])%s' % re.escape(other), text):
                    bad.append('%s/%s → %s' % (os.path.basename(base), f, other))
    print('پیشوند اشتباه: %s' % (sorted(set(bad)) or 'ندارد'))
    return 0 if not bad else 1


if __name__ == '__main__':
    sys.exit(main())
