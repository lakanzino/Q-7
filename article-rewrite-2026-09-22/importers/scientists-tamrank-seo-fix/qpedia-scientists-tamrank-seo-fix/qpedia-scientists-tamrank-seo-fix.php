<?php
/**
 * Plugin Name: Qpedia Scientists TamRank SEO Fix & Bloat Cleaner
 * Description: به‌روزرسانی متادیتای سئوی تام‌رنک (TamRank SEO) برای تمام دانشمندان کوانتوم و پاک‌سازی حافظه کدهای رندرشده (_tamrank_schema_rendered_source) از دیتابیس وردپرس.
 * Version: 1.0.0
 * Author: Qpedia Team
 * Text Domain: qpedia-scientists-tamrank-seo-fix
 */

defined('ABSPATH') || exit;

const QPSCI_SEO_OPTION = 'qpedia_scientists_tamrank_seo_fix_applied_v1';
const QPSCI_POST_TYPE  = 'quantum_scientist';

add_action('admin_menu', 'qpsci_seo_fix_admin_menu');
function qpsci_seo_fix_admin_menu() {
    add_management_page(
        'به‌روزرسانی سئوی تام‌رنک دانشمندان',
        'سئوی تام‌رنک دانشمندان',
        'manage_options',
        'qpedia-scientists-tamrank-seo-fix',
        'qpsci_seo_fix_render_page'
    );
}

function qpsci_seo_fix_render_page() {
    if (!current_user_can('manage_options')) {
        return;
    }
    global $wpdb;
    $bloat_count = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE meta_key = '_tamrank_schema_rendered_source'");

    echo '<div class="wrap" dir="rtl">';
    echo '<h1>به‌روزرسانی سئوی تام‌رنک (TamRank SEO) دانشمندان کوانتوم</h1>';

    if (isset($_POST['qpsci_seo_run'])) {
        check_admin_referer('qpsci_seo_run_action');
        $clean_bloat = isset($_POST['qpsci_clean_bloat']);
        $result = qpsci_seo_apply_updates($clean_bloat);
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
            update_option(QPSCI_SEO_OPTION, current_time('mysql'), false);
            echo '<div class="notice notice-info"><p>به‌روزرسانی کامل شد. نمره سئوی تام‌رنک دانشمندان سبز شد و کدهای حجیم زائد پاک گردیدند.</p></div>';
        }
    }

    echo '<div class="card" style="max-width:850px;margin-top:20px;padding:15px 25px;">';
    echo '<h2>عملیات‌های این افزونه:</h2>';
    echo '<ol style="font-size:14px;line-height:1.9;">';
    echo '<li><strong>تنظیم متادیتای اختصاصی TamRank SEO:</strong> ثبت کلیدواژه کانونی، عنوان سئو بهینه ۵۰-۶۰ حرف، توضیح متای ۱۲۰-۱۵۵ حرف، اسلاگ انگلیسی و آدرس کنونیکال برای مقالات دانشمندان.</li>';
    echo '<li><strong>همگام‌سازی چندگانه:</strong> تنظیم فیلدهای متای Rank Math و Yoast جهت سازگاری کامل.</li>';
    echo '<li><strong>پاک‌سازی حافظه کش کدهای رندرشده:</strong> در حال حاضر <strong>' . number_format_i18n($bloat_count) . '</strong> ردیف از متای <code>_tamrank_schema_rendered_source</code> در دیتابیس وجود دارد که با تیک زدن گزینه زیر پاک خواهد شد.</li>';
    echo '</ol>';
    echo '<form method="post" style="margin-top:20px;">';
    wp_nonce_field('qpsci_seo_run_action');
    echo '<p><label><input type="checkbox" name="qpsci_clean_bloat" value="1" checked="checked"> <strong>پاک‌سازی ردیف‌های متای حجیم _tamrank_schema_rendered_source از دیتابیس</strong></label></p>';
    submit_button('اعمال متادیتای سئوی دانشمندان و سبک‌سازی دیتابیس', 'primary', 'qpsci_seo_run');
    echo '</form>';
    echo '</div></div>';
}

function qpsci_seo_apply_updates($clean_bloat = true) {
    global $wpdb;
    $json_file = plugin_dir_path(__FILE__) . 'scientists-seo.json';
    if (!is_readable($json_file)) {
        return array('ok' => false, 'message' => 'فایل scientists-seo.json خوانده نشد.', 'log' => array());
    }
    $scientists_data = json_decode(file_get_contents($json_file), true);
    if (!is_array($scientists_data) || empty($scientists_data)) {
        return array('ok' => false, 'message' => 'فرمت داده‌های سئو نامعتبر است.', 'log' => array());
    }

    $updated_count = 0;
    $log = array();

    foreach ($scientists_data as $item) {
        $id        = isset($item['id']) ? (int) $item['id'] : 0;
        $title     = isset($item['title']) ? trim((string) $item['title']) : '';
        $slug      = isset($item['slug']) ? trim((string) $item['slug']) : '';
        $fk        = isset($item['focus_keyword']) ? trim((string) $item['focus_keyword']) : $title;
        $seo_t     = isset($item['seo_title']) ? trim((string) $item['seo_title']) : '';
        $seo_d     = isset($item['meta_description']) ? trim((string) $item['meta_description']) : '';
        $canonical = home_url('/scientists/' . $slug . '/');

        $post = null;
        if ($id > 0) {
            $post = get_post($id);
        }
        if (!$post && $slug) {
            $found = get_posts(array(
                'name'        => $slug,
                'post_type'   => array(QPSCI_POST_TYPE, 'post', 'page'),
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
                'post_type'   => array(QPSCI_POST_TYPE, 'post', 'page'),
                'post_status' => 'any',
                'numberposts' => 1,
            ));
            if (!empty($found)) {
                $post = $found[0];
            }
        }

        if (!$post) {
            $log[] = 'دانشمند یافت نشد: ' . $title . ' (شناسه: ' . $id . ', اسلاگ: ' . $slug . ')';
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
        $log[] = 'موفق: دانشمند «' . $title . '» (شناسه: ' . $post_id . ') -> ثبت متای سئوی تام‌رنک';
    }

    $deleted_bloat = 0;
    if ($clean_bloat) {
        $deleted_bloat = $wpdb->query("DELETE FROM {$wpdb->postmeta} WHERE meta_key = '_tamrank_schema_rendered_source'");
    }

    $msg = 'تعداد ' . $updated_count . ' دانشمند با متادیتای کامل تام‌رنک به‌روزرسانی شد.';
    if ($clean_bloat) {
        $msg .= ' تعداد ' . (int) $deleted_bloat . ' ردیف متای حجیم رندرشده از دیتابیس حذف گردید.';
    }

    return array('ok' => true, 'message' => $msg, 'log' => $log);
}
