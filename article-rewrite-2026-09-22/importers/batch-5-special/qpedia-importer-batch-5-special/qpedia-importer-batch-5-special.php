<?php
/**
 * Plugin Name: Qpedia Importer - 5 Special Advanced Articles
 * Description: درون‌ریز ۵ مقاله تخصصی و جامع جدید Qpedia (شامل نظریه بازی کوانتومی، بازی‌های کوانتومی، مالی کوانتومی، مسابقه تسلیحاتی و توافق کلید کنفرانسی) با سئوی کامل TamRank، اسکیما و متادیتا.
 * Version: 1.0.0
 * Author: Qpedia Team
 * Text Domain: qpedia-importer-batch-5-special
 */

defined('ABSPATH') || exit;

add_action('admin_menu', 'qp_batch5_admin_menu');
function qp_batch5_admin_menu() {
    add_management_page(
        'درون‌ریز ۵ مقاله تخصصی کوانتوم',
        'درون‌ریز ۵ مقاله کوانتوم',
        'manage_options',
        'qpedia-importer-batch-5-special',
        'qp_batch5_render_page'
    );
}

function qp_batch5_render_page() {
    if (!current_user_can('manage_options')) {
        return;
    }

    echo '<div class="wrap" dir="rtl" style="font-family:Tahoma,sans-serif;line-height:1.7;">';
    echo '<h1>📥 درون‌ریز ۵ مقاله تخصصی و جامع جدید Qpedia</h1>';

    if (isset($_POST['qp_batch5_run'])) {
        check_admin_referer('qp_batch5_action_nonce');
        $result = qp_batch5_execute_import();

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
    }

    echo '<div class="card" style="max-width:850px;margin-top:20px;padding:15px 25px;">';
    echo '<h2>مشخصات بسته مقالات:</h2>';
    echo '<ol style="font-size:14px;line-height:1.9;">';
    echo '<li><strong>۱۸۵. نظریه بازی کوانتومی</strong> (<code>quantum-game-theory</code>) - بیش از ۲۱۰۰ کلمه</li>';
    echo '<li><strong>۱۸۶. بازی‌های کوانتومی و شطرنج کوانتومی</strong> (<code>quantum-games</code>) - بیش از ۱۸۹۰ کلمه</li>';
    echo '<li><strong>۱۸۷. مالی کوانتومی</strong> (<code>quantum-finance</code>) - بیش از ۱۸۴۰ کلمه</li>';
    echo '<li><strong>۱۸۸. مسابقه تسلیحاتی کوانتومی</strong> (<code>quantum-arms-race</code>) - بیش از ۱۸۰۰ کلمه</li>';
    echo '<li><strong>۱۸۹. توافق کلید کنفرانسی کوانتومی</strong> (<code>quantum-conference-key-agreement</code>) - بیش از ۱۹۵۰ کلمه</li>';
    echo '</ol>';

    echo '<form method="post" style="margin-top:20px;">';
    wp_nonce_field('qp_batch5_action_nonce');
    submit_button('شروع درون‌ریزی و به‌روزرسانی ۵ مقاله تخصصی', 'primary', 'qp_batch5_run');
    echo '</form>';
    echo '</div></div>';
}

function qp_batch5_execute_import() {
    $data_file = plugin_dir_path(__FILE__) . 'articles_data.json';
    if (!is_readable($data_file)) {
        return array('ok' => false, 'message' => 'فایل داده‌های مقالات یافت نشد.', 'log' => array());
    }

    $articles = json_decode(file_get_contents($data_file), true);
    if (!is_array($articles) || empty($articles)) {
        return array('ok' => false, 'message' => 'فرمت داده‌ها نامعتبر است.', 'log' => array());
    }

    $imported = 0;
    $updated = 0;
    $log = array();

    foreach ($articles as $art) {
        $slug      = trim((string)$art['slug']);
        $h1        = trim((string)$art['h1']);
        $seo_title = trim((string)$art['seo_title']);
        $meta_desc = trim((string)$art['meta_desc']);
        $focus_kw  = trim((string)$art['focus_kw']);
        $content   = (string)$art['content'];
        $date      = (string)$art['date'];
        $modified  = (string)$art['modified'];

        $existing = get_posts(array(
            'name'        => $slug,
            'post_type'   => array('quantum_article', 'post'),
            'post_status' => 'any',
            'numberposts' => 1
        ));

        $post_type = post_type_exists('quantum_article') ? 'quantum_article' : 'post';

        if (!empty($existing)) {
            $post_id = (int)$existing[0]->ID;
            wp_update_post(array(
                'ID'           => $post_id,
                'post_title'   => $h1,
                'post_content' => $content,
                'post_modified'=> $modified,
                'post_modified_gmt' => get_gmt_from_date($modified),
                'post_status'  => 'publish'
            ));
            $updated++;
            $log[] = '🔄 به‌روزرسانی مقاله موجود: «' . $h1 . '» (شناسه ' . $post_id . ')';
        } else {
            $post_id = wp_insert_post(array(
                'post_name'    => $slug,
                'post_title'   => $h1,
                'post_content' => $content,
                'post_status'  => 'publish',
                'post_type'    => $post_type,
                'post_date'    => $date,
                'post_date_gmt'=> get_gmt_from_date($date),
                'post_modified'=> $modified,
                'post_modified_gmt' => get_gmt_from_date($modified)
            ));
            $imported++;
            $log[] = '✨ ایجاد مقاله جدید: «' . $h1 . '» (شناسه ' . $post_id . ')';
        }

        if ($post_id > 0) {
            update_post_meta($post_id, '_tamrank_seo_title', $seo_title);
            update_post_meta($post_id, '_tamrank_seo_description', $meta_desc);
            update_post_meta($post_id, '_tamrank_seo_focus_keyword', $focus_kw);

            update_post_meta($post_id, 'rank_math_title', $seo_title);
            update_post_meta($post_id, 'rank_math_description', $meta_desc);
            update_post_meta($post_id, 'rank_math_focus_keyword', $focus_kw);

            if (!empty($art['schema'])) {
                update_post_meta($post_id, '_qpedia_schema_json', wp_json_encode($art['schema']));
            }

            // تنظیم خودکار تصویر شاخص WebP و متن جایگزین سئو
            if (!empty($art['featured_image'])) {
                $img_name = basename((string)$art['featured_image']);
                $img_file = plugin_dir_path(__FILE__) . 'images/' . $img_name;

                if (file_exists($img_file)) {
                    $upload_dir = wp_upload_dir();
                    $target_file = $upload_dir['path'] . '/' . $img_name;

                    if (!file_exists($target_file)) {
                        wp_mkdir_p($upload_dir['path']);
                        copy($img_file, $target_file);
                    }

                    $base_slug = pathinfo($img_name, PATHINFO_FILENAME);
                    $existing_img = get_posts(array(
                        'post_type'      => 'attachment',
                        'name'           => $base_slug,
                        'posts_per_page' => 1,
                        'post_status'    => 'inherit'
                    ));

                    if (!empty($existing_img)) {
                        $attach_id = (int)$existing_img[0]->ID;
                    } else {
                        $wp_filetype = wp_check_filetype($img_name, null);
                        $attachment = array(
                            'guid'           => $upload_dir['url'] . '/' . $img_name,
                            'post_mime_type' => !empty($wp_filetype['type']) ? $wp_filetype['type'] : 'image/webp',
                            'post_title'     => sanitize_text_field(!empty($art['image_title']) ? $art['image_title'] : $h1),
                            'post_content'   => '',
                            'post_status'    => 'inherit'
                        );
                        $attach_id = wp_insert_attachment($attachment, $target_file, $post_id);
                        if (!is_wp_error($attach_id) && $attach_id > 0) {
                            require_once(ABSPATH . 'wp-admin/includes/image.php');
                            $attach_data = wp_generate_attachment_metadata($attach_id, $target_file);
                            wp_update_attachment_metadata($attach_id, $attach_data);
                        }
                    }

                    if (!empty($attach_id) && !is_wp_error($attach_id)) {
                        set_post_thumbnail($post_id, $attach_id);
                        update_post_meta($attach_id, '_wp_attachment_image_alt', sanitize_text_field(!empty($art['image_alt']) ? $art['image_alt'] : $h1));
                        update_post_meta($post_id, '_thumbnail_id', $attach_id);
                        $log[] = '🖼️ تصویر شاخص WebP متصل شد: ' . $img_name . ' (شناسه رسانه ' . $attach_id . ')';
                    }
                }
            }
        }
    }

    $msg = 'عملیات با موفقیت انجام شد: ' . $imported . ' مقاله جدید ایجاد و ' . $updated . ' مقاله به‌روزرسانی گردید.';
    return array('ok' => true, 'message' => $msg, 'log' => $log);
}
