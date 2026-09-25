<?php
/**
 * Plugin Name: Qpedia Glossary 200 Featured Images Importer
 * Description: افزونه خودکار اتصال و جایگزینی 200 تصویر مینیمال و استاندارد WebP برای اصطلاحات واژه‌نامه تخصصی کوانتوم با نشان qpedia.ir و متادیتاهای کامل سئو در وردپرس.
 * Version: 1.0.0
 * Author: Qpedia Team
 * Text Domain: qpedia-glossary-200-images
 */

defined('ABSPATH') || exit;

add_action('admin_menu', 'qp_glossary_img_menu');
function qp_glossary_img_menu() {
    add_management_page(
        'اتصال تصاویر اصطلاحات واژه‌نامه',
        'تصاویر واژه‌نامه (200)',
        'manage_options',
        'qpedia-glossary-200-images',
        'qp_glossary_img_render_page'
    );
}

function qp_glossary_img_render_page() {
    if (!current_user_can('manage_options')) {
        return;
    }

    echo '<div class="wrap" dir="rtl" style="font-family:Tahoma,sans-serif;line-height:1.7;">';
    echo '<h1>🎨 اتصال و جایگزینی خودکار ۲۰۰ تصویر مینیمال واژه‌نامه کوانتوم</h1>';

    if (isset($_POST['qp_run_glossary_images'])) {
        check_admin_referer('qp_glossary_img_nonce');
        $result = qp_glossary_img_execute();

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
    echo '<h2>مشخصات بسته تصاویر مینیمال واژه‌نامه:</h2>';
    echo '<p>این بسته شامل <strong>۲۰۰ تصویر شاخص مینیمال WebP</strong> در ابعاد استاندارد ۱۶:۹ (1200x675) با نشان رسمی <code>qpedia.ir</code>، تفکیک رنگی ۱۲ دسته، الگوهای هندسی وکتور و متن‌های جایگزین سئو است.</p>';
    echo '<p style="color:#555;">با کلیک روی دکمه زیر، افزونه تمام اصطلاحات پست‌تایپ <code>qp_glossary</code> را شناسایی و تصویر مینیمال مربوط به هر اصطلاح را به عنوان تصویر شاخص متصل می‌نماید.</p>';

    echo '<form method="post" style="margin-top:20px;">';
    wp_nonce_field('qp_glossary_img_nonce');
    submit_button('شروع اتصال یک‌جای تصاویر به ۲۰۰ اصطلاح واژه‌نامه', 'primary', 'qp_run_glossary_images');
    echo '</form>';
    echo '</div></div>';
}

function qp_glossary_img_execute() {
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
        $term      = trim((string)$item['term']);
        $slug      = trim((string)$item['slug']);
        $filename  = basename((string)$item['filename']);
        $alt_text  = trim((string)$item['alt']);

        // Find post by term title in qp_glossary
        $posts = get_posts(array(
            'post_type'   => array('qp_glossary', 'post'),
            'post_status' => 'any',
            'title'       => $term,
            'numberposts' => 1
        ));

        // Fallback by slug
        if (empty($posts)) {
            $posts = get_posts(array(
                'name'        => $slug,
                'post_type'   => array('qp_glossary', 'post'),
                'post_status' => 'any',
                'numberposts' => 1
            ));
        }

        if (empty($posts)) {
            $log[] = '⚠️ اصطلاح «' . $term . '» در سایت یافت نشد.';
            $count_miss++;
            continue;
        }

        $post_id = (int)$posts[0]->ID;
        $img_src = plugin_dir_path(__FILE__) . 'images/' . $filename;

        if (!file_exists($img_src)) {
            $log[] = '❌ فایل تصویر «' . $filename . '» در افزونه موجود نیست.';
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
                'post_title'     => sanitize_text_field('تصویر ' . $term),
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
            $log[] = '✅ تصویر مینیمال «' . $term . '» متصل شد -> ' . $filename . ' (شناسه ' . $attach_id . ')';
            $count_ok++;
        } else {
            $log[] = '❌ خطا در اتصال تصویر برای اصطلاح ' . $term;
            $count_miss++;
        }
    }

    $msg = 'عملیات موفقیت‌آمیز: ' . $count_ok . ' تصویر مینیمال با نشان qpedia.ir به اصطلاحات واژه‌نامه متصل گردید.';
    return array('ok' => true, 'message' => $msg, 'log' => $log);
}
