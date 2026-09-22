<?php
/**
 * Plugin Name: QP 100 Importer v2 v1 v1 — 6 مقاله 100 امتیازی qpedia.ir
 * Description: 5 مقاله اول بازنویسی‌شده به هدف 100 امتیاز (simon-algorithm و 4 مقاله ته‌جدول) را با متا، کلمه کلیدی، محتوا و پیشنهادات لینک ورودی وارد می‌کند. پیش‌نمایش خشک دارد، پشتیبان می‌گیرد، قابل بازگشت است.
 * Version: 1.0.0
 * Author: Arena Agent for qpedia.ir
 * License: GPL-2.0+
 * Text Domain: qp100v2v1
 */

if (!defined('ABSPATH')) { exit; }

require_once plugin_dir_path(__FILE__) . 'qp-import-data-v2.php';

define('QP100V1_TABLE', 'qp100v1_backup');

function qp100_maybe_install() {
    global $wpdb;
    $table = $wpdb->prefix . QP100V1_TABLE;
    if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table)) === $table) { return; }
    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    $charset = $wpdb->get_charset_collate();
    $sql = "CREATE TABLE {$table} (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        slug VARCHAR(100) NOT NULL,
        field_name VARCHAR(100) NOT NULL,
        old_value LONGTEXT,
        new_value LONGTEXT,
        created DATETIME NOT NULL,
        PRIMARY KEY (id),
        KEY slug (slug)
    ) {$charset};";
    dbDelta($sql);
}
register_activation_hook(__FILE__, 'qp100_maybe_install');

function qp100_find_post_id($slug) {
    $posts = get_posts([
        'name' => $slug,
        'post_type' => 'quantum_article',
        'post_status' => 'publish',
        'posts_per_page' => 1,
        'fields' => 'ids',
    ]);
    return $posts ? (int)$posts[0] : 0;
}

function qp100v1_backup($slug, $field, $old, $new) {
    global $wpdb;
    $wpdb->insert(
        $wpdb->prefix . QP100V1_TABLE,
        [
            'slug' => $slug,
            'field_name' => $field,
            'old_value' => $old,
            'new_value' => $new,
            'created' => current_time('mysql'),
        ],
        ['%s','%s','%s','%s','%s']
    );
}

function qp100_data() {
    return qp100v2_import_data();
}

function qp100_admin_menu() {
    add_management_page(
        'QP 100 Importer v2 v1',
        'QP 100 Importer v2 v1',
        'manage_options',
        'qp-100-importer-v1',
        'qp100_render_admin'
    );
}
add_action('admin_menu', 'qp100_admin_menu');

function qp100_render_admin() {
    if (!current_user_can('manage_options')) { return; }
    $data = qp100_data();
    $action = isset($_POST['qp100_action']) ? $_POST['qp100_action'] : '';
    $apply = ($action === 'apply');
    $dry = ($action === 'dry');

    echo '<div class="wrap" dir="rtl" style="font-family:Vazirmatn, Tahoma; max-width:900px;">';
    echo '<h1>QP 100 Importer v2 v1 — واردکننده 5 مقاله 100 امتیازی</h1>';
    echo '<p>این افزونه 5 مقاله ته‌جدول را به نسخه 100 امتیازی بازنویسی‌شده ارتقا می‌دهد. ابتدا پیش‌نمایش خشک بزنید.</p>';

    if ($dry || $apply) {
        echo '<h2>'.($apply ? 'در حال اعمال...' : 'پیش‌نمایش خشک').'</h2>';
        echo '<div style="background:#f9f9f9; border:1px solid #ddd; padding:12px; white-space:pre-wrap; font-family:monospace; font-size:12px; max-height:600px; overflow:auto;">';
        foreach ($data as $item) {
            $slug = $item['slug'];
            $post_id = qp100_find_post_id($slug);
            if (!$post_id) {
                echo "⚠️ {$slug}: پست منتشرشده یافت نشد (شاید اسلاگ تغییر کرده)\n";
                continue;
            }
            $old_content = get_post_field('post_content', $post_id);
            $old_title = get_post_field('post_title', $post_id);
            $old_meta = get_post_meta($post_id, 'rank_math_description', true);
            $old_kw = get_post_meta($post_id, 'rank_math_focus_keyword', true);
            $old_seo_title = get_post_meta($post_id, 'rank_math_title', true);

            $new_content = $item['content_html'];
            $new_meta = $item['meta_description'];
            $new_kw = $item['focus_keyword'];
            $new_seo_title = $item['seo_title'];

            $diff_words = str_word_count(strip_tags($new_content)) - str_word_count(strip_tags($old_content));
            echo "✔ {$slug} (#{$post_id}): {$old_title} → {$item['title']}\n";
            echo "  کلمات: قبل ".str_word_count(strip_tags($old_content))." → بعد ".str_word_count(strip_tags($new_content))." (اختلاف +{$diff_words})\n";
            echo "  SEO Title: ".($old_seo_title ? $old_seo_title : '(خالی)')." → {$new_seo_title}\n";
            echo "  Meta: ".($old_meta ? mb_substr($old_meta,0,50).'...' : '(خالی)')." → {$new_meta}\n";
            echo "  KW: ".($old_kw ? $old_kw : '(خالی)')." → {$new_kw}\n";
            echo "  Featured: {$item['featured_image']} alt: {$item['featured_alt']}\n";
            echo "  Inbound پیشنهادی: ".implode(', ', array_map(fn($x)=>$x['from'].'('.$x['anchor'].')', $item['inbound_suggestions']))."\n";

            if ($apply) {
                qp100v1_backup($slug, 'post_content', $old_content, $new_content);
                qp100v1_backup($slug, 'rank_math_description', $old_meta, $new_meta);
                qp100v1_backup($slug, 'rank_math_focus_keyword', $old_kw, $new_kw);
                qp100v1_backup($slug, 'rank_math_title', $old_seo_title, $new_seo_title);

                wp_update_post(['ID' => $post_id, 'post_content' => $new_content]);
                update_post_meta($post_id, 'rank_math_description', $new_meta);
                update_post_meta($post_id, 'rank_math_focus_keyword', $new_kw);
                update_post_meta($post_id, 'rank_math_title', $new_seo_title);
                // Also set focus keyword for Qpedia legacy
                update_post_meta($post_id, '_qpedia_focus_keyphrase', $new_kw);
            }
            echo "\n";
        }
        echo '</div>';
        if ($apply) {
            echo '<div class="notice notice-success"><p>اعمال شد. پشتیبان در جدول '.esc_html($GLOBALS['wpdb']->prefix . QP100V1_TABLE).' ذخیره شد.</p></div>';
        }
    }

    echo '<form method="post" style="margin-top:20px;">';
    wp_nonce_field('qp100_importer');
    echo '<p><button type="submit" name="qp100_action" value="dry" class="button">پیش‌نمایش خشک (5 مقاله)</button> ';
    echo '<button type="submit" name="qp100_action" value="apply" class="button button-primary" onclick="return confirm(\'آیا مطمئن هستید؟ پشتیبان گرفته می‌شود.\')">اعمال (با پشتیبان)</button></p>';
    echo '</form>';

    echo '<h2>لیست 5 مقاله</h2><ol>';
    foreach ($data as $item) {
        echo '<li><code>'.$item['slug'].'</code> — '.$item['title'].' — امتیاز قبلی: '.$item['old_score'].' → هدف 100</li>';
    }
    echo '</ol>';

    echo '<h3>تصاویر شاخص پیشنهادی</h3><ul>';
    foreach ($data as $item) {
        echo '<li>'.$item['slug'].': '.$item['featured_image'].' — ALT: '.$item['featured_alt'].'</li>';
    }
    echo '</ul>';

    echo '</div>';
}
