<?php
/**
 * Plugin Name: QP 100 Importer v9 — 20 articles 58-72 to 100 (combined)
 * Description: Imports 20 rewritten articles (10 from v8 + 10 new lowest 70-72) from low scores to 100 with backup and dry-run. Fixes v8 zip structure bug. English UI.
 * Version: 1.0.1
 * Author: Arena Agent for qpedia.ir
 * License: GPL-2.0+
 * Text Domain: qp100v9
 */

if (!defined('ABSPATH')) { exit; }
require_once plugin_dir_path(__FILE__) . 'qp-import-data-v9.php';
define('QP100V9_TABLE', 'qp100v9_backup');
function qp100v9_maybe_install() {
    global $wpdb;
    $table = $wpdb->prefix . QP100V9_TABLE;
    if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table)) === $table) { return; }
    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    $charset = $wpdb->get_charset_collate();
    $sql = "CREATE TABLE {$table} (id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, slug VARCHAR(100) NOT NULL, field_name VARCHAR(100) NOT NULL, old_value LONGTEXT, new_value LONGTEXT, created DATETIME NOT NULL, PRIMARY KEY (id), KEY slug (slug)) {$charset};";
    dbDelta($sql);
}
register_activation_hook(__FILE__, 'qp100v9_maybe_install');
function qp100v9_find_post_id($slug) {
    $posts = get_posts(['name'=>$slug,'post_type'=>'quantum_article','post_status'=>'publish','posts_per_page'=>1,'fields'=>'ids']);
    if ($posts) return (int)$posts[0];
    $posts = get_posts(['name'=>$slug,'post_status'=>'publish','posts_per_page'=>1,'fields'=>'ids']);
    return $posts ? (int)$posts[0] : 0;
}
function qp100v9_backup($slug,$field,$old,$new){
    global $wpdb;
    $wpdb->insert($wpdb->prefix . QP100V9_TABLE, ['slug'=>$slug,'field_name'=>$field,'old_value'=>$old,'new_value'=>$new,'created'=>current_time('mysql')], ['%s','%s','%s','%s','%s']);
}
function qp100v9_data(){ return qp100v9_import_data(); }
function qp100v9_admin_menu(){ add_management_page('QP 100 Importer v9','QP 100 Importer v9','manage_options','qp-100-importer-v9','qp100v9_render_admin'); }
add_action('admin_menu','qp100v9_admin_menu');
function qp100v9_render_admin(){
    if (!current_user_can('manage_options')) { return; }
    $data=qp100v9_data();
    $action=isset($_POST['qp100v9_action'])?$_POST['qp100v9_action']:'';
    $apply=($action==='apply'); $dry=($action==='dry');
    echo '<div class="wrap" style="max-width:950px;"><h1>QP 100 Importer v9 — 20 articles to 100</h1><p>Upgrades 20 low-scored articles to 100 (10 from v8 fix + 10 new 70-72). Backup in <code>'.esc_html($GLOBALS['wpdb']->prefix . QP100V9_TABLE).'</code>. Updates post_content, rank_math_title, rank_math_description, rank_math_focus_keyword. Fixes v8 zip structure bug.</p>';
    if($dry||$apply){
        echo '<h2>'.($apply?'Applying...':'Dry-run preview').'</h2><div style="background:#f9f9f9; border:1px solid #ddd; padding:12px; white-space:pre-wrap; font-family:monospace; font-size:12px; max-height:600px; overflow:auto;">';
        foreach($data as $item){
            $slug=$item['slug']; $post_id=qp100v9_find_post_id($slug);
            if(!$post_id){ echo "⚠️ {$slug}: not found\n"; continue; }
            $old_content=get_post_field('post_content',$post_id); $old_title=get_post_field('post_title',$post_id);
            $old_meta=get_post_meta($post_id,'rank_math_description',true); $old_kw=get_post_meta($post_id,'rank_math_focus_keyword',true); $old_seo_title=get_post_meta($post_id,'rank_math_title',true);
            $new_content=$item['content_html']; $new_meta=$item['meta_description']; $new_kw=$item['focus_keyword']; $new_seo_title=$item['seo_title'];
            echo "✔ {$slug} (#{$post_id}): {$old_title} → {$item['title']}\n";
            echo "  SEO Title (".strlen($new_seo_title)."): ".($old_seo_title?$old_seo_title:'(empty)')." → {$new_seo_title}\n";
            echo "  Meta (".strlen($new_meta)."): ".($old_meta?mb_substr($old_meta,0,60).'...':'(empty)')." → {$new_meta}\n";
            echo "  KW: ".($old_kw?$old_kw:'(empty)')." → {$new_kw}\n";
            echo "  Featured: {$item['featured_image']} alt: {$item['featured_alt']}\n";
            echo "  Inbound: ".implode(', ', array_map(fn($x)=>$x['from'].'('.$x['anchor'].')', $item['inbound_suggestions']))."\n";
            if($apply){
                qp100v9_backup($slug,'post_content',$old_content,$new_content);
                qp100v9_backup($slug,'rank_math_description',$old_meta,$new_meta);
                qp100v9_backup($slug,'rank_math_focus_keyword',$old_kw,$new_kw);
                qp100v9_backup($slug,'rank_math_title',$old_seo_title,$new_seo_title);
                wp_update_post(['ID'=>$post_id,'post_content'=>$new_content]);
                update_post_meta($post_id,'rank_math_description',$new_meta);
                update_post_meta($post_id,'rank_math_focus_keyword',$new_kw);
                update_post_meta($post_id,'rank_math_title',$new_seo_title);
                update_post_meta($post_id,'_qpedia_focus_keyphrase',$new_kw);
            }
            echo "\n";
        }
        echo '</div>'; if($apply) echo '<div class="notice notice-success"><p>Applied. Backup in '.esc_html($GLOBALS['wpdb']->prefix . QP100V9_TABLE).'.</p></div>';
    }
    echo '<form method="post" style="margin-top:20px;">'; wp_nonce_field('qp100v9_importer');
    echo '<p><button type="submit" name="qp100v9_action" value="dry" class="button">Dry-run preview (20)</button> <button type="submit" name="qp100v9_action" value="apply" class="button button-primary" onclick="return confirm(\'Are you sure? Backup will be taken.\')">Apply with backup</button></p></form>';
    echo '<h2>List 20 articles low→100</h2><ol>'; foreach($data as $item) echo '<li><code>'.$item['slug'].'</code> — '.$item['title'].' — old: '.$item['old_score'].' → 100</li>'; echo '</ol></div>';
}
