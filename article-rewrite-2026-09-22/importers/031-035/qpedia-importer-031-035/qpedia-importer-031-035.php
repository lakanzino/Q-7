<?php
/**
 * Plugin Name: Qpedia One-Time Importer 031–035
 * Description: جایگزینی کنترل‌شده مقاله‌های ۳۱ تا ۳۵ Qpedia و ثبت متادیتای Rank Math؛ فقط یک بار قابل اجرا است.
 * Version: 1.0.0
 * Author: Qpedia Editorial
 */
if (!defined('ABSPATH')) exit;

const QP5_OPTION = 'qpedia_importer_031_035_completed';

add_action('admin_menu', function () {
    add_management_page('درون‌ریز Qpedia 031–035', 'درون‌ریز Qpedia 031–035', 'manage_options', 'qpedia-importer-031-035', 'qp5_render_page');
});

function qp5_render_page() {
    if (!current_user_can('manage_options')) return;
    $done = get_option(QP5_OPTION);
    echo '<div class="wrap" dir="rtl"><h1>درون‌ریز یک‌بارمصرف مقاله‌های ۳۱ تا ۳۵</h1>';
    if ($done) {
        echo '<div class="notice notice-success"><p>این بسته قبلاً در '.esc_html($done).' اجرا شده است و دوباره اجرا نمی‌شود.</p></div></div>';
        return;
    }
    if (isset($_POST['qp5_run'])) {
        check_admin_referer('qp5_run_import');
        $results = qp5_run_import();
        $has_error = false;
        foreach ($results as $result) if (!$result['ok']) $has_error = true;
        echo '<div class="notice '.($has_error ? 'notice-warning' : 'notice-success').' "><ul>';
        foreach ($results as $result) echo '<li>'.esc_html($result['message']).'</li>';
        echo '</ul></div>';
        if (!$has_error) {
            update_option(QP5_OPTION, current_time('mysql'), false);
            echo '<p><strong>هر ۵ مقاله به‌روزرسانی شدند. افزونه را اکنون غیرفعال و حذف کنید.</strong></p></div>';
            return;
        }
    }
    echo '<p>این ابزار عنوان، متن و متادیتای SEO مقاله‌های موجود را به‌روزرسانی می‌کند. وضعیت انتشار و نویسنده تغییر نمی‌کند. قبل از اجرا از پایگاه داده پشتیبان بگیرید.</p>';
    echo '<form method="post">';
    wp_nonce_field('qp5_run_import');
    submit_button('به‌روزرسانی مقاله‌های ۳۱ تا ۳۵', 'primary', 'qp5_run');
    echo '</form></div>';
}

function qp5_run_import() {
    $path = plugin_dir_path(__FILE__).'articles.json';
    $items = json_decode(file_get_contents($path), true);
    if (!is_array($items) || count($items) !== 5) return array(array('ok'=>false, 'message'=>'خطا: فایل داده معتبر نیست.'));
    $results = array();
    foreach ($items as $item) {
        $post = get_post((int)$item['post_id']);
        if (!$post || $post->post_type !== 'quantum_article') {
            $post = get_page_by_path(sanitize_title($item['slug']), OBJECT, 'quantum_article');
        }
        if (!$post) {
            $results[] = array('ok'=>false, 'message'=>'پیدا نشد: '.$item['title'].' (ID '.$item['post_id'].')');
            continue;
        }
        $updated = wp_update_post(array(
            'ID' => $post->ID,
            'post_title' => $item['title'],
            'post_name' => $item['slug'],
            'post_content' => $item['content']
        ), true);
        if (is_wp_error($updated)) {
            $results[] = array('ok'=>false, 'message'=>'خطا در '.$item['title'].': '.$updated->get_error_message());
            continue;
        }
        $metas = array(
            'rank_math_focus_keyword' => $item['focus_keyword'],
            'rank_math_title' => $item['seo_title'],
            'rank_math_description' => $item['description'],
            '_qpedia_focus_keyphrase' => $item['focus_keyword'],
            '_qpedia_seo_title' => $item['seo_title'],
            '_qpedia_meta_description' => $item['description'],
            '_yoast_wpseo_focuskw' => $item['focus_keyword'],
            '_yoast_wpseo_title' => $item['seo_title'],
            '_yoast_wpseo_metadesc' => $item['description']
        );
        foreach ($metas as $key => $value) update_post_meta($post->ID, $key, $value);
        $t=$item['taxonomy']; $mother=term_exists($t['mother_slug'],'quantum_category'); if(!$mother)$mother=wp_insert_term($t['mother_name'],'quantum_category',array('slug'=>$t['mother_slug'])); if(is_wp_error($mother)){ $results[]=array('ok'=>false,'message'=>'خطای دسته مادر: '.$item['title']); continue; } $mid=is_array($mother)?(int)$mother['term_id']:(int)$mother; $child=term_exists($t['category_slug'],'quantum_category'); if(!$child)$child=wp_insert_term($t['category_name'],'quantum_category',array('slug'=>$t['category_slug'],'parent'=>$mid)); if(is_wp_error($child)){ $results[]=array('ok'=>false,'message'=>'خطای دسته: '.$item['title']); continue; } $cid=is_array($child)?(int)$child['term_id']:(int)$child; wp_set_object_terms($post->ID,array($mid,$cid),'quantum_category',false);
        if (!empty($item['faqs'])) {
            $entities = array();
            foreach ($item['faqs'] as $faq) {
                $entities[] = array('@type'=>'Question', 'name'=>$faq['question'], 'acceptedAnswer'=>array('@type'=>'Answer', 'text'=>$faq['answer']));
            }
            $schema = array('@context'=>'https://schema.org', '@type'=>'FAQPage', 'mainEntity'=>$entities);
            update_post_meta($post->ID, 'rank_math_schema_FAQPage', $schema);
            update_post_meta($post->ID, '_qpedia_faq_schema', wp_json_encode($schema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        }
        clean_post_cache($post->ID);
        $results[] = array('ok'=>true, 'message'=>'به‌روزرسانی شد: '.$item['title'].' (ID '.$post->ID.')');
    }
    return $results;
}
