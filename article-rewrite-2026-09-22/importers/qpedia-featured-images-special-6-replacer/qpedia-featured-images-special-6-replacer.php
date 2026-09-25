<?php
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
