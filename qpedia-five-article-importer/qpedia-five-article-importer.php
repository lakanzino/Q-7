<?php
/**
 * Plugin Name: Qpedia – درون‌ریز یک‌بارمصرف پنج مقاله
 * Description: پنج مقاله بررسی‌شده Qpedia را همراه تصویر شاخص، طبقه‌بندی، متادیتای سئو و FAQ Schema به‌صورت پیش‌نویس وارد می‌کند.
 * Version: 1.0.0
 * Author: Qpedia Editorial
 */
if (!defined('ABSPATH')) exit;

add_action('admin_menu', function () {
    add_management_page('درون‌ریزی پنج مقاله Qpedia','درون‌ریز Qpedia','manage_options','qpedia-five-import','qp5_page');
});

function qp5_page() {
    if (!current_user_can('manage_options')) return;
    echo '<div class="wrap" dir="rtl"><h1>درون‌ریزی پنج مقاله Qpedia</h1>';
    if (!empty($_POST['qp5_import'])) {
        check_admin_referer('qp5_import_action');
        $results = qp5_import_all();
        echo '<div class="notice notice-success"><p><strong>عملیات تمام شد.</strong></p><ul>';
        foreach ($results as $result) echo '<li>'.esc_html($result).'</li>';
        echo '</ul></div>';
    }
    echo '<p>مقاله‌ها فقط به‌صورت <strong>پیش‌نویس</strong> ساخته می‌شوند. اجرای دوباره، مقاله موجود با همان اسلاگ را تکثیر نمی‌کند.</p>';
    echo '<form method="post">'; wp_nonce_field('qp5_import_action');
    submit_button('ساخت پنج پیش‌نویس','primary','qp5_import'); echo '</form></div>';
}

function qp5_import_all() {
    $json = file_get_contents(plugin_dir_path(__FILE__).'articles.json');
    $items = json_decode($json, true);
    if (!is_array($items)) return array('خطا: فایل داده خوانده نشد.');
    $results = array();
    foreach ($items as $item) {
        $existing = get_page_by_path($item['slug'], OBJECT, 'quantum_article');
        if ($existing) { $results[] = 'رد شد (از قبل موجود): '.$item['title']; continue; }
        $post_id = wp_insert_post(array(
            'post_type'=>'quantum_article','post_status'=>'draft','post_title'=>$item['title'],
            'post_name'=>$item['slug'],'post_content'=>$item['content'],'post_excerpt'=>$item['excerpt'],
            'post_author'=>get_current_user_id(),'comment_status'=>'open'
        ), true);
        if (is_wp_error($post_id)) { $results[]='خطا در '.$item['title'].': '.$post_id->get_error_message(); continue; }

        if (taxonomy_exists('quantum_category')) {
            $term = term_exists('technology','quantum_category');
            if (!$term) $term = wp_insert_term('فناوری و کاربردهای کوانتومی','quantum_category',array('slug'=>'technology'));
            if (!is_wp_error($term)) wp_set_object_terms($post_id,array((int)(is_array($term)?$term['term_id']:$term)),'quantum_category');
        }
        wp_set_post_tags($post_id,$item['tags'],false);
        $metas = array(
            '_qpedia_seo_title'=>$item['seo'],'_qpedia_meta_description'=>$item['desc'],
            '_qpedia_focus_keyphrase'=>$item['focus'],'_qpedia_secondary_keyphrases'=>$item['secondary'],
            '_qpedia_tags'=>implode('، ',$item['tags']),'_jetica_seo_title'=>$item['seo'],
            '_jetica_meta_description'=>$item['desc'],'_jetica_focus_keyword'=>$item['focus'],
            '_jetica_schema_enabled'=>'1','_jetica_schema_type'=>'Article',
            'rank_math_title'=>$item['seo'],'rank_math_description'=>$item['desc'],'rank_math_focus_keyword'=>$item['focus'],
            '_yoast_wpseo_title'=>$item['seo'],'_yoast_wpseo_metadesc'=>$item['desc'],'_yoast_wpseo_focuskw'=>$item['focus']
        );
        foreach ($metas as $key=>$value) update_post_meta($post_id,$key,$value);

        $entities=array();
        foreach ($item['faqs'] as $faq) $entities[]=array('@type'=>'Question','name'=>$faq['question'],'acceptedAnswer'=>array('@type'=>'Answer','text'=>$faq['answer']));
        update_post_meta($post_id,'rank_math_schema_FAQPage',array('@context'=>'https://schema.org','@type'=>'FAQPage','mainEntity'=>$entities));
        update_post_meta($post_id,'_qpedia_faq_schema',wp_json_encode(array('@context'=>'https://schema.org','@type'=>'FAQPage','mainEntity'=>$entities),JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES));

        $attachment_id = qp5_attach_image($post_id,$item);
        if (is_wp_error($attachment_id)) $results[]='ساخته شد، اما خطای تصویر برای '.$item['title'].': '.$attachment_id->get_error_message();
        else { set_post_thumbnail($post_id,$attachment_id); $results[]='پیش‌نویس ساخته شد: '.$item['title']; }
    }
    return $results;
}

function qp5_attach_image($post_id,$item) {
    $path=plugin_dir_path(__FILE__).'assets/'.$item['image'];
    if (!file_exists($path)) return new WP_Error('missing_image','فایل تصویر پیدا نشد.');
    $bits=wp_upload_bits($item['image'],null,file_get_contents($path));
    if (!empty($bits['error'])) return new WP_Error('upload_error',$bits['error']);
    $type=wp_check_filetype($bits['file'],null);
    $id=wp_insert_attachment(array('post_mime_type'=>$type['type'],'post_title'=>pathinfo($item['image'],PATHINFO_FILENAME),'post_excerpt'=>$item['caption'],'post_content'=>'','post_status'=>'inherit'),$bits['file'],$post_id,true);
    if (is_wp_error($id)) return $id;
    require_once ABSPATH.'wp-admin/includes/image.php';
    $meta=wp_generate_attachment_metadata($id,$bits['file']);
    if ($meta) wp_update_attachment_metadata($id,$meta);
    update_post_meta($id,'_wp_attachment_image_alt',$item['alt']);
    return $id;
}
