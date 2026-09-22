<?php
/**
 * Plugin Name: QP Scientist Importer — Max Planck FA+EN Publish v6.2.2 Like Einstein
 * Description: FIX v6.2.2 — Publishes max-planck FA + max-planck-en EN as PUBLISH — uses same method as working Einstein v6 (small JSON, no file_get_contents). Trashes old max-plank typo, built-in 301 max-plank→max-planck, no img, gray sources.
 * Version: 6.2.2
 * Author: Arena Agent for qpedia.ir
 */
if (!defined('ABSPATH')) exit;
require_once plugin_dir_path(__FILE__) . 'qp-import-data-max-planck.php';
define('QP_SCI_MAX_V622_TABLE', 'qp_scientist_max_planck_backup');

function qp_sci_max_v622_maybe_install(){ global $wpdb; $table=$wpdb->prefix . QP_SCI_MAX_V622_TABLE; if($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s',$table))===$table) return; require_once ABSPATH.'wp-admin/includes/upgrade.php'; $charset=$wpdb->get_charset_collate(); $sql="CREATE TABLE {$table} (id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, slug VARCHAR(100) NOT NULL, field_name VARCHAR(100) NOT NULL, old_value LONGTEXT, new_value LONGTEXT, created DATETIME NOT NULL, PRIMARY KEY (id), KEY slug (slug)) {$charset};"; dbDelta($sql); }
register_activation_hook(__FILE__, 'qp_sci_max_v622_maybe_install');

function qp_sci_max_v622_find($slug){
    $posts=get_posts(['name'=>$slug,'post_type'=>'quantum_scientist','post_status'=>['publish','draft','pending','private','trash'],'posts_per_page'=>1,'fields'=>'ids']);
    if($posts) return (int)$posts[0];
    $posts=get_posts(['name'=>$slug,'post_status'=>['publish','draft','pending','private','trash'],'posts_per_page'=>1,'fields'=>'ids']);
    return $posts?(int)$posts[0]:0;
}
function qp_sci_max_v622_backup($slug,$field,$old,$new){ global $wpdb; $wpdb->insert($wpdb->prefix . QP_SCI_MAX_V622_TABLE, ['slug'=>$slug,'field_name'=>$field,'old_value'=>$old,'new_value'=>$new,'created'=>current_time('mysql')], ['%s','%s','%s','%s','%s']); }
function qp_sci_max_v622_data(){ return qp_scientist_max_planck_import_data(); }

function qp_sci_max_v622_301(){
    if(is_admin()) return;
    $r=$_SERVER['REQUEST_URI']??''; $p=parse_url($r, PHP_URL_PATH); if(!$p) return; $low=strtolower($p);
    if($low==='/scientists/max-plank/' || $low==='/scientists/max-plank' || $low==='/en/scientists/max-plank/' || $low==='/en/scientists/max-plank' || strpos($low,'/scientists/max-plank/')===0 || strpos($low,'/en/scientists/max-plank/')===0){
        wp_redirect(home_url(strpos($low,'/en/')===0?'/en/scientists/max-planck/':'/scientists/max-planck/'),301); exit;
    }
}
add_action('template_redirect','qp_sci_max_v622_301',1);

function qp_sci_max_v622_force_slug($data,$postarr){
    if($data['post_type']==='quantum_scientist' && $data['post_name']==='max-plank') $data['post_name']='max-planck';
    return $data;
}
add_filter('wp_insert_post_data','qp_sci_max_v622_force_slug',10,2);

function qp_sci_max_v622_admin_menu(){ add_management_page('QP Scientist Max Planck v6.2.2','QP Scientist Max Planck v6.2.2','manage_options','qp-scientist-max-planck-v622','qp_sci_max_v622_render_admin'); }
add_action('admin_menu','qp_sci_max_v622_admin_menu');

function qp_sci_max_v622_render_admin(){
    if(!current_user_can('manage_options')) return;
    $data=qp_sci_max_v622_data();
    $action=isset($_POST['qp_sci_max_v622_action'])?$_POST['qp_sci_max_v622_action']:'';
    $apply=($action==='apply'); $dry=($action==='dry'); $cleanup=($action==='cleanup');
    echo '<div class="wrap" style="max-width:1100px;"><h1>QP Scientist Max Planck v6.2.2 — PUBLISH Like Einstein v6</h1>';
    echo '<div style="background:#e0f2fe;border:1px solid #7dd3fc;padding:12px 16px;border-radius:8px;margin:12px 0;">این نسخه دقیقا مثل اینشتین v6 که نصب شد ساخته شده — JSON کوچک، بدون file_get_contents. باید نصب شود.<br>1) max-plank را Trash می‌کند، 2) max-planck FA+EN را Publish می‌کند، 3) 301 دارد.</div>';
    if($cleanup||$dry||$apply){
        echo '<h2>Cleanup max-plank</h2><div style="background:#f9f9f9;border:1px solid #ddd;padding:12px;white-space:pre-wrap;font-family:monospace;font-size:11px;max-height:300px;overflow:auto;">';
        foreach(['max-plank','max-plank-en'] as $old){
            $oid=qp_sci_max_v622_find($old);
            if($oid){ echo "Found {$old} (#{$oid} ".get_post_status($oid).") trash\n"; if($apply||$cleanup){ wp_trash_post($oid); echo " → Trashed\n"; } } else { echo "No {$old} (good)\n"; }
        }
        echo '</div>';
        echo '<h2>'.($apply?'Publishing...':'Dry-run').'</h2><div style="background:#f0fdf4;border:1px solid #86efac;padding:12px;white-space:pre-wrap;font-family:monospace;font-size:11px;max-height:700px;overflow:auto;">';
        foreach($data as $idx=>$item){
            $slug=$item['slug']; $pid=qp_sci_max_v622_find($slug);
            if(!$pid && !empty($item['old_slugs'])){
                foreach($item['old_slugs'] as $old){ $oid=qp_sci_max_v622_find($old); if($oid){ $pid=$oid; echo "Found old {$old} → rename to {$slug} (#{$oid})\n"; break; } }
            }
            if(!$pid) echo ($idx+1).". 🆕 {$slug} CREATE PUBLISH — {$item['title']} len ".strlen($item['content_html'])."\n";
            else echo ($idx+1).". ✔ {$slug} UPDATE #{$pid} ".get_post_status($pid)." len ".strlen($item['content_html'])."\n";
            if($apply){
                $new_content=$item['content_html'];
                $new_meta=$item['meta_description'];
                $new_kw=$item['focus_keyword'];
                $new_seo_title=$item['seo_title'];
                if(!$pid){
                    $pid=wp_insert_post(['post_title'=>$item['title'],'post_name'=>$slug,'post_content'=>$new_content,'post_type'=>$item['post_type'],'post_status'=>'publish']);
                    if($pid){
                        update_post_meta($pid,'rank_math_title',$new_seo_title);
                        update_post_meta($pid,'rank_math_description',$new_meta);
                        update_post_meta($pid,'rank_math_focus_keyword',$new_kw);
                        update_post_meta($pid,'_qpedia_lang',$item['locale']);
                        update_post_meta($pid,'_qpedia_canonical',$item['canonical']);
                        update_post_meta($pid,'_qpedia_hreflang_fa',$item['hreflang_fa']);
                        update_post_meta($pid,'_qpedia_hreflang_en',$item['hreflang_en']);
                        update_post_meta($pid,'_qpedia_translation_of',$item['translation_of']??'');
                        update_post_meta($pid,'_qpedia_person_id','https://qpedia.ir/scientists/max-planck/#person');
                        echo " → Created PUBLISH #{$pid}\n";
                    }
                } else {
                    $old=get_post_field('post_content',$pid);
                    qp_sci_max_v622_backup($slug,'post_content',$old,$new_content);
                    wp_update_post(['ID'=>$pid,'post_title'=>$item['title'],'post_name'=>$slug,'post_content'=>$new_content]);
                    update_post_meta($pid,'rank_math_title',$new_seo_title);
                    update_post_meta($pid,'rank_math_description',$new_meta);
                    update_post_meta($pid,'rank_math_focus_keyword',$new_kw);
                    update_post_meta($pid,'_qpedia_lang',$item['locale']);
                    update_post_meta($pid,'_qpedia_canonical',$item['canonical']);
                    update_post_meta($pid,'_qpedia_hreflang_fa',$item['hreflang_fa']);
                    update_post_meta($pid,'_qpedia_hreflang_en',$item['hreflang_en']);
                    wp_update_post(['ID'=>$pid,'post_status'=>'publish']);
                    echo " → Updated PUBLISH #{$pid}\n";
                }
            }
        }
        echo '</div>';
        if($apply) echo '<div class="notice notice-success"><p>✅ max-planck FA+EN Publish شد.</p></div>';
    }
    echo '<form method="post" style="margin-top:20px;">'; wp_nonce_field('qp_sci_max_v622');
    echo '<p><button type="submit" name="qp_sci_max_v622_action" value="cleanup" class="button">1) Cleanup max-plank</button> <button type="submit" name="qp_sci_max_v622_action" value="dry" class="button">2) Dry-run</button> <button type="submit" name="qp_sci_max_v622_action" value="apply" class="button button-primary" style="background:#dc2626;border-color:#dc2626;" onclick="return confirm('Publish?')">3) FIX NOW — Publish FA+EN</button></p></form></div>';
}
