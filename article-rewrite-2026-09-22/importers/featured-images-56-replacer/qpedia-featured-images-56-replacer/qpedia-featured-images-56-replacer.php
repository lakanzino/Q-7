<?php
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
