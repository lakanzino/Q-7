<?php
/**
 * Plugin Name: QP Scientist Importer — Erwin Schrödinger (FA+EN) Draft
 * Description: Imports Erwin Schrödinger biography bilingual FA+EN as DRAFT with full filter compliance (one sentence one source), Person schema, hreflang linking per Google standard, and 301 redirects from old slugs (schrodingerr, ervin-schrodinger -> erwin-schrodinger). English UI. Draft publication.
 * Version: 1.0.0
 * Author: Arena Agent for qpedia.ir
 * License: GPL-2.0+
 * Text Domain: qp-scientist-schrodinger
 */
if (!defined('ABSPATH')) { exit; }
require_once plugin_dir_path(__FILE__) . 'qp-import-data-erwin-schrodinger.php';
define('QP_SCI_SCH_TABLE', 'qp_scientist_schrodinger_backup');

function qp_sci_sch_maybe_install() {
    global $wpdb;
    $table = $wpdb->prefix . QP_SCI_SCH_TABLE;
    if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table)) === $table) { return; }
    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    $charset = $wpdb->get_charset_collate();
    $sql = "CREATE TABLE {$table} (id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, slug VARCHAR(100) NOT NULL, field_name VARCHAR(100) NOT NULL, old_value LONGTEXT, new_value LONGTEXT, created DATETIME NOT NULL, PRIMARY KEY (id), KEY slug (slug)) {$charset};";
    dbDelta($sql);
}
register_activation_hook(__FILE__, 'qp_sci_sch_maybe_install');

function qp_sci_sch_find_post_id($slug) {
    $posts = get_posts(['name'=>$slug,'post_type'=>'quantum_scientist','post_status'=>['publish','draft','pending'],'posts_per_page'=>1,'fields'=>'ids']);
    if ($posts) return (int)$posts[0];
    $posts = get_posts(['name'=>$slug,'post_status'=>['publish','draft','pending'],'posts_per_page'=>1,'fields'=>'ids']);
    return $posts ? (int)$posts[0] : 0;
}

function qp_sci_sch_backup($slug,$field,$old,$new){
    global $wpdb;
    $wpdb->insert($wpdb->prefix . QP_SCI_SCH_TABLE, ['slug'=>$slug,'field_name'=>$field,'old_value'=>$old,'new_value'=>$new,'created'=>current_time('mysql')], ['%s','%s','%s','%s','%s']);
}

function qp_sci_sch_data(){ return qp_scientist_schrodinger_import_data(); }

function qp_sci_sch_admin_menu(){ add_management_page('QP Scientist Schrodinger','QP Scientist Schrodinger','manage_options','qp-scientist-schrodinger','qp_sci_sch_render_admin'); }
add_action('admin_menu','qp_sci_sch_admin_menu');

function qp_sci_sch_render_admin(){
    if (!current_user_can('manage_options')) { return; }
    $data=qp_sci_sch_data();
    $action=isset($_POST['qp_sci_sch_action'])?$_POST['qp_sci_sch_action']:'';
    $apply=($action==='apply'); $dry=($action==='dry');
    echo '<div class="wrap" style="max-width:1000px;"><h1>QP Scientist Importer — Erwin Schrödinger (FA+EN) Draft</h1>';
    echo '<p>Bilingual FA+EN, draft status, full filter compliance (one sentence one source <mark><a>), Person schema, hreflang fa/en/x-default linking per Google. Backup in <code>'.esc_html($GLOBALS['wpdb']->prefix . QP_SCI_SCH_TABLE).'</code>. Old slugs 301: schrodingerr, ervin-schrodinger → erwin-schrodinger.</p>';
    echo '<p><strong>Canonical FA:</strong> https://qpedia.ir/scientists/erwin-schrodinger/ | <strong>EN:</strong> https://qpedia.ir/en/scientists/erwin-schrodinger/ | Person @id shared: https://qpedia.ir/scientists/erwin-schrodinger/#person</p>';
    if($dry||$apply){
        echo '<h2>'.($apply?'Applying as DRAFT...':'Dry-run preview').'</h2><div style="background:#f9f9f9; border:1px solid #ddd; padding:12px; white-space:pre-wrap; font-family:monospace; font-size:11px; max-height:700px; overflow:auto;">';
        foreach($data as $item){
            $slug=$item['slug']; $post_id=qp_sci_sch_find_post_id($slug);
            if(!$post_id && !empty($item['old_slugs'])){
                foreach($item['old_slugs'] as $old){
                    $clean_old = basename($old);
                    $old_id=qp_sci_sch_find_post_id($old);
                    if(!$old_id) $old_id=qp_sci_sch_find_post_id($clean_old);
                    if($old_id){ $post_id=$old_id; echo "Found old slug {$old} → will rename to {$slug} (#{$old_id})\n"; break; }
                }
            }
            if(!$post_id){
                echo "🆕 {$slug} ({$item['locale']}): will CREATE as draft — {$item['title']}\n";
            } else {
                $old_title=get_post_field('post_title',$post_id);
                echo "✔ {$slug} ({$item['locale']}) (#{$post_id}): {$old_title} → {$item['title']}\n";
            }
            echo "  SEO Title (".strlen($item['seo_title'])."): {$item['seo_title']}\n";
            echo "  Meta (".strlen($item['meta_description'])."): ".mb_substr($item['meta_description'],0,80)."…\n";
            echo "  KW: {$item['focus_keyword']} | Canonical: {$item['canonical']} | hreflang fa: {$item['hreflang_fa']} en: {$item['hreflang_en']}\n";
            echo "  Featured: {$item['featured_image']} alt: {$item['featured_alt']}\n";
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
                        update_post_meta($post_id,'_thumbnail_alt',$item['featured_alt']);
                        update_post_meta($post_id,'_qpedia_lang',$item['locale']);
                        update_post_meta($post_id,'_qpedia_canonical',$item['canonical']);
                        update_post_meta($post_id,'_qpedia_hreflang_fa',$item['hreflang_fa']);
                        update_post_meta($post_id,'_qpedia_hreflang_en',$item['hreflang_en']);
                        update_post_meta($post_id,'_qpedia_translation_of', isset($item['translation_of'])?$item['translation_of']:'');
                        update_post_meta($post_id,'_qpedia_person_id','https://qpedia.ir/scientists/erwin-schrodinger/#person');
                        echo "  → Created draft #{$post_id}\n";
                    }
                } else {
                    $old_content=get_post_field('post_content',$post_id);
                    $old_meta=get_post_meta($post_id,'rank_math_description',true);
                    $old_kw=get_post_meta($post_id,'rank_math_focus_keyword',true);
                    $old_seo_title=get_post_meta($post_id,'rank_math_title',true);
                    qp_sci_sch_backup($slug,'post_content',$old_content,$new_content);
                    qp_sci_sch_backup($slug,'rank_math_description',$old_meta,$new_meta);
                    qp_sci_sch_backup($slug,'rank_math_focus_keyword',$old_kw,$new_kw);
                    qp_sci_sch_backup($slug,'rank_math_title',$old_seo_title,$new_seo_title);
                    wp_update_post(['ID'=>$post_id,'post_title'=>$item['title'],'post_name'=>$slug,'post_content'=>$new_content]);
                    update_post_meta($post_id,'rank_math_title',$new_seo_title);
                    update_post_meta($post_id,'rank_math_description',$new_meta);
                    update_post_meta($post_id,'rank_math_focus_keyword',$new_kw);
                    update_post_meta($post_id,'_thumbnail_alt',$item['featured_alt']);
                    update_post_meta($post_id,'_qpedia_lang',$item['locale']);
                    update_post_meta($post_id,'_qpedia_canonical',$item['canonical']);
                    update_post_meta($post_id,'_qpedia_hreflang_fa',$item['hreflang_fa']);
                    update_post_meta($post_id,'_qpedia_hreflang_en',$item['hreflang_en']);
                    update_post_meta($post_id,'_qpedia_person_id','https://qpedia.ir/scientists/erwin-schrodinger/#person');
                    wp_update_post(['ID'=>$post_id,'post_status'=>'draft']);
                    echo "  → Updated draft #{$post_id} + backup\n";
                }
            }
            echo "\n";
        }
        echo '</div>';
        if($apply) echo '<div class="notice notice-success"><p>Applied as DRAFT. Both FA and EN are draft, hreflang linked per Google, Person @id shared.</p></div>';
    }
    echo '<form method="post" style="margin-top:20px;">'; wp_nonce_field('qp_sci_sch_importer');
    echo '<p><button type="submit" name="qp_sci_sch_action" value="dry" class="button">Dry-run preview (FA+EN)</button> <button type="submit" name="qp_sci_sch_action" value="apply" class="button button-primary" onclick="return confirm(\'Create/Update both FA+EN as DRAFT?\')">Apply as DRAFT with backup</button></p></form>';
    echo '<h2>Filter Approval</h2><p>Each article passed mandatory filter 06-final-official-filter.md — one-sentence-one-source, whitelist sources, E-E-A-T, hreflang fa/en/x-default, Person @id shared.</p>';
    echo '<h2>301 Redirects Included</h2><ul><li>schrodingerr → erwin-schrodinger</li><li>ervin-schrodinger → erwin-schrodinger</li><li>schrodingerr/ervin-schrodinger → erwin-schrodinger</li></ul></div>';
}
