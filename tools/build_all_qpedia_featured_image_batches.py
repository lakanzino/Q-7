#!/usr/bin/env python3
"""Build all 172 featured images in standard 5-article batch WordPress plugins (Batches 01 to 35) + Master Plugin."""
import csv
import json
import os
import shutil
import subprocess
import zipfile
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
OUT_BASE = ROOT / 'article-rewrite-2026-09-22/importers/all-featured-images-batches'
OUT_BASE.mkdir(parents=True, exist_ok=True)
DL_DIR = ROOT / 'downloads'
DL_DIR.mkdir(parents=True, exist_ok=True)
FONT = '/usr/share/fonts/truetype/dejavu/DejaVuSans-Bold.ttf'

all_items = {}

# 1. 56 images
sys_path = str(ROOT / 'tools')
import sys
if sys_path not in sys.path:
    sys.path.insert(0, sys_path)

from process_56_featured_images import IMAGE_MAP
for src, (slug, title, alt) in sorted(IMAGE_MAP.items()):
    all_items[slug] = {
        'slug': slug,
        'title': title,
        'alt': alt,
        'src_file': ROOT / f'article-rewrite-2026-09-22/featured-images-56-webp/{slug}.webp'
    }

# 2. 82 images (006-090)
p82 = ROOT / 'featured-images-006-090/manifest.csv'
if p82.exists():
    with open(p82, encoding='utf-8-sig') as f:
        r = csv.DictReader(f)
        for row in r:
            slug = row['slug']
            if slug not in all_items:
                all_items[slug] = {
                    'slug': slug,
                    'title': row['alt_fa'],
                    'alt': row['alt_fa'],
                    'src_file': ROOT / f'featured-images-006-090/{slug}.webp'
                }

# 3. 36 images (final)
p36 = ROOT / 'featured-images-final/manifest.csv'
if p36.exists():
    with open(p36, encoding='utf-8-sig') as f:
        r = csv.DictReader(f)
        for row in r:
            slug = row['slug']
            if slug not in all_items:
                all_items[slug] = {
                    'slug': slug,
                    'title': row['alt_fa'],
                    'alt': row['alt_fa'],
                    'src_file': ROOT / f'featured-images-final/{slug}.webp'
                }

# 4. Batch 5 special
b5_slugs = [
    ('quantum-game-theory', 'نظریه بازی کوانتومی', 'تصویر مفهومی نظریه بازی کوانتومی، دو مهره شطرنج نوری پیوندیافته با حلقه درهم‌تنیدگی و امواج احتمالاتی'),
    ('quantum-games', 'بازی‌های کوانتومی', 'تصویر مفهومی بازی‌های کوانتومی با صفحه و مهره‌های شطرنج نوری در وضعیت برهم‌نهی کوانتومی'),
    ('quantum-finance', 'محاسبات کوانتومی در وال‌استریت', 'تصویر مفهومی محاسبات کوانتومی در وال‌استریت و بازارهای مالی با نمودار صعودی و احتمالات چندمسیره'),
    ('quantum-arms-race', 'مسابقه تسلیحاتی کوانتومی', 'تصویر مفهومی مسابقه تسلیحاتی کوانتومی با نشانه‌گیر راداری نئونی و برهم‌کنش ذرات فوتون و الکترون'),
    ('quantum-conference-key-agreement', 'توافق کلید کوانتومی', 'تصویر مفهومی توافق کلید کوانتومی چندطرفه با هشت گره درهم‌تنیده حول قفل امنیتی نئونی')
]
for slug, title, alt in b5_slugs:
    all_items[slug] = {
        'slug': slug,
        'title': title,
        'alt': alt,
        'src_file': ROOT / f'featured-images-batch-5/{slug}.webp'
    }

print(f'Total cataloged items: {len(all_items)}')

# Standard watermark function
def ensure_watermarked(src_path, dst_path):
    cmd = [
        'convert', str(src_path),
        '(', '-size', '130x34', 'xc:none',
        '-fill', 'rgba(10, 18, 32, 0.65)', '-draw', 'roundrectangle 0,0 129,33 7,7',
        '-stroke', 'rgba(0, 212, 255, 0.45)', '-strokewidth', '1', '-draw', 'roundrectangle 0,0 129,33 7,7',
        '-font', FONT, '-pointsize', '14', '-fill', 'rgba(232, 244, 252, 0.95)',
        '-gravity', 'center', '-annotate', '+0+0', 'qpedia.ir', ')',
        '-gravity', 'NorthEast', '-geometry', '+35+30', '-composite',
        '-quality', '85',
        str(dst_path)
    ]
    subprocess.run(cmd, check=True)

# Prepare unified master image library
master_img_dir = ROOT / 'article-rewrite-2026-09-22/all-featured-images-watermarked'
master_img_dir.mkdir(parents=True, exist_ok=True)

items_list = []
for slug, info in sorted(all_items.items()):
    out_img = master_img_dir / f'{slug}.webp'
    ensure_watermarked(info['src_file'], out_img)
    items_list.append({
        'slug': slug,
        'title': info['title'],
        'filename': f'{slug}.webp',
        'alt': info['alt']
    })

print(f'Watermarked all {len(items_list)} images into {master_img_dir}')

# Split into 5-article batches
batch_size = 5
batches = [items_list[i:i + batch_size] for i in range(0, len(items_list), batch_size)]
print(f'Created {len(batches)} batches of 5 articles each.')

batch_catalog = []

for idx, b_items in enumerate(batches, 1):
    b_slug = f'qpedia-featured-images-batch-{idx:02d}'
    b_func = f'qp_fimg_b{idx:02d}'
    b_dir = OUT_BASE / b_slug
    if b_dir.exists():
        shutil.rmtree(b_dir)
    b_dir.mkdir(parents=True, exist_ok=True)
    img_dir = b_dir / 'images'
    img_dir.mkdir(exist_ok=True)

    for item in b_items:
        shutil.copy2(master_img_dir / item['filename'], img_dir / item['filename'])

    (b_dir / 'images_data.json').write_text(json.dumps(b_items, ensure_ascii=False, indent=2), encoding='utf-8')

    items_html_lines = []
    for it in b_items:
        items_html_lines.append(f"    echo '<li><strong>{it['title']}</strong> (<code>{it['slug']}</code>) &larr; <code>{it['filename']}</code></li>';")
    items_html = '\n'.join(items_html_lines)

    php_code = f'''<?php
/**
 * Plugin Name: Qpedia Featured Images - Batch {idx:02d} ({len(b_items)} Images)
 * Description: افزونه تنظیم و جایگزینی تصاویر شاخص بهینه‌شده WebP با نشان استاندارد qpedia.ir (بسته شماره {idx:02d} شامل {len(b_items)} مقاله) به همراه متادیتا و Alt Text سئو در Qpedia.
 * Version: 1.0.0
 * Author: Qpedia Team
 * Text Domain: {b_slug}
 */

defined('ABSPATH') || exit;

add_action('admin_menu', '{b_func}_admin_menu');
function {b_func}_admin_menu() {{
    add_management_page(
        'تصاویر شاخص کوانتوم - بسته {idx:02d}',
        'تصاویر شاخص بسته {idx:02d}',
        'manage_options',
        '{b_slug}',
        '{b_func}_render_page'
    );
}}

function {b_func}_render_page() {{
    if (!current_user_can('manage_options')) {{
        return;
    }}

    echo '<div class="wrap" dir="rtl" style="font-family:Tahoma,sans-serif;line-height:1.7;">';
    echo '<h1>🖼️ تنظیم و جایگزینی تصاویر شاخص WebP - بسته {idx:02d}</h1>';

    if (isset($_POST['qp_run_attach'])) {{
        check_admin_referer('qp_attach_nonce_{idx}');
        $result = {b_func}_execute();

        $class = $result['ok'] ? 'notice-success' : 'notice-error';
        echo '<div class="notice ' . esc_attr($class) . '"><p><strong>' . esc_html($result['message']) . '</strong></p></div>';

        if (!empty($result['log'])) {{
            echo '<div style="max-height:350px;overflow-y:auto;background:#fff;border:1px solid #ccd0d4;padding:12px;margin:15px 0;">';
            echo '<ul style="margin:0;padding:0 20px;font-size:13px;line-height:1.8;">';
            foreach ($result['log'] as $line) {{
                echo '<li>' . esc_html($line) . '</li>';
            }}
            echo '</ul></div>';
        }}
    }}

    echo '<div class="card" style="max-width:850px;margin-top:20px;padding:15px 25px;">';
    echo '<h2>فهرست مقالات و تصاویر این بسته ({len(b_items)} تصویر با نشان qpedia.ir):</h2>';
    echo '<ol style="font-size:14px;line-height:1.9;">';
{items_html}
    echo '</ol>';

    echo '<form method="post" style="margin-top:20px;">';
    wp_nonce_field('qp_attach_nonce_{idx}');
    submit_button('شروع اتصال و جایگزینی تصاویر شاخص بسته {idx:02d}', 'primary', 'qp_run_attach');
    echo '</form>';
    echo '</div></div>';
}}

function {b_func}_execute() {{
    $data_file = plugin_dir_path(__FILE__) . 'images_data.json';
    if (!is_readable($data_file)) {{
        return array('ok' => false, 'message' => 'فایل متادیتا یافت نشد.', 'log' => array());
    }}

    $items = json_decode(file_get_contents($data_file), true);
    if (!is_array($items) || empty($items)) {{
        return array('ok' => false, 'message' => 'فرمت متادیتا نامعتبر است.', 'log' => array());
    }}

    $count_ok = 0;
    $count_miss = 0;
    $log = array();

    $upload_dir = wp_upload_dir();
    require_once(ABSPATH . 'wp-admin/includes/image.php');

    foreach ($items as $item) {{
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

        if (empty($posts)) {{
            $log[] = '⚠️ مقاله با اسلاگ «' . $slug . '» در سایت یافت نشد.';
            $count_miss++;
            continue;
        }}

        $post_id = (int)$posts[0]->ID;
        $img_src = plugin_dir_path(__FILE__) . 'images/' . $filename;

        if (!file_exists($img_src)) {{
            $log[] = '❌ فایل تصویر «' . $filename . '» در پوشه افزونه موجود نیست.';
            $count_miss++;
            continue;
        }}

        $target_file = $upload_dir['path'] . '/' . $filename;
        if (!file_exists($target_file)) {{
            wp_mkdir_p($upload_dir['path']);
            copy($img_src, $target_file);
        }}

        $base_name = pathinfo($filename, PATHINFO_FILENAME);
        $existing_attach = get_posts(array(
            'post_type'      => 'attachment',
            'name'           => $base_name,
            'posts_per_page' => 1,
            'post_status'    => 'inherit'
        ));

        if (!empty($existing_attach)) {{
            $attach_id = (int)$existing_attach[0]->ID;
        }} else {{
            $wp_filetype = wp_check_filetype($filename, null);
            $attachment = array(
                'guid'           => $upload_dir['url'] . '/' . $filename,
                'post_mime_type' => !empty($wp_filetype['type']) ? $wp_filetype['type'] : 'image/webp',
                'post_title'     => sanitize_text_field($title),
                'post_content'   => '',
                'post_status'    => 'inherit'
            );
            $attach_id = wp_insert_attachment($attachment, $target_file, $post_id);
            if (!is_wp_error($attach_id) && $attach_id > 0) {{
                $attach_data = wp_generate_attachment_metadata($attach_id, $target_file);
                wp_update_attachment_metadata($attach_id, $attach_data);
            }}
        }}

        if (!empty($attach_id) && !is_wp_error($attach_id)) {{
            set_post_thumbnail($post_id, $attach_id);
            update_post_meta($attach_id, '_wp_attachment_image_alt', sanitize_text_field($alt_text));
            update_post_meta($post_id, '_thumbnail_id', $attach_id);
            $log[] = '✅ تصویر شاخص «' . $title . '» متصل شد -> ' . $filename . ' (شناسه رسانه ' . $attach_id . ')';
            $count_ok++;
        }} else {{
            $log[] = '❌ خطا در درج تصویر «' . $filename . '» برای مقاله ' . $slug;
            $count_miss++;
        }}
    }}

    $msg = 'عملیات بسته ' . {idx} . ' به پایان رسید: ' . $count_ok . ' تصویر شاخص با موفقیت متصل گردید.';
    return array('ok' => true, 'message' => $msg, 'log' => $log);
}}
'''
    (b_dir / f'{b_slug}.php').write_text(php_code.strip() + '\n', encoding='utf-8')

    zip_path = OUT_BASE / f'{b_slug}.zip'
    with zipfile.ZipFile(zip_path, 'w', zipfile.ZIP_DEFLATED) as zf:
        for f in b_dir.rglob('*'):
            if f.is_file():
                arc = f.relative_to(b_dir.parent)
                zf.write(f, arcname=str(arc))

    dl_zip = DL_DIR / f'{b_slug}.zip'
    shutil.copy2(zip_path, dl_zip)
    batch_catalog.append({
        'batch_num': idx,
        'slug': b_slug,
        'count': len(b_items),
        'size_kb': dl_zip.stat().st_size / 1024,
        'titles': [x['title'] for x in b_items]
    })
    print(f'Batch {idx:02d}: {b_slug}.zip ({dl_zip.stat().st_size / 1024:.1f} KB, {len(b_items)} items)')

# Also build Master All-In-One Plugin
master_slug = 'qpedia-all-featured-images-master'
master_dir = OUT_BASE / master_slug
if master_dir.exists():
    shutil.rmtree(master_dir)
master_dir.mkdir(parents=True, exist_ok=True)
m_img_dir = master_dir / 'images'
m_img_dir.mkdir(exist_ok=True)

for it in items_list:
    shutil.copy2(master_img_dir / it['filename'], m_img_dir / it['filename'])

(master_dir / 'images_data.json').write_text(json.dumps(items_list, ensure_ascii=False, indent=2), encoding='utf-8')

master_php = f'''<?php
/**
 * Plugin Name: Qpedia Featured Images - Master All-in-One ({len(items_list)} Images)
 * Description: افزونه جامع تنظیم و جایگزینی یک‌جای تمامی {len(items_list)} تصویر شاخص بهینه‌شده WebP دانشنامه با نشان qpedia.ir و Alt Text سئو در وردپرس.
 * Version: 1.0.0
 * Author: Qpedia Team
 * Text Domain: qpedia-all-featured-images-master
 */

defined('ABSPATH') || exit;

add_action('admin_menu', 'qp_all_fimg_master_menu');
function qp_all_fimg_master_menu() {{
    add_management_page(
        'تنظیم جامع تصاویر شاخص کوانتوم',
        'تصاویر شاخص دانشنامه ({len(items_list)})',
        'manage_options',
        'qpedia-all-featured-images-master',
        'qp_all_fimg_master_render_page'
    );
}}

function qp_all_fimg_master_render_page() {{
    if (!current_user_can('manage_options')) {{
        return;
    }}

    echo '<div class="wrap" dir="rtl" style="font-family:Tahoma,sans-serif;line-height:1.7;">';
    echo '<h1>🖼️ تنظیم و جایگزینی جامع تمامی {len(items_list)} تصویر شاخص WebP</h1>';

    if (isset($_POST['qp_run_master_attach'])) {{
        check_admin_referer('qp_master_attach_nonce');
        $result = qp_all_fimg_master_execute();

        $class = $result['ok'] ? 'notice-success' : 'notice-error';
        echo '<div class="notice ' . esc_attr($class) . '"><p><strong>' . esc_html($result['message']) . '</strong></p></div>';

        if (!empty($result['log'])) {{
            echo '<div style="max-height:400px;overflow-y:auto;background:#fff;border:1px solid #ccd0d4;padding:12px;margin:15px 0;">';
            echo '<ul style="margin:0;padding:0 20px;font-size:13px;line-height:1.8;">';
            foreach ($result['log'] as $line) {{
                echo '<li>' . esc_html($line) . '</li>';
            }}
            echo '</ul></div>';
        }}
    }}

    echo '<div class="card" style="max-width:850px;margin-top:20px;padding:15px 25px;">';
    echo '<h2>مشخصات بسته جامع تصاویر:</h2>';
    echo '<p>این افزونه شامل تمامی <strong>{len(items_list)} تصویر شاخص</strong> با نشان استاندارد <code>qpedia.ir</code>، ابعاد 16:9 WebP و متن‌های جایگزین سئو است و با یک کلیک تمامی مقالات موجود در سایت را شناسایی و تصاویر شاخص آن‌ها را متصل می‌کند.</p>';

    echo '<form method="post" style="margin-top:20px;">';
    wp_nonce_field('qp_master_attach_nonce');
    submit_button('شروع اتصال یک‌جای تمامی {len(items_list)} تصویر شاخص', 'primary', 'qp_run_master_attach');
    echo '</form>';
    echo '</div></div>';
}}

function qp_all_fimg_master_execute() {{
    $data_file = plugin_dir_path(__FILE__) . 'images_data.json';
    if (!is_readable($data_file)) {{
        return array('ok' => false, 'message' => 'فایل متادیتا یافت نشد.', 'log' => array());
    }}

    $items = json_decode(file_get_contents($data_file), true);
    if (!is_array($items) || empty($items)) {{
        return array('ok' => false, 'message' => 'فرمت متادیتا نامعتبر است.', 'log' => array());
    }}

    $count_ok = 0;
    $count_miss = 0;
    $log = array();

    $upload_dir = wp_upload_dir();
    require_once(ABSPATH . 'wp-admin/includes/image.php');

    foreach ($items as $item) {{
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

        if (empty($posts)) {{
            $log[] = '⚠️ مقاله با اسلاگ «' . $slug . '» در سایت یافت نشد.';
            $count_miss++;
            continue;
        }}

        $post_id = (int)$posts[0]->ID;
        $img_src = plugin_dir_path(__FILE__) . 'images/' . $filename;

        if (!file_exists($img_src)) {{
            $log[] = '❌ فایل تصویر «' . $filename . '» در پوشه افزونه موجود نیست.';
            $count_miss++;
            continue;
        }}

        $target_file = $upload_dir['path'] . '/' . $filename;
        if (!file_exists($target_file)) {{
            wp_mkdir_p($upload_dir['path']);
            copy($img_src, $target_file);
        }}

        $base_name = pathinfo($filename, PATHINFO_FILENAME);
        $existing_attach = get_posts(array(
            'post_type'      => 'attachment',
            'name'           => $base_name,
            'posts_per_page' => 1,
            'post_status'    => 'inherit'
        ));

        if (!empty($existing_attach)) {{
            $attach_id = (int)$existing_attach[0]->ID;
        }} else {{
            $wp_filetype = wp_check_filetype($filename, null);
            $attachment = array(
                'guid'           => $upload_dir['url'] . '/' . $filename,
                'post_mime_type' => !empty($wp_filetype['type']) ? $wp_filetype['type'] : 'image/webp',
                'post_title'     => sanitize_text_field($title),
                'post_content'   => '',
                'post_status'    => 'inherit'
            );
            $attach_id = wp_insert_attachment($attachment, $target_file, $post_id);
            if (!is_wp_error($attach_id) && $attach_id > 0) {{
                $attach_data = wp_generate_attachment_metadata($attach_id, $target_file);
                wp_update_attachment_metadata($attach_id, $attach_data);
            }}
        }}

        if (!empty($attach_id) && !is_wp_error($attach_id)) {{
            set_post_thumbnail($post_id, $attach_id);
            update_post_meta($attach_id, '_wp_attachment_image_alt', sanitize_text_field($alt_text));
            update_post_meta($post_id, '_thumbnail_id', $attach_id);
            $log[] = '✅ تصویر شاخص «' . $title . '» متصل شد -> ' . $filename . ' (شناسه ' . $attach_id . ')';
            $count_ok++;
        }} else {{
            $log[] = '❌ خطا در درج تصویر «' . $filename . '» برای مقاله ' . $slug;
            $count_miss++;
        }}
    }}

    $msg = 'عملیات با موفقیت انجام شد: ' . $count_ok . ' تصویر شاخص با نشان qpedia.ir متصل گردید.';
    return array('ok' => true, 'message' => $msg, 'log' => $log);
}}
'''
(master_dir / 'qpedia-all-featured-images-master.php').write_text(master_php.strip() + '\n', encoding='utf-8')

m_zip_path = OUT_BASE / f'{master_slug}.zip'
with zipfile.ZipFile(m_zip_path, 'w', zipfile.ZIP_DEFLATED) as zf:
    for f in master_dir.rglob('*'):
        if f.is_file():
            arc = f.relative_to(master_dir.parent)
            zf.write(f, arcname=str(arc))

dl_m_zip = DL_DIR / f'{master_slug}.zip'
shutil.copy2(m_zip_path, dl_m_zip)
print(f'Master Plugin Created: {master_slug}.zip ({dl_m_zip.stat().st_size / 1024:.1f} KB, {len(items_list)} items)')

with open(ROOT / 'docs/all-featured-images-catalog.json', 'w', encoding='utf-8') as f:
    json.dump(batch_catalog, f, ensure_ascii=False, indent=2)

print('Build completed successfully!')
