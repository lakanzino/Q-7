<?php
/**
 * Plugin Name: Qpedia Glossary TamRank SEO Fix & Bloat Cleaner
 * Description: به‌روزرسانی متادیتای سئوی تام‌رنک (TamRank SEO) برای تمام ۲۰۱ اصطلاح واژه‌نامه (شامل کلیدواژه کانونی، متاتایتل، متادیسکریپشن و کنونیکال) و پاک‌سازی حافظه حجیم کدهای رندرشده (_tamrank_schema_rendered_source) از دیتابیس وردپرس.
 * Version: 1.0.0
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
    
    global ;
     = get_option(QPG_SEO_OPTION);
    
    // Count bloated rows currently in database
     = (int) ->get_var("SELECT COUNT(*) FROM {->postmeta} WHERE meta_key = '_tamrank_schema_rendered_source'");
    
    echo '<div class="wrap" dir="rtl">';
    echo '<h1>به‌روزرسانی سئوی تام‌رنک (TamRank SEO) و بهینه‌سازی دیتابیس واژه‌نامه</h1>';
    
    if (isset(['qpg_seo_run'])) {
        check_admin_referer('qpg_seo_run_action');
         = isset(['qpg_clean_bloat']);
         = qpg_seo_apply_updates();
        
         = ['ok'] ? 'notice-success' : 'notice-error';
        echo '<div class="notice ' . esc_attr() . '"><p><strong>' . esc_html(['message']) . '</strong></p></div>';
        
        if (!empty(['log'])) {
            echo '<div style="max-height:350px;overflow-y:auto;background:#fff;border:1px solid #ccd0d4;padding:12px;margin:15px 0;">';
            echo '<ul style="margin:0;padding:0 20px;">';
            foreach (['log'] as ) {
                echo '<li>' . esc_html() . '</li>';
            }
            echo '</ul></div>';
        }
        
        if (['ok']) {
            update_option(QPG_SEO_OPTION, current_time('mysql'), false);
            echo '<div class="notice notice-info"><p>به‌روزرسانی با موفقیت اعمال شد. اکنون می‌توانید نمره سئوی تام‌رنک را در بخش اصطلاحات مشاهده کنید یا بازبینی کلی (Audit) را در تام‌رنک اجرا نمایید.</p></div>';
        }
    }
    
    echo '<div class="card" style="max-width:850px;margin-top:20px;padding:15px 25px;">';
    echo '<h2>عملیات‌های این افزونه:</h2>';
    echo '<ol>';
    echo '<li><strong>ثبت متادیتای اختصاصی تام‌رنک (TamRank SEO):</strong> ثبت دقیق <code>_tam_rank_focus_keyword</code> (کلیدواژه کانونی فارسی)، <code>_tam_rank_meta_title</code> (عنوان سئو بهینه ۵۰-۶۰ حرف)، <code>_tam_rank_meta_description</code> (توضیح متای ۱۲۰-۱۵۵ حرف)، <code>_tam_rank_canonical</code> و <code>_tam_rank_custom_slug</code> (اسلاگ انگلیسی) برای تمام ۲۰۱ اصطلاح واژه‌نامه.</li>';
    echo '<li><strong>همگام‌سازی دوگانه:</strong> تنظیم متادیتای سئو در Rank Math و Yoast به صورت هم‌زمان جهت سازگاری کامل.</li>';
    echo '<li><strong>پاک‌سازی حافظه حجیم کدهای HTML رندرشده:</strong> در حال حاضر <strong>' . number_format_i18n() . '</strong> ردیف از متای <code>_tamrank_schema_rendered_source</code> در دیتابیس وجود دارد که حجم دیتابیس و خروجی XML را تا چندین برابر سنگین کرده است. این گزینه آن را پاک می‌کند.</li>';
    echo '</ol>';
    
    echo '<form method="post" style="margin-top:20px;">';
    wp_nonce_field('qpg_seo_run_action');
    echo '<p><label><input type="checkbox" name="qpg_clean_bloat" value="1" checked="checked"> <strong>پاک‌سازی ردیف‌های متای حجیم _tamrank_schema_rendered_source از جدول postmeta (پیشنهاد می‌شود)</strong></label></p>';
    submit_button('اعمال متادیتای سئوی تام‌رنک و پاک‌سازی حجم دیتابیس', 'primary', 'qpg_seo_run');
    echo '</form>';
    echo '</div></div>';
}

function qpg_seo_apply_updates( = true) {
    global ;
     = plugin_dir_path(__FILE__) . 'terms-seo.json';
    if (!is_readable()) {
        return array('ok' => false, 'message' => 'فایل داده‌های سئو terms-seo.json یافت نشد.', 'log' => array());
    }
    
     = json_decode(file_get_contents(), true);
    if (!is_array() || empty()) {
        return array('ok' => false, 'message' => 'فرمت فایل داده‌های سئو نامعتبر است.', 'log' => array());
    }
    
     = 0;
     = array();
    
    foreach ( as ) {
         = isset(['id']) ? (int) ['id'] : 0;
         = isset(['title']) ? trim((string) ['title']) : '';
         = isset(['slug']) ? trim((string) ['slug']) : '';
         = isset(['focus_keyword']) ? trim((string) ['focus_keyword']) : ;
         = isset(['seo_title']) ? trim((string) ['seo_title']) : '';
         = isset(['meta_description']) ? trim((string) ['meta_description']) : '';
         = isset(['canonical']) ? trim((string) ['canonical']) : home_url('/glossary/' .  . '/');
        
         = null;
        if ( > 0) {
             = get_post();
        }
        if (! && ) {
             = array(
                'name'        => ,
                'post_type'   => QPG_POST_TYPE,
                'post_status' => 'any',
                'numberposts' => 1
            );
             = get_posts();
            if (!empty()) {
                 = [0];
            }
        }
        if (! && ) {
             = array(
                'title'       => ,
                'post_type'   => QPG_POST_TYPE,
                'post_status' => 'any',
                'numberposts' => 1
            );
             = get_posts();
            if (!empty()) {
                 = [0];
            }
        }
        
        if (!) {
            [] = 'اصطلاح پیدا نشد: ' .  . ' (ID: ' .  . ', Slug: ' .  . ')';
            continue;
        }
        
         = (int) ->ID;
        
        // Update TamRank SEO Meta Keys
        update_post_meta(, '_tam_rank_focus_keyword', );
        update_post_meta(, '_tam_rank_meta_title', );
        update_post_meta(, '_tam_rank_meta_description', );
        update_post_meta(, '_tam_rank_canonical', );
        update_post_meta(, '_tam_rank_custom_slug', );
        update_post_meta(, '_tam_rank_social_title', );
        update_post_meta(, '_tam_rank_social_description', );
        
        // Update Rank Math / Yoast / Schema Keys
        update_post_meta(, 'rank_math_focus_keyword', );
        update_post_meta(, 'rank_math_title', );
        update_post_meta(, 'rank_math_description', );
        update_post_meta(, '_yoast_wpseo_focuskw', );
        update_post_meta(, '_yoast_wpseo_title', );
        update_post_meta(, '_yoast_wpseo_metadesc', );
        update_post_meta(, '_qpedia_focus_keyphrase', );
        update_post_meta(, '_qpedia_seo_title', );
        update_post_meta(, '_qpedia_meta_description', );
        
        ++;
        [] = 'موفق: «' .  . '» (شناسه ' .  . ') -> کلیدواژه: ' .  . ' | متاتایتل: ' . mb_substr(, 0, 30) . '...';
    }
    
     = 0;
    if () {
         = ->query("DELETE FROM {->postmeta} WHERE meta_key = '_tamrank_schema_rendered_source'");
    }
    
     = 'تعداد ' .  . ' اصطلاح با متادیتای کامل تام‌رنک به‌روزرسانی شد.';
    if () {
         .= ' تعداد ' . (int)  . ' ردیف متای حجیم رندرشده از دیتابیس حذف گردید.';
    }
    
    return array('ok' => true, 'message' => , 'log' => );
}
