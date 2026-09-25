#!/usr/bin/env python3
import json
import os
import shutil
import subprocess
import zipfile
from pathlib import Path

ROOT = Path('/home/user/Q-7')
IMPORTER_DIR = ROOT / 'article-rewrite-2026-09-22/importers/qpedia-featured-images-special-6-replacer'
IMPORTER_DIR.mkdir(parents=True, exist_ok=True)
IMG_SUBDIR = IMPORTER_DIR / 'images'
IMG_SUBDIR.mkdir(parents=True, exist_ok=True)
DL_DIR = ROOT / 'downloads'
DL_DIR.mkdir(parents=True, exist_ok=True)

special_6 = [
    {
        "slug": "quantum-time-travel",
        "title": "سفر در زمان",
        "filename": "quantum-time-travel.webp",
        "alt": "تصویر شاخص سفر در زمان از منظر فیزیک کوانتوم و نسبیت با نشان qpedia.ir"
    },
    {
        "slug": "quantum-tunneling",
        "title": "تونل‌زنی کوانتومی",
        "filename": "quantum-tunneling.webp",
        "alt": "تصویر شاخص تونل‌زنی کوانتومی و عبور ذره از سد پتانسیل با نشان qpedia.ir"
    },
    {
        "slug": "quantum-superposition",
        "title": "برهم‌نهی کوانتومی",
        "filename": "quantum-superposition.webp",
        "alt": "تصویر شاخص برهم‌نهی کوانتومی و حالت‌های چندگانه با نشان qpedia.ir"
    },
    {
        "slug": "quantum-state",
        "title": "حالت کوانتومی چیست؟",
        "filename": "quantum-state.webp",
        "alt": "تصویر شاخص بردار حالت کوانتومی در فضای هیلبرت با نشان qpedia.ir"
    },
    {
        "slug": "quantum-spin",
        "title": "اسپین؛ چرخشی که چرخش نیست",
        "filename": "quantum-spin.webp",
        "alt": "تصویر شاخص اسپین کوانتومی و تکانه زاویه‌ای ذاتی با نشان qpedia.ir"
    },
    {
        "slug": "quantum-random-number-generator",
        "title": "مولد عدد تصادفی کوانتومی",
        "filename": "quantum-random-number-generator.webp",
        "alt": "تصویر شاخص تولید اعداد تصادفی واقعی با کوانتوم با نشان qpedia.ir"
    }
]

# Copy and optimize the 6 images
src_dir = ROOT / 'article-rewrite-2026-09-22/all-featured-images-watermarked'
for item in special_6:
    fn = item['filename']
    s_path = src_dir / fn
    d_path = IMG_SUBDIR / fn
    # Run convert optimization
    cmd = [
        'convert', str(s_path),
        '-unsharp', '0x0.5+0.8+0.005',
        '-strip',
        '-quality', '85',
        '-define', 'webp:method=6',
        '-define', 'webp:auto-filter=true',
        str(d_path)
    ]
    subprocess.check_call(cmd)
    # Also update in gallery and watermarked folders
    shutil.copy2(d_path, s_path)
    shutil.copy2(d_path, ROOT / 'gallery/images' / fn)

# Save images_data.json
with open(IMPORTER_DIR / 'images_data.json', 'w', encoding='utf-8') as f:
    json.dump(special_6, f, ensure_ascii=False, indent=2)

# Write PHP plugin
php_code = '''<?php
/**
 * Plugin Name: Qpedia Featured Images - Special 6 Replacer
 * Description: افزونه اختصاصی بهینه‌سازی و جایگزینی فوری ۶ تصویر شاخص منتخب (سفر در زمان، تونل‌زنی، برهم‌نهی، حالت کوانتومی، اسپین و مولد اعداد تصادفی) با نشان استاندارد qpedia.ir و متادیتاهای سئو در وردپرس.
 * Version: 1.0.0
 * Author: Qpedia Team
 * Text Domain: qpedia-featured-images-special-6-replacer
 */

defined('ABSPATH') || exit;

add_action('admin_menu', 'qp_special6_menu');
function qp_special6_menu() {
    add_management_page(
        'جایگزینی ۶ تصویر شاخص منتخب',
        'تصاویر شاخص منتخب (6)',
        'manage_options',
        'qpedia-special-6-replacer',
        'qp_special6_render_page'
    );
}

function qp_special6_render_page() {
    if (!current_user_can('manage_options')) {
        return;
    }

    echo '<div class="wrap" dir="rtl" style="font-family:Tahoma,sans-serif;line-height:1.7;">';
    echo '<h1>✨ بهینه‌سازی و جایگزینی ۶ تصویر شاخص منتخب</h1>';

    if (isset($_POST['qp_run_special6'])) {
        check_admin_referer('qp_special6_nonce');
        $result = qp_special6_execute();

        $class = $result['ok'] ? 'notice-success' : 'notice-error';
        echo '<div class="notice ' . esc_attr($class) . '"><p><strong>' . esc_html($result['message']) . '</strong></p></div>';

        if (!empty($result['log'])) {
            echo '<div style="max-height:400px;overflow-y:auto;background:#fff;border:1px solid #ccd0d4;padding:12px;margin:15px 0;border-radius:6px;">';
            echo '<ul style="margin:0;padding:0 20px;font-size:13px;line-height:1.8;">';
            foreach ($result['log'] as $line) {
                echo '<li>' . esc_html($line) . '</li>';
            }
            echo '</ul></div>';
        }
    }

    echo '<div class="card" style="max-width:850px;margin-top:20px;padding:20px 25px;border-radius:8px;box-shadow:0 2px 8px rgba(0,0,0,0.08);">';
    echo '<h2>فهرست ۶ تصویر شاخص منتخب:</h2>';
    echo '<ol style="margin-right:20px;font-size:14px;line-height:2;">';
    echo '<li><strong>سفر در زمان</strong> (اسلاگ: <code>quantum-time-travel</code>)</li>';
    echo '<li><strong>تونل‌زنی کوانتومی</strong> (اسلاگ: <code>quantum-tunneling</code>)</li>';
    echo '<li><strong>برهم‌نهی کوانتومی</strong> (اسلاگ: <code>quantum-superposition</code>)</li>';
    echo '<li><strong>حالت کوانتومی چیست؟</strong> (اسلاگ: <code>quantum-state</code>)</li>';
    echo '<li><strong>اسپین؛ چرخشی که چرخش نیست</strong> (اسلاگ: <code>quantum-spin</code>)</li>';
    echo '<li><strong>مولد عدد تصادفی کوانتومی</strong> (اسلاگ: <code>quantum-random-number-generator</code>)</li>';
    echo '</ol>';

    echo '<p style="color:#555;margin-top:15px;">با کلیک روی دکمه زیر، فایل‌های تصویر بهینه‌شده به صورت خودکار در کتابخانه رسانه وردپرس آپلود شده و به عنوان <strong>تصویر شاخص (Featured Image) با متای Alt سئو</strong> به این ۶ مقاله متصل و جایگزین می‌شوند.</p>';

    echo '<form method="post" style="margin-top:20px;">';
    wp_nonce_field('qp_special6_nonce');
    submit_button('اجرای فوری و جایگزینی ۶ تصویر شاخص منتخب', 'primary', 'qp_run_special6');
    echo '</form>';
    echo '</div></div>';
}

function qp_special6_execute() {
    $data_file = plugin_dir_path(__FILE__) . 'images_data.json';
    if (!is_readable($data_file)) {
        return array('ok' => false, 'message' => 'فایل متادیتا یافت نشد.', 'log' => array());
    }

    $items = json_decode(file_get_contents($data_file), true);
    if (!is_array($items) || empty($items)) {
        return array('ok' => false, 'message' => 'فرمت متادیتا نامعتبر است.', 'log' => array());
    }

    $count_ok = 0;
    $count_miss = 0;
    $log = array();

    $upload_dir = wp_upload_dir();
    require_once(ABSPATH . 'wp-admin/includes/image.php');

    foreach ($items as $item) {
        $slug      = trim((string)$item['slug']);
        $title     = trim((string)$item['title']);
        $filename  = basename((string)$item['filename']);
        $alt_text  = trim((string)$item['alt']);

        $posts = get_posts(array(
            'name'        => $slug,
            'post_type'   => array('quantum_article', 'post'),
            'post_status' => 'any',
            'numberposts' => 1
        ));

        if (empty($posts)) {
            $log[] = '⚠️ مقاله با اسلاگ «' . $slug . '» در سایت یافت نشد.';
            $count_miss++;
            continue;
        }

        $post_id = (int)$posts[0]->ID;
        $img_src = plugin_dir_path(__FILE__) . 'images/' . $filename;

        if (!file_exists($img_src)) {
            $log[] = '❌ فایل تصویر «' . $filename . '» در پوشه افزونه موجود نیست.';
            $count_miss++;
            continue;
        }

        $target_file = $upload_dir['path'] . '/' . $filename;
        if (!file_exists($target_file)) {
            wp_mkdir_p($upload_dir['path']);
            copy($img_src, $target_file);
        }

        $base_name = pathinfo($filename, PATHINFO_FILENAME);
        $existing_attach = get_posts(array(
            'post_type'      => 'attachment',
            'name'           => $base_name,
            'posts_per_page' => 1,
            'post_status'    => 'inherit'
        ));

        if (!empty($existing_attach)) {
            $attach_id = (int)$existing_attach[0]->ID;
        } else {
            $wp_filetype = wp_check_filetype($filename, null);
            $attachment = array(
                'guid'           => $upload_dir['url'] . '/' . $filename,
                'post_mime_type' => !empty($wp_filetype['type']) ? $wp_filetype['type'] : 'image/webp',
                'post_title'     => sanitize_text_field($title),
                'post_content'   => '',
                'post_status'    => 'inherit'
            );
            $attach_id = wp_insert_attachment($attachment, $target_file, $post_id);
            if (!is_wp_error($attach_id) && $attach_id > 0) {
                $attach_data = wp_generate_attachment_metadata($attach_id, $target_file);
                wp_update_attachment_metadata($attach_id, $attach_data);
            }
        }

        if (!empty($attach_id) && !is_wp_error($attach_id)) {
            set_post_thumbnail($post_id, $attach_id);
            update_post_meta($attach_id, '_wp_attachment_image_alt', sanitize_text_field($alt_text));
            update_post_meta($post_id, '_thumbnail_id', $attach_id);
            $log[] = '✅ تصویر شاخص «' . $title . '» متصل شد -> ' . $filename . ' (شناسه رسانه: ' . $attach_id . ')';
            $count_ok++;
        } else {
            $log[] = '❌ خطا در درج تصویر «' . $filename . '» برای مقاله ' . $slug;
            $count_miss++;
        }
    }

    $msg = 'عملیات موفق: ' . $count_ok . ' تصویر شاخص منتخب با نشان qpedia.ir و متادیتاهای سئو به مقالات متصل و جایگزین گردیدند.';
    return array('ok' => true, 'message' => $msg, 'log' => $log);
}
'''

with open(IMPORTER_DIR / 'qpedia-featured-images-special-6-replacer.php', 'w', encoding='utf-8') as f:
    f.write(php_code)

# Create zip package
zip_path = DL_DIR / 'qpedia-featured-images-special-6-replacer.zip'
with zipfile.ZipFile(zip_path, 'w', zipfile.ZIP_DEFLATED) as zipf:
    for root, dirs, files in os.walk(IMPORTER_DIR):
        for file in files:
            full_p = Path(root) / file
            rel_p = full_p.relative_to(IMPORTER_DIR.parent)
            zipf.write(full_p, arcname=str(rel_p))

print(f"Zip created at {zip_path} with size {zip_path.stat().st_size / 1024:.2f} KB")
