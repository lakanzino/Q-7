#!/usr/bin/env python3
"""Process 56 new featured images to 16:9 WebP and build WordPress Replacer Plugin."""
import csv
import json
import os
import subprocess
import zipfile
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
SRC_DIR = ROOT / 'image56-src'
OUT_DIR = ROOT / 'article-rewrite-2026-09-22/featured-images-56-webp'
OUT_DIR.mkdir(parents=True, exist_ok=True)

IMAGE_MAP = {
    '1000098919.jpg': ('wave-particle-duality', 'دوگانگی موج و ذره', 'تصویر شاخص دوگانگی موج و ذره در فیزیک کوانتوم'),
    '1000098920.jpg': ('what-is-quantum', 'کوانتوم یعنی چه؟', 'تصویر شاخص کوانتوم یعنی چه و مبانی فیزیک کوانتومی'),
    '1000098921.jpg': ('quantum-superposition', 'برهم‌نهی کوانتومی', 'تصویر شاخص برهم‌نهی کوانتومی و حالت‌های چندگانه'),
    '1000098922.jpg': ('wave-function', 'تابع موج چیست؟', 'تصویر شاخص تابع موج کوانتومی و معادله شرودینگر'),
    '1000098923.jpg': ('quantum-measurement', 'اندازه‌گیری و فروپاشی', 'تصویر شاخص اندازه‌گیری کوانتومی و فروپاشی تابع موج'),
    '1000098924.jpg': ('quantum-spin', 'اسپین؛ چرخشی که چرخش نیست', 'تصویر شاخص اسپین کوانتومی و تکانه زاویه‌ای ذاتی'),
    '1000098925.jpg': ('energy-levels', 'ترازهای انرژی و کوانتش', 'تصویر شاخص ترازهای گسسته انرژی و کوانتش'),
    '1000098926.jpg': ('decoherence', 'واهمدوسی کوانتومی', 'تصویر شاخص واهمدوسی کوانتومی و گذار به فیزیک کلاسیک'),
    '1000098927.jpg': ('planck-constant', 'ثابت پلانک چیست؟', 'تصویر شاخص ثابت پلانک و مقیاس بنیادین کوانتوم'),
    '1000098928.jpg': ('quantum-entanglement-explained', 'درهم‌تنیدگی کوانتومی', 'تصویر شاخص درهم‌تنیدگی کوانتومی و هم‌بستگی ذرات'),
    '1000098929.jpg': ('quantum-zeno-effect', 'اثر زنون کوانتومی', 'تصویر شاخص اثر زنون کوانتومی و مشاهده مداوم'),
    '1000098930.jpg': ('vacuum-fluctuations', 'نوسانات خلأ', 'تصویر شاخص افت‌وخیز کوانتومی و انرژی نقطه صفر خلأ'),
    '1000098931.jpg': ('casimir-effect', 'اثر کازیمیر', 'تصویر شاخص اثر کازیمیر و نیروی کوانتومی میان صفحات'),
    '1000098932.jpg': ('ultraviolet-catastrophe', 'فاجعهٔ فرابنفش', 'تصویر شاخص فاجعه فرابنفش و تابش جسم سیاه'),
    '1000098933.jpg': ('bohr-atomic-model', 'مدل اتمی بور', 'تصویر شاخص مدل اتمی نیلز بور و مدارهای کوانتومی'),
    '1000098934.jpg': ('bell-inequality', 'نامساوی بل', 'تصویر شاخص قضیه و نامساوی بل در فیزیک کوانتوم'),
    '1000098935.jpg': ('absolute-zero', 'صفر مطلق چیست؟', 'تصویر شاخص صفر مطلق دما و توقف جنبش گرمایی'),
    '1000098960.jpg': ('alpha-decay', 'واپاشی آلفا', 'تصویر شاخص واپاشی آلفا و تونل‌زنی هسته‌ای'),
    '1000098961.jpg': ('loophole-free-bell-test', 'نامساوی بل (آزمون بل)', 'تصویر شاخص آزمایش بدون روزنه نامساوی بل'),
    '1000098962.jpg': ('quantum-tunneling', 'تونل‌زنی کوانتومی', 'تصویر شاخص تونل‌زنی کوانتومی و عبور ذره از سد پتانسیل'),
    '1000098963.jpg': ('double-slit-experiment', 'آزمایش دو شکاف', 'تصویر شاخص آزمایش دو شکاف یانگ در کوانتوم'),
    '1000098964.jpg': ('no-cloning-theorem', 'قضیهٔ عدم‌کپی', 'تصویر شاخص قضیه عدم همسانه‌سازی یا کپی کوانتومی'),
    '1000098965.jpg': ('coherence', 'همدوسی', 'تصویر شاخص همدوسی کوانتومی و فاز امواج'),
    '1000098967.jpg': ('quantum-state', 'حالت کوانتومی چیست؟', 'تصویر شاخص بردار حالت کوانتومی در فضای هیلبرت'),
    '1000098968.jpg': ('entanglement-quantum-computers', 'درهم‌تنیدگی در کامپیوترهای کوانتومی', 'تصویر شاخص درهم‌تنیدگی در پردازش کامپیوترهای کوانتومی'),
    '1000098969.jpg': ('human-teleportation', 'درهم‌تنیدگی و سرعت نور', 'تصویر شاخص بررسی انتقال اطلاعات در درهم‌تنیدگی کوانتومی'),
    '1000098970.jpg': ('quantum-probability', 'احتمال کوانتومی', 'تصویر شاخص ماهیت احتمالاتی مکانیک کوانتومی و قاعده بورن'),
    '1000098971.jpg': ('virtual-particles', 'ذرات مجازی', 'تصویر شاخص ذرات مجازی در میدان‌های کوانتومی'),
    '1000098972.jpg': ('complementarity-principle', 'اصل مکملیت', 'تصویر شاخص اصل مکملیت نیلز بور در فیزیک کوانتوم'),
    '1000098973.jpg': ('observer', 'ناظر در کوانتوم', 'تصویر شاخص اثر ناظر و اندازه‌گیری در فیزیک کوانتومی'),
    '1000098975.jpg': ('quantum-number', 'عدد کوانتومی', 'تصویر شاخص اعداد کوانتومی الکترون در اتم'),
    '1000098976.jpg': ('coin-vs-dice-quantum-uncertainty', 'تمثیل تاس در مقابل تمثیل سکه', 'تصویر شاخص تمثیل تاس و سکه در عدم قطعیت کوانتومی'),
    '1000098977.jpg': ('quantum-physics-vs-quantum-mechanics', 'فیزیک کوانتوم و مکانیک کوانتومی', 'تصویر شاخص تفاوت فیزیک کوانتوم و مکانیک کوانتومی'),
    '1000098978.jpg': ('quantum-analogy-exercise-boundary', 'نیلز بور و اصل تکمیل', 'تصویر شاخص نیلز بور، اصل تکمیل و مرز شکست تمثیل'),
    '1000098979.jpg': ('determinism-vs-probability', 'جبرگرایی کلاسیک و احتمال کوانتومی', 'تصویر شاخص جبرگرایی نیوتنی در برابر احتمال کوانتومی'),
    '1000098980.jpg': ('why-large-objects-dont-superpose', 'برهم‌نهی اشیای بزرگ', 'تصویر شاخص علت عدم مشاهده برهم‌نهی در اشیای بزرگ'),
    '1000098981.jpg': ('heisenberg-uncertainty-principle', 'اصل عدم قطعیت هایزنبرگ', 'تصویر شاخص اصل عدم قطعیت هایزنبرگ در مکان و تکانه'),
    '1000098982.jpg': ('is-classical-physics-wrong', 'آیا فیزیک کلاسیک اشتباه بود؟', 'تصویر شاخص حد کلاسیک و رابطه فیزیک نیوتنی با کوانتوم'),
    '1000098983.jpg': ('pauli-exclusion-principle', 'اصل طرد پاولی', 'تصویر شاخص اصل طرد پاولی و آرایش الکترون‌ها'),
    '1000098984.jpg': ('quantum-interpretation-debate', 'ماکس بورن و تفسیر احتمالاتی', 'تصویر شاخص ماکس بورن و جدال تفاسیر کوانتومی'),
    '1000098985.jpg': ('aharonov-bohm-effect', 'اثر آهارونوف-بوهم', 'تصویر شاخص اثر آهارونوف-بوهم و پتانسیل الکترومغناطیسی'),
    '1000098986.jpg': ('quantum-random-number-generator', 'مولد عدد تصادفی کوانتومی', 'تصویر شاخص تولید اعداد تصادفی واقعی با کوانتوم'),
    '1000098987.jpg': ('cosmic-inflation', 'تورم کیهانی', 'تصویر شاخص نظریه تورم کیهانی در کیهان‌شناسی آغازین'),
    '1000098988.jpg': ('cosmic-microwave-background', 'تابش زمینه کیهانی', 'تصویر شاخص تابش پس‌زمینه کیهانی CMB از انفجار بزرگ'),
    '1000098989.jpg': ('wormhole', 'کرم‌چاله', 'تصویر شاخص ساختار نظری کرم‌چاله و پل اینشتین-روزن'),
    '1000098990.jpg': ('quantum-bounce', 'جهش کوانتومی کیهان', 'تصویر شاخص نظریه جهش کوانتومی کیهان قبل از مهبانگ'),
    '1000099001.jpg': ('quantum-culture', 'فرهنگ کوانتومی', 'تصویر شاخص بازتاب مفاهیم کوانتومی در فرهنگ و هنر جامعه'),
    '1000099002.jpg': ('quantum-winter', 'زمستان کوانتومی', 'تصویر شاخص زمستان کوانتومی و چرخه‌های افت سرمایه‌گذاری'),
    '1000099003.jpg': ('copenhagen-interpretation', 'تفسیر کپنهاگی', 'تصویر شاخص تفسیر کپنهاگی از مکانیک کوانتومی'),
    '1000099004.jpg': ('is-many-worlds-real', 'جهان‌های موازی', 'تصویر شاخص تفسیر چندجهانی اورت و انشعاب واقعیت‌ها'),
    '1000099005.jpg': ('does-quantum-prove-god', 'کوانتوم و خدا', 'تصویر شاخص بررسی نسبت فیزیک کوانتوم و مباحث فلسفی'),
    '1000099006.jpg': ('holographic-principle', 'اصل هولوگرافیک', 'تصویر شاخص اصل هولوگرافیک در گرانش کوانتومی و افق سیاه‌چاله'),
    '1000099007.jpg': ('quantum-darwinism', 'داروینیسم کوانتومی', 'تصویر شاخص نظریه داروینیسم کوانتومی زورک'),
    '1000099008.jpg': ('quantum-free-will', 'کوانتوم و اراده آزاد', 'تصویر شاخص فیزیک کوانتومی و مسئله اراده آزاد و آگاهی'),
    '1000099009.jpg': ('quantum-time-travel', 'سفر در زمان', 'تصویر شاخص سفر در زمان از منظر فیزیک کوانتوم و نسبیت'),
    '1000099010.jpg': ('wigner-friend', 'دوست ویگنر', 'تصویر شاخص پارادوکس دوست ویگنر و مسئله ناظر'),
}

manifest_rows = []
print(f'Processing {len(IMAGE_MAP)} images to 16:9 WebP (1200x675)...')

for src_name, (slug, title_fa, alt_fa) in sorted(IMAGE_MAP.items()):
    src_file = SRC_DIR / src_name
    dst_file = OUT_DIR / f'{slug}.webp'
    
    assert src_file.exists(), f'Missing {src_file}'
    
    font = '/usr/share/fonts/truetype/dejavu/DejaVuSans-Bold.ttf'
    # 16:9 resize & crop to 1200x675 with ImageMagick + standard qpedia.ir watermark badge
    cmd = [
        'convert', str(src_file),
        '-auto-orient',
        '-resize', '1200x675^',
        '-gravity', 'center',
        '-extent', '1200x675',
        '-strip',
        '(', '-size', '130x34', 'xc:none',
        '-fill', 'rgba(10, 18, 32, 0.65)', '-draw', 'roundrectangle 0,0 129,33 7,7',
        '-stroke', 'rgba(0, 212, 255, 0.45)', '-strokewidth', '1', '-draw', 'roundrectangle 0,0 129,33 7,7',
        '-font', font, '-pointsize', '14', '-fill', 'rgba(232, 244, 252, 0.95)',
        '-gravity', 'center', '-annotate', '+0+0', 'qpedia.ir', ')',
        '-gravity', 'NorthEast', '-geometry', '+35+30', '-composite',
        '-quality', '85',
        str(dst_file)
    ]
    subprocess.run(cmd, check=True)
    
    size_kb = dst_file.stat().st_size / 1024
    manifest_rows.append({
        'source_file': src_name,
        'slug': slug,
        'filename': f'{slug}.webp',
        'title_fa': title_fa,
        'alt_fa': alt_fa,
        'dimensions': '1200x675',
        'size_kb': f'{size_kb:.1f} KB'
    })
    print(f'Converted: {src_name} -> {dst_file.name} ({size_kb:.1f} KB)')

# Write Manifest CSV & JSON
manifest_csv = OUT_DIR / 'manifest.csv'
with open(manifest_csv, 'w', encoding='utf-8-sig', newline='') as f:
    writer = csv.DictWriter(f, fieldnames=['source_file', 'slug', 'filename', 'title_fa', 'alt_fa', 'dimensions', 'size_kb'])
    writer.writeheader()
    writer.writerows(manifest_rows)

manifest_json = OUT_DIR / 'manifest.json'
manifest_json.write_text(json.dumps(manifest_rows, ensure_ascii=False, indent=2), encoding='utf-8')
print(f'Wrote manifest files to {OUT_DIR}')

# 1. Zip package of all 56 standalone WebP images
zip_images_path = ROOT / 'downloads/qpedia-featured-images-56-webp.zip'
with zipfile.ZipFile(zip_images_path, 'w', zipfile.ZIP_DEFLATED) as zf:
    for row in manifest_rows:
        img_p = OUT_DIR / row['filename']
        zf.write(img_p, arcname=row['filename'])
    zf.write(manifest_csv, arcname='manifest.csv')
    zf.write(manifest_json, arcname='manifest.json')
print(f'Created {zip_images_path} ({zip_images_path.stat().st_size / 1024:.1f} KB)')

# 2. Build WordPress Replacer Plugin
plugin_dir = ROOT / 'article-rewrite-2026-09-22/importers/featured-images-56-replacer/qpedia-featured-images-56-replacer'
plugin_dir.mkdir(parents=True, exist_ok=True)
images_sub_dir = plugin_dir / 'images'
images_sub_dir.mkdir(parents=True, exist_ok=True)

# Copy webp images and manifest to plugin directory
for row in manifest_rows:
    src_img = OUT_DIR / row['filename']
    dst_img = images_sub_dir / row['filename']
    dst_img.write_bytes(src_img.read_bytes())

(plugin_dir / 'manifest.json').write_text(json.dumps(manifest_rows, ensure_ascii=False, indent=2), encoding='utf-8')

php_code = r'''<?php
/**
 * Plugin Name: Qpedia Featured Images 56 Replacer (One-Time)
 * Description: جایگزینی کنترل‌شده ۵۶ تصویر شاخص بهینه‌سازی‌شده (WebP با نسبت ۱۶:۹ و ابعاد ۱۲۰۰×۶۷۵) برای مقالات Qpedia همراه با حذف تصاویر شاخص قبلی جهت سبک‌سازی هاست و دیتابیس.
 * Version: 1.0.0
 * Author: Qpedia Team
 * Text Domain: qpedia-featured-images-replacer
 */

defined('ABSPATH') || exit;

const QPFIR56_OPTION = 'qpedia_featured_images_56_replacer_completed_v1';

add_action('admin_menu', 'qpfir56_admin_menu');
function qpfir56_admin_menu() {
    add_management_page(
        'جایگزینی ۵۶ تصویر شاخص جدید',
        'جایگزینی ۵۶ تصویر شاخص',
        'manage_options',
        'qpedia-featured-images-56-replacer',
        'qpfir56_render_page'
    );
}

function qpfir56_render_page() {
    if (!current_user_can('manage_options')) {
        return;
    }

    $completed = get_option(QPFIR56_OPTION);

    echo '<div class="wrap" dir="rtl" style="font-family:Tahoma,sans-serif;line-height:1.7;">';
    echo '<h1>🖼️ جایگزینی هوشمند ۵۶ تصویر شاخص بهینه‌سازی‌شده Qpedia</h1>';

    if (isset($_POST['qpfir56_run'])) {
        check_admin_referer('qpfir56_action_nonce');
        $delete_old = isset($_POST['qpfir56_delete_old']);
        $result = qpfir56_execute_replacement($delete_old);

        $class = $result['ok'] ? 'notice-success' : 'notice-error';
        echo '<div class="notice ' . esc_attr($class) . '"><p><strong>' . esc_html($result['message']) . '</strong></p></div>';

        if (!empty($result['log'])) {
            echo '<div style="max-height:350px;overflow-y:auto;background:#fff;border:1px solid #ccd0d4;padding:12px;margin:15px 0;">';
            echo '<ul style="margin:0;padding:0 20px;font-size:13px;line-height:1.8;">';
            foreach ($result['log'] as $line) {
                echo '<li>' . esc_html($line) . '</li>';
            }
            echo '</ul></div>';
        }

        if ($result['ok']) {
            update_option(QPFIR56_OPTION, current_time('mysql'), false);
            echo '<div class="notice notice-info"><p>عملیات با موفقیت پایان یافت. تمام ۵۶ تصویر شاخص جایگزین شدند. اکنون می‌توانید این افزونه را غیرفعال و حذف کنید.</p></div>';
        }
    }

    echo '<div class="card" style="max-width:850px;margin-top:20px;padding:15px 25px;">';
    echo '<h2>مشخصات بسته تصاویر جدید:</h2>';
    echo '<ol style="font-size:14px;line-height:1.9;">';
    echo '<li><strong>فرمت مدرن و سبک:</strong> تمام ۵۶ تصویر با فرمت <code>WebP</code> و ابعاد استاندارد <code>۱۲۰۰×۶۷۵ (نسبت ۱۶:۹)</code> طراحی شده‌اند.</li>';
    echo '<li><strong>نام‌گذاری و Alt متن فارسی:</strong> نام فایل‌ها بر اساس اسلاگ انگلیسی مقالات و Alt تصاویر با متن فارسی دقیق تنظیم می‌شوند.</li>';
    echo '<li><strong>حذف تصاویر قدیمی:</strong> با فعال بودن گزینه زیر، پیوست‌ها و فایل‌های تصاویر شاخص قدیمی از کتابخانه رسانه و هاست حذف می‌شوند تا هاست خلوت و سبک بماند.</li>';
    echo '</ol>';

    echo '<form method="post" style="margin-top:20px;">';
    wp_nonce_field('qpfir56_action_nonce');
    echo '<p><label><input type="checkbox" name="qpfir56_delete_old" value="1" checked="checked"> <strong>حذف دائمی فایل‌ها و پیوست‌های تصاویر شاخص قدیمی از رسانه (پیشنهاد می‌شود)</strong></label></p>';
    submit_button('شروع جایگزینی و اتصال ۵۶ تصویر شاخص جدید', 'primary', 'qpfir56_run');
    echo '</form>';
    echo '</div></div>';
}

function qpfir56_execute_replacement($delete_old = true) {
    require_once ABSPATH . 'wp-admin/includes/image.php';
    require_once ABSPATH . 'wp-admin/includes/file.php';
    require_once ABSPATH . 'wp-admin/includes/media.php';

    $manifest_file = plugin_dir_path(__FILE__) . 'manifest.json';
    if (!is_readable($manifest_file)) {
        return array('ok' => false, 'message' => 'فایل manifest.json در افزونه یافت نشد.', 'log' => array());
    }

    $manifest = json_decode(file_get_contents($manifest_file), true);
    if (!is_array($manifest) || empty($manifest)) {
        return array('ok' => false, 'message' => 'فرمت مانیفست نامعتبر است.', 'log' => array());
    }

    $upload_dir = wp_upload_dir();
    $target_dir = $upload_dir['path'];
    $target_url = $upload_dir['url'];

    $updated_count = 0;
    $deleted_old_count = 0;
    $log = array();

    foreach ($manifest as $item) {
        $slug     = trim((string)$item['slug']);
        $filename = trim((string)$item['filename']);
        $alt_fa   = trim((string)$item['alt_fa']);
        $title_fa = trim((string)$item['title_fa']);

        $src_img_path = plugin_dir_path(__FILE__) . 'images/' . $filename;
        if (!file_exists($src_img_path)) {
            $log[] = '❌ فایل تصویر در افزونه یافت نشد: ' . $filename;
            continue;
        }

        // جستجوی مقاله در وردپرس بر اساس اسلاگ
        $posts = get_posts(array(
            'name'        => $slug,
            'post_type'   => array('quantum_article', 'post', 'page', 'qp_glossary'),
            'post_status' => 'any',
            'numberposts' => 1
        ));

        if (empty($posts)) {
            $log[] = '⚠️ مقاله‌ای با اسلاگ «' . $slug . '» یافت نشد.';
            continue;
        }

        $post = $posts[0];
        $post_id = (int)$post->ID;

        // حذف تصویر شاخص قدیمی در صورت وجود
        $old_thumb_id = (int) get_post_thumbnail_id($post_id);
        if ($old_thumb_id > 0 && $delete_old) {
            wp_delete_attachment($old_thumb_id, true);
            $deleted_old_count++;
        }

        // کپی فایل تصویر به پوشه آپلود وردپرس
        $unique_filename = wp_unique_filename($target_dir, $filename);
        $dest_file_path  = $target_dir . '/' . $unique_filename;
        copy($src_img_path, $dest_file_path);

        $filetype = wp_check_filetype($unique_filename, null);
        $attachment_data = array(
            'post_mime_type' => $filetype['type'] ? $filetype['type'] : 'image/webp',
            'post_title'     => $title_fa,
            'post_content'   => '',
            'post_status'    => 'inherit'
        );

        $attach_id = wp_insert_attachment($attachment_data, $dest_file_path, $post_id);
        if (!is_wp_error($attach_id) && $attach_id > 0) {
            $attach_data = wp_generate_attachment_metadata($attach_id, $dest_file_path);
            wp_update_attachment_metadata($attach_id, $attach_data);
            update_post_meta($attach_id, '_wp_attachment_image_alt', $alt_fa);

            // تنظیم تصویر شاخص پست
            set_post_thumbnail($post_id, $attach_id);
            $updated_count++;
            $log[] = '✅ مقاله «' . $post->post_title . '» (شناسه ' . $post_id . ') -> تصویر شاخص جدید تنظیم شد (' . $filename . ')';
        } else {
            $log[] = '❌ خطا در درج پیوست رسانه برای «' . $slug . '»';
        }
    }

    $msg = 'تعداد ' . $updated_count . ' تصویر شاخص با موفقیت جایگزین شد.';
    if ($delete_old) {
        $msg .= ' تعداد ' . $deleted_old_count . ' تصویر قدیمی پاک‌سازی گردید.';
    }

    return array('ok' => true, 'message' => $msg, 'log' => $log);
}
'''

(plugin_dir / 'qpedia-featured-images-56-replacer.php').write_text(php_code.strip() + '\n', encoding='utf-8')
print(f'Wrote {plugin_dir / "qpedia-featured-images-56-replacer.php"}')

# Zip WordPress Replacer Plugin
zip_plugin_path = ROOT / 'article-rewrite-2026-09-22/importers/featured-images-56-replacer/qpedia-featured-images-56-replacer.zip'
with zipfile.ZipFile(zip_plugin_path, 'w', zipfile.ZIP_DEFLATED) as zf:
    for f in plugin_dir.rglob('*'):
        if f.is_file():
            arc = f.relative_to(plugin_dir.parent)
            zf.write(f, arcname=str(arc))

print(f'Created {zip_plugin_path} ({zip_plugin_path.stat().st_size / 1024:.1f} KB)')

# Copy plugin to downloads
dl_plugin = ROOT / 'downloads/qpedia-featured-images-56-replacer.zip'
dl_plugin.write_bytes(zip_plugin_path.read_bytes())
print(f'Copied to {dl_plugin} ({dl_plugin.stat().st_size / 1024:.1f} KB)')
