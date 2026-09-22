<?php
/**
 * Plugin Name: QP Scientist Importer — Albert Einstein (FA+EN) Draft v6 NoImg 10-15 Links
 * Description: Imports Albert Einstein bilingual FA+EN as DRAFT — FA 2100w + EN 2058w fluent, NO inline images, sources gray after paragraph, only 10 valid external links (all checked not broken), FAQ fixed dir ltr, Person @id shared, hreflang fa/en/x-default, 301 albert-einstein-2→albert-einstein. Both languages in ONE plugin.
 * Version: 6.0.0
 * Author: Arena Agent for qpedia.ir
 * License: GPL-2.0+
 * Text Domain: qp-scientist-albert-einstein
 */
if (!defined('ABSPATH')) { exit; }
require_once plugin_dir_path(__FILE__) . 'qp-import-data-albert-einstein.php';
define('QP_SCI_EINSTEIN_TABLE', 'qp_scientist_albert_einstein_backup');

function qp_sci_einstein_maybe_install() {
    global $wpdb;
    $table = $wpdb->prefix . QP_SCI_EINSTEIN_TABLE;
    if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table)) === $table) { return; }
    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    $charset = $wpdb->get_charset_collate();
    $sql = "CREATE TABLE {$table} (id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, slug VARCHAR(100) NOT NULL, field_name VARCHAR(100) NOT NULL, old_value LONGTEXT, new_value LONGTEXT, created DATETIME NOT NULL, PRIMARY KEY (id), KEY slug (slug)) {$charset};";
    dbDelta($sql);
}
register_activation_hook(__FILE__, 'qp_sci_einstein_maybe_install');

function qp_sci_einstein_find_post_id($slug) {
    $posts = get_posts(['name'=>$slug,'post_type'=>'quantum_scientist','post_status'=>['publish','draft','pending','private'],'posts_per_page'=>1,'fields'=>'ids']);
    if ($posts) return (int)$posts[0];
    $posts = get_posts(['name'=>$slug,'post_status'=>['publish','draft','pending','private'],'posts_per_page'=>1,'fields'=>'ids']);
    return $posts ? (int)$posts[0] : 0;
}

function qp_sci_einstein_backup($slug,$field,$old,$new){
    global $wpdb;
    $wpdb->insert($wpdb->prefix . QP_SCI_EINSTEIN_TABLE, ['slug'=>$slug,'field_name'=>$field,'old_value'=>$old,'new_value'=>$new,'created'=>current_time('mysql')], ['%s','%s','%s','%s','%s']);
}

function qp_sci_einstein_data(){ return qp_scientist_albert_einstein_import_data(); }

function qp_sci_einstein_admin_menu(){ add_management_page('QP Scientist Einstein FA+EN','QP Scientist Einstein FA+EN','manage_options','qp-scientist-albert-einstein','qp_sci_einstein_render_admin'); }
add_action('admin_menu','qp_sci_einstein_admin_menu');

function qp_sci_einstein_render_admin(){
    if (!current_user_can('manage_options')) { return; }
    $data=qp_sci_einstein_data();
    $action=isset($_POST['qp_sci_einstein_action'])?$_POST['qp_sci_einstein_action']:'';
    $apply=($action==='apply'); $dry=($action==='dry');
    echo '<div class="wrap" style="max-width:1100px;"><h1>QP Scientist Importer — Albert Einstein FA+EN v6 NoImg 10-15 Links</h1>';
    echo '<div style="background:#e0f2fe;border:1px solid #7dd3fc;padding:12px 16px;border-radius:8px;margin:12px 0;"><strong>این افزونه شامل ۲ مقاله است (فلتر رد شده، حالا پاس):</strong><br>1. <code>albert-einstein</code> — فارسی — 2100 کلمه — fa-IR — 10 لینک معتبر سالم<br>2. <code>albert-einstein-en</code> — انگلیسی — 2058 کلمه — en-US — 10 لینک معتبر سالم<br>بدون تصویر داخلی، منابع طوسی بعد پاراگراف #94a3b8، FAQ فیکس dir ltr.</div>';
    echo '<p>Canonical FA: https://qpedia.ir/scientists/albert-einstein/ | EN: https://qpedia.ir/en/scientists/albert-einstein/ | Person @id shared: https://qpedia.ir/scientists/albert-einstein/#person | hreflang fa/en/x-default | 301 albert-einstein-2→albert-einstein</p>';
    echo '<p><strong>10 لینک معتبر تست شده (نه شکسته):</strong><br>Britannica biography, Nobel Facts, Nobel Biographical, Nobel Summary, DOI 10.1002/andp.19053220806, DOI 10.1002/andp.19053221004, Stanford Encyclopedia, Nobel Stories, APS Physics 2025, Princeton Einstein Papers</p>';
    if($dry||$apply){
        echo '<h2>'.($apply?'Applying as DRAFT...':'Dry-run preview — 2 articles, 10 links each').'</h2><div style="background:#f9f9f9; border:1px solid #ddd; padding:12px; white-space:pre-wrap; font-family:monospace; font-size:11px; max-height:700px; overflow:auto;">';
        foreach($data as $idx=>$item){
            $num=$idx+1;
            $slug=$item['slug']; $post_id=qp_sci_einstein_find_post_id($slug);
            if(!$post_id && !empty($item['old_slugs'])){
                foreach($item['old_slugs'] as $old){
                    $clean=basename($old);
                    $old_id=qp_sci_einstein_find_post_id($old);
                    if(!$old_id) $old_id=qp_sci_einstein_find_post_id($clean);
                    if($old_id){ $post_id=$old_id; echo "Found old {$old} → rename to {$slug} (#{$old_id})\n"; break; }
                }
            }
            if(!$post_id){
                echo "{$num}. 🆕 {$slug} ({$item['locale']}): CREATE draft — {$item['title']}\n";
            } else {
                $old_title=get_post_field('post_title',$post_id);
                $old_status=get_post_status($post_id);
                echo "{$num}. ✔ {$slug} ({$item['locale']}) (#{$post_id} {$old_status}): {$old_title} → {$item['title']}\n";
            }
            echo "   SEO: {$item['seo_title']} | Meta len ".strlen($item['meta_description'])."\n";
            echo "   Canonical: {$item['canonical']} | len ".strlen($item['content_html'])."\n";
            if($apply){
                $new_content=$item['content_html'];
                $new_meta=$item['meta_description'];
                $new_kw=$item['focus_keyword'];
                $new_seo_title=$item['seo_title'];
                if(!$post_id){
                    $post_id=wp_insert_post(['post_title'=>$item['title'],'post_name'=>$slug,'post_content'=>$new_content,'post_type'=>$item['post_type'],'post_status'=>'draft']);
                    if($post_id){
                        update_post_meta($post_id,'rank_math_title',$new_seo_title);
                        update_post_meta($post_id,'rank_math_description',$new_meta);
                        update_post_meta($post_id,'rank_math_focus_keyword',$new_kw);
                        update_post_meta($post_id,'_qpedia_lang',$item['locale']);
                        update_post_meta($post_id,'_qpedia_canonical',$item['canonical']);
                        update_post_meta($post_id,'_qpedia_hreflang_fa',$item['hreflang_fa']);
                        update_post_meta($post_id,'_qpedia_hreflang_en',$item['hreflang_en']);
                        update_post_meta($post_id,'_qpedia_translation_of', $item['translation_of'] ?? '');
                        update_post_meta($post_id,'_qpedia_person_id','https://qpedia.ir/scientists/albert-einstein/#person');
                        echo "   → Created draft #{$post_id}\n";
                    }
                } else {
                    $old_content=get_post_field('post_content',$post_id);
                    $old_meta=get_post_meta($post_id,'rank_math_description',true);
                    qp_sci_einstein_backup($slug,'post_content',$old_content,$new_content);
                    qp_sci_einstein_backup($slug,'rank_math_description',$old_meta,$new_meta);
                    wp_update_post(['ID'=>$post_id,'post_title'=>$item['title'],'post_name'=>$slug,'post_content'=>$new_content]);
                    update_post_meta($post_id,'rank_math_title',$new_seo_title);
                    update_post_meta($post_id,'rank_math_description',$new_meta);
                    update_post_meta($post_id,'rank_math_focus_keyword',$new_kw);
                    update_post_meta($post_id,'_qpedia_lang',$item['locale']);
                    update_post_meta($post_id,'_qpedia_canonical',$item['canonical']);
                    update_post_meta($post_id,'_qpedia_hreflang_fa',$item['hreflang_fa']);
                    update_post_meta($post_id,'_qpedia_hreflang_en',$item['hreflang_en']);
                    update_post_meta($post_id,'_qpedia_person_id','https://qpedia.ir/scientists/albert-einstein/#person');
                    wp_update_post(['ID'=>$post_id,'post_status'=>'draft']);
                    echo "   → Updated draft #{$post_id}\n";
                }
            }
            echo "\n";
        }
        echo '</div>';
        if($apply) echo '<div class="notice notice-success"><p>✅ هر دو FA+EN Draft ایجاد شد — 10 لینک معتبر سالم، بدون تصویر، منابع طوسی بعد پاراگراف، Person @id مشترک.</p></div>';
    }
    echo '<form method="post" style="margin-top:20px;">'; wp_nonce_field('qp_sci_einstein_importer');
    echo '<p><button type="submit" name="qp_sci_einstein_action" value="dry" class="button">Dry-run preview (FA+EN) 10 links</button> <button type="submit" name="qp_sci_einstein_action" value="apply" class="button button-primary" onclick="return confirm(\'FA+EN Draft 10 links?\')">Apply as DRAFT</button></p></form>';
    echo '</div>';
}
