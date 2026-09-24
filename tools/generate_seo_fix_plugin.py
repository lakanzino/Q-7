#!/usr/bin/env python3
"""Generate Qpedia Glossary TamRank SEO Fix & Bloat Cleaner Plugin."""
import json
from pathlib import Path
import zipfile

ROOT = Path(__file__).resolve().parents[1]
PLUGIN_DIR = ROOT / 'article-rewrite-2026-09-22/importers/glossary-tamrank-seo-fix/qpedia-glossary-tamrank-seo-fix'
PLUGIN_DIR.mkdir(parents=True, exist_ok=True)

DATA_FILE = ROOT / 'article-rewrite-2026-09-22/glossary-201-tamrank-seo-data.json'
with open(DATA_FILE, 'r', encoding='utf-8') as f:
    terms_data = json.load(f)

# Write terms-seo.json inside plugin dir
(PLUGIN_DIR / 'terms-seo.json').write_text(json.dumps(terms_data, ensure_ascii=False, indent=2), encoding='utf-8')

php_code = r'''<?php
/**
 * Plugin Name: Qpedia Glossary TamRank SEO Fix & Bloat Cleaner
 * Description: به‌روزرسانی متادیتای سئوی تام‌رنک (TamRank SEO) برای تمام ۲۰۱ اصطلاح واژه‌نامه و پاک‌سازی حافظه حجیم کدهای رندرشده (_tamrank_schema_rendered_source) از دیتابیس وردپرس.
 * Version: 1.0.2
 * Author: Qpedia Team
 * Text Domain: qpedia-tamrank-seo-fix
 */

defined('ABSPATH') || exit;

const QPG_SEO_OPTION = 'qpedia_glossary_tamrank_seo_fix_applied_v1';
const QPG_POST_TYPE  = 'qp_glossary';

add_action('admin_menu', 'qpg_seo_fix_admin_menu');
function qpg_seo_fix_admin_menu() {
    add_management_page(
        'به‌روزرسانی سئوی تام‌رنک واژه‌نامه',
        'سئوی تام‌رنک واژه‌نامه',
        'manage_options',
        'qpedia-glossary-tamrank-seo-fix',
        'qpg_seo_fix_render_page'
    );
}

function qpg_seo_fix_render_page() {
    if (!current_user_can('manage_options')) {
        return;
    }
    global $wpdb;
    $bloat_count = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE meta_key = '_tamrank_schema_rendered_source'");

    echo '<div class="wrap" dir="rtl">';
    echo '<h1>به‌روزرسانی سئوی تام‌رنک (TamRank SEO) و سبک‌سازی دیتابیس واژه‌نامه</h1>';

    if (isset($_POST['qpg_seo_run'])) {
        check_admin_referer('qpg_seo_run_action');
        $clean_bloat = isset($_POST['qpg_clean_bloat']);
        $result = qpg_seo_apply_updates($clean_bloat);
        $class = $result['ok'] ? 'notice-success' : 'notice-error';
        echo '<div class="notice ' . esc_attr($class) . '"><p><strong>' . esc_html($result['message']) . '</strong></p></div>';
        if (!empty($result['log'])) {
            echo '<div style="max-height:320px;overflow-y:auto;background:#fff;border:1px solid #ccd0d4;padding:12px;margin:15px 0;">';
            echo '<ul style="margin:0;padding:0 20px;font-size:13px;line-height:1.8;">';
            foreach ($result['log'] as $line) {
                echo '<li>' . esc_html($line) . '</li>';
            }
            echo '</ul></div>';
        }
        if ($result['ok']) {
            update_option(QPG_SEO_OPTION, current_time('mysql'), false);
            echo '<div class="notice notice-info"><p>به‌روزرسانی کامل شد. اکنون نمره سئوی تام‌رنک ارتقا یافته و می‌توانید در منوی تام‌رنک بازبینی خودکار (Audit) را نیز مشاهده یا اجرا نمایید.</p></div>';
        }
    }

    echo '<div class="card" style="max-width:850px;margin-top:20px;padding:15px 25px;">';
    echo '<h2>عملیات‌های این افزونه:</h2>';
    echo '<ol style="font-size:14px;line-height:1.9;">';
    echo '<li><strong>تنظیم متادیتای اختصاصی TamRank SEO:</strong> ثبت کلیدواژه کانونی فارسی، عنوان سئو بهینه ۵۰-۶۰ حرف، توضیح متای ۱۲۰-۱۵۵ حرف، اسلاگ انگلیسی و آدرس کنونیکال برای تمام ۲۰۱ اصطلاح.</li>';
    echo '<li><strong>همگام‌سازی چندگانه:</strong> تنظیم فیلدهای متای Rank Math و Yoast جهت سازگاری صددرصدی.</li>';
    echo '<li><strong>پاک‌سازی حافظه کش فوق‌سنگین کدهای رندرشده:</strong> در حال حاضر <strong>' . number_format_i18n($bloat_count) . '</strong> ردیف از متای <code>_tamrank_schema_rendered_source</code> در دیتابیس وجود دارد که حجم خروجی XML را تا چندین برابر سنگین کرده بود.</li>';
    echo '</ol>';
    echo '<form method="post" style="margin-top:20px;">';
    wp_nonce_field('qpg_seo_run_action');
    echo '<p><label><input type="checkbox" name="qpg_clean_bloat" value="1" checked="checked"> <strong>پاک‌سازی ردیف‌های متای حجیم _tamrank_schema_rendered_source از دیتابیس (پیشنهاد می‌شود)</strong></label></p>';
    submit_button('اعمال تنظیمات سئوی تام‌رنک و سبک‌سازی دیتابیس', 'primary', 'qpg_seo_run');
    echo '</form>';
    echo '</div></div>';
}

function qpg_seo_apply_updates($clean_bloat = true) {
    global $wpdb;
    $json_file = plugin_dir_path(__FILE__) . 'terms-seo.json';
    if (!is_readable($json_file)) {
        return array('ok' => false, 'message' => 'فایل terms-seo.json خوانده نشد.', 'log' => array());
    }
    $terms_data = json_decode(file_get_contents($json_file), true);
    if (!is_array($terms_data) || empty($terms_data)) {
        return array('ok' => false, 'message' => 'فرمت داده‌های سئو نامعتبر است.', 'log' => array());
    }

    $updated_count = 0;
    $log = array();

    foreach ($terms_data as $item) {
        $id        = isset($item['id']) ? (int) $item['id'] : 0;
        $title     = isset($item['title']) ? trim((string) $item['title']) : '';
        $slug      = isset($item['slug']) ? trim((string) $item['slug']) : '';
        $fk        = isset($item['focus_keyword']) ? trim((string) $item['focus_keyword']) : $title;
        $seo_t     = isset($item['seo_title']) ? trim((string) $item['seo_title']) : '';
        $seo_d     = isset($item['meta_description']) ? trim((string) $item['meta_description']) : '';
        $canonical = isset($item['canonical']) ? trim((string) $item['canonical']) : home_url('/glossary/' . $slug . '/');

        $post = null;
        if ($id > 0) {
            $post = get_post($id);
        }
        if (!$post && $slug) {
            $found = get_posts(array(
                'name'        => $slug,
                'post_type'   => array(QPG_POST_TYPE, 'post', 'page'),
                'post_status' => 'any',
                'numberposts' => 1,
            ));
            if (!empty($found)) {
                $post = $found[0];
            }
        }
        if (!$post && $title) {
            $found = get_posts(array(
                'title'       => $title,
                'post_type'   => array(QPG_POST_TYPE, 'post', 'page'),
                'post_status' => 'any',
                'numberposts' => 1,
            ));
            if (!empty($found)) {
                $post = $found[0];
            }
        }

        if (!$post) {
            $log[] = 'اصطلاح یافت نشد: ' . $title . ' (شناسه: ' . $id . ', اسلاگ: ' . $slug . ')';
            continue;
        }

        $post_id = (int) $post->ID;

        // TamRank SEO Metas
        update_post_meta($post_id, '_tam_rank_focus_keyword', $fk);
        update_post_meta($post_id, '_tam_rank_meta_title', $seo_t);
        update_post_meta($post_id, '_tam_rank_meta_description', $seo_d);
        update_post_meta($post_id, '_tam_rank_canonical', $canonical);
        update_post_meta($post_id, '_tam_rank_custom_slug', $slug);
        update_post_meta($post_id, '_tam_rank_social_title', $seo_t);
        update_post_meta($post_id, '_tam_rank_social_description', $seo_d);

        // Rank Math / Yoast / General Metas
        update_post_meta($post_id, 'rank_math_focus_keyword', $fk);
        update_post_meta($post_id, 'rank_math_title', $seo_t);
        update_post_meta($post_id, 'rank_math_description', $seo_d);
        update_post_meta($post_id, '_yoast_wpseo_focuskw', $fk);
        update_post_meta($post_id, '_yoast_wpseo_title', $seo_t);
        update_post_meta($post_id, '_yoast_wpseo_metadesc', $seo_d);
        update_post_meta($post_id, '_qpedia_focus_keyphrase', $fk);
        update_post_meta($post_id, '_qpedia_seo_title', $seo_t);
        update_post_meta($post_id, '_qpedia_meta_description', $seo_d);

        $updated_count++;
        $log[] = 'موفق: «' . $title . '» (شناسه: ' . $post_id . ') -> ثبت متای سئوی تام‌رنک';
    }

    $deleted_bloat = 0;
    if ($clean_bloat) {
        $deleted_bloat = $wpdb->query("DELETE FROM {$wpdb->postmeta} WHERE meta_key = '_tamrank_schema_rendered_source'");
    }

    $msg = 'تعداد ' . $updated_count . ' اصطلاح با متادیتای کامل تام‌رنک به‌روزرسانی شد.';
    if ($clean_bloat) {
        $msg .= ' تعداد ' . (int) $deleted_bloat . ' ردیف متای حجیم رندرشده از دیتابیس حذف گردید.';
    }

    return array('ok' => true, 'message' => $msg, 'log' => $log);
}
'''

php_file = PLUGIN_DIR / 'qpedia-glossary-tamrank-seo-fix.php'
php_file.write_text(php_code.strip() + '\n', encoding='utf-8')
print(f'Wrote {php_file}')

# Create zip package
zip_path = ROOT / 'article-rewrite-2026-09-22/importers/glossary-tamrank-seo-fix/qpedia-glossary-tamrank-seo-fix.zip'
with zipfile.ZipFile(zip_path, 'w', zipfile.ZIP_DEFLATED) as zf:
    for f in PLUGIN_DIR.glob('*'):
        if f.is_file():
            zf.write(f, arcname=f'qpedia-glossary-tamrank-seo-fix/{f.name}')

print(f'Created {zip_path} ({zip_path.stat().st_size / 1024:.1f} KB)')

# Copy to downloads
dl_zip = ROOT / 'downloads/qpedia-glossary-tamrank-seo-fix.zip'
dl_zip.write_bytes(zip_path.read_bytes())
print(f'Copied to {dl_zip} ({dl_zip.stat().st_size / 1024:.1f} KB)')
