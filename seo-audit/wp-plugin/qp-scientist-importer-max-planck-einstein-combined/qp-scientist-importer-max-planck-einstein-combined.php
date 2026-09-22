<?php
/**
 * Plugin Name: QP Scientist Importer — Max Planck + Einstein COMBINED Publish v6.2
 * Description: COMBINED FIX — Publishes 4 posts at once: max-planck FA + max-planck-en EN + albert-einstein FA + albert-einstein-en EN — all PUBLISH, no img, gray sources, 10 valid links each, trashes old typos max-plank / albert-einstein-2 / schrodingerr, built-in 301 max-plank→max-planck.
 * Version: 6.2.0
 * Author: Arena Agent for qpedia.ir
 */
if (!defined('ABSPATH')) exit;
require_once plugin_dir_path(__FILE__) . 'qp-import-data-combined.php';
define('QP_SCI_COMBINED_TABLE', 'qp_scientist_combined_backup');

function qp_sci_combined_maybe_install(){ global $wpdb; $table=$wpdb->prefix . QP_SCI_COMBINED_TABLE; if($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s',$table))===$table) return; require_once ABSPATH.'wp-admin/includes/upgrade.php'; $charset=$wpdb->get_charset_collate(); $sql="CREATE TABLE {$table} (id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, slug VARCHAR(100) NOT NULL, field_name VARCHAR(100) NOT NULL, old_value LONGTEXT, new_value LONGTEXT, created DATETIME NOT NULL, PRIMARY KEY (id), KEY slug (slug)) {$charset};"; dbDelta($sql); }
register_activation_hook(__FILE__, 'qp_sci_combined_maybe_install');

function qp_sci_combined_find_post_id($slug){
    $posts=get_posts(['name'=>$slug,'post_type'=>'quantum_scientist','post_status'=>['publish','draft','pending','private','trash'],'posts_per_page'=>1,'fields'=>'ids']);
    if($posts) return (int)$posts[0];
    $posts=get_posts(['name'=>$slug,'post_status'=>['publish','draft','pending','private','trash'],'posts_per_page'=>1,'fields'=>'ids']);
    return $posts?(int)$posts[0]:0;
}
function qp_sci_combined_backup($slug,$field,$old,$new){ global $wpdb; $wpdb->insert($wpdb->prefix . QP_SCI_COMBINED_TABLE, ['slug'=>$slug,'field_name'=>$field,'old_value'=>$old,'new_value'=>$new,'created'=>current_time('mysql')], ['%s','%s','%s','%s','%s']); }
function qp_sci_combined_data(){ return qp_scientist_combined_import_data(); }

function qp_sci_combined_301(){
    if(is_admin()) return;
    $request=$_SERVER['REQUEST_URI']??'';
    $path=parse_url($request, PHP_URL_PATH);
    if(!$path) return;
    $low=strtolower($path);
    if($low==='/scientists/max-plank/' || $low==='/scientists/max-plank' || $low==='/en/scientists/max-plank/' || $low==='/en/scientists/max-plank' || strpos($low,'/scientists/max-plank/')===0 || strpos($low,'/en/scientists/max-plank/')===0){
        wp_redirect(home_url(strpos($low,'/en/')===0?'/en/scientists/max-planck/':'/scientists/max-planck/'),301); exit;
    }
    if(strpos($low,'/scientists/albert-einstein-2/')===0 || $low==='/scientists/albert-einstein-2'){
        wp_redirect(home_url('/scientists/albert-einstein/'),301); exit;
    }
    if(strpos($low,'/scientists/schrodingerr/')===0){
        $rest=substr($path, strlen('/scientists/schrodingerr/')); $rest=ltrim($rest,'/');
        $rest=str_replace(['ervin-schrodinger','arnold-sommer-feld','herman-weyl','john-bell-3'], ['erwin-schrodinger','arnold-sommerfeld','hermann-weyl','john-bell'], strtolower($rest));
        wp_redirect(home_url($rest?'/scientists/'.$rest:'/scientists/erwin-schrodinger/'),301); exit;
    }
}
add_action('template_redirect','qp_sci_combined_301',1);

function qp_sci_combined_force_slug($data,$postarr){
    if($data['post_type']==='quantum_scientist'){
        if($data['post_name']==='max-plank') $data['post_name']='max-planck';
        if($data['post_name']==='albert-einstein-2') $data['post_name']='albert-einstein';
        if($data['post_name']==='schrodingerr') $data['post_name']='erwin-schrodinger';
        if($data['post_name']==='ervin-schrodinger') $data['post_name']='erwin-schrodinger';
    }
    return $data;
}
add_filter('wp_insert_post_data','qp_sci_combined_force_slug',10,2);

function qp_sci_combined_admin_menu(){ add_management_page('QP Scientist COMBINED','QP Scientist COMBINED','manage_options','qp-scientist-combined','qp_sci_combined_render_admin'); }
add_action('admin_menu','qp_sci_combined_admin_menu');

function qp_sci_combined_render_admin(){
    if(!current_user_can('manage_options')) return;
    $data=qp_sci_combined_data();
    $action=isset($_POST['qp_sci_combined_action'])?$_POST['qp_sci_combined_action']:'';
    $apply=($action==='apply'); $dry=($action==='dry'); $cleanup=($action==='cleanup');
    echo '<div class="wrap" style="max-width:1150px;"><h1>QP Scientist Importer — COMBINED Max Planck + Einstein v6.2 PUBLISH</h1>';
    echo '<div style="background:#fef2f2;border:2px solid #f87171;padding:14px 18px;border-radius:10px;margin:12px 0;"><strong style="color:#dc2626;">فیکس 2 تا با هم:</strong><br>• <code>max-plank</code> غلط → <code>max-planck</code> صحیح<br>• اینشتین جدید 10 لینک سالم<br>این افزونه هر 4 تا را یه دفعه Publish می‌کند + پست‌های قدیمی غلط را Trash می‌کند + خودش 301 دارد.</div>';
    echo '<p>FA: https://qpedia.ir/scientists/max-planck/ | https://qpedia.ir/scientists/albert-einstein/<br>EN: https://qpedia.ir/en/scientists/max-planck/ | https://qpedia.ir/en/scientists/albert-einstein/</p>';

    if($cleanup || $dry || $apply){
        echo '<h2>Step 1: Cleanup old typos</h2><div style="background:#f9f9f9;border:1px solid #ddd;padding:12px;white-space:pre-wrap;font-family:monospace;font-size:11px;max-height:300px;overflow:auto;">';
        foreach(['max-plank','max-plank-en','albert-einstein-2','albert-einstein-3','schrodingerr','ervin-schrodinger'] as $old_slug){
            $oid=qp_sci_combined_find_post_id($old_slug);
            if($oid){
                $st=get_post_status($oid);
                echo "Found OLD {$old_slug} (#{$oid} {$st}) — will trash\n";
                if($apply || $cleanup){ wp_trash_post($oid); echo " → Trashed\n"; }
            } else {
                echo "No {$old_slug} (good)\n";
            }
        }
        echo '</div>';
        echo '<h2>'.($apply?'Publishing 4 posts...':'Dry-run — 4 posts will be PUBLISHED').'</h2><div style="background:#f0fdf4;border:1px solid #86efac;padding:12px;white-space:pre-wrap;font-family:monospace;font-size:11px;max-height:800px;overflow:auto;">';
        foreach($data as $idx=>$item){
            $num=$idx+1; $slug=$item['slug']; $post_id=qp_sci_combined_find_post_id($slug);
            if(!$post_id && !empty($item['old_slugs'])){
                foreach($item['old_slugs'] as $old){
                    $old_id=qp_sci_combined_find_post_id($old);
                    if($old_id){ $post_id=$old_id; echo "Found old {$old} → rename to {$slug} (#{$old_id})\n"; break; }
                }
            }
            if(!$post_id){
                echo "{$num}. 🆕 {$slug} ({$item['locale']}): CREATE PUBLISH — {$item['title']}\n";
            } else {
                $old_title=get_post_field('post_title',$post_id);
                $old_status=get_post_status($post_id);
                echo "{$num}. ✔ {$slug} ({$item['locale']}) (#{$post_id} {$old_status}): {$old_title} → {$item['title']}\n";
            }
            echo "   Canonical: {$item['canonical']} | len ".strlen($item['content_html'])."\n";
            if($apply){
                $new_content=$item['content_html'];
                $new_meta=$item['meta_description'];
                $new_kw=$item['focus_keyword'];
                $new_seo_title=$item['seo_title'];
                if(!$post_id){
                    $post_id=wp_insert_post(['post_title'=>$item['title'],'post_name'=>$slug,'post_content'=>$new_content,'post_type'=>$item['post_type'],'post_status'=>'publish']);
                    if($post_id){
                        update_post_meta($post_id,'rank_math_title',$new_seo_title);
                        update_post_meta($post_id,'rank_math_description',$new_meta);
                        update_post_meta($post_id,'rank_math_focus_keyword',$new_kw);
                        update_post_meta($post_id,'_qpedia_lang',$item['locale']);
                        update_post_meta($post_id,'_qpedia_canonical',$item['canonical']);
                        update_post_meta($post_id,'_qpedia_hreflang_fa',$item['hreflang_fa']);
                        update_post_meta($post_id,'_qpedia_hreflang_en',$item['hreflang_en']);
                        update_post_meta($post_id,'_qpedia_translation_of',$item['translation_of']??'');
                        update_post_meta($post_id,'_qpedia_person_id','https://qpedia.ir/scientists/'.$item['slug'].'/#person');
                        echo "   → Created PUBLISH #{$post_id}\n";
                    }
                } else {
                    $old_content=get_post_field('post_content',$post_id);
                    qp_sci_combined_backup($slug,'post_content',$old_content,$new_content);
                    wp_update_post(['ID'=>$post_id,'post_title'=>$item['title'],'post_name'=>$slug,'post_content'=>$new_content]);
                    update_post_meta($post_id,'rank_math_title',$new_seo_title);
                    update_post_meta($post_id,'rank_math_description',$new_meta);
                    update_post_meta($post_id,'rank_math_focus_keyword',$new_kw);
                    update_post_meta($post_id,'_qpedia_lang',$item['locale']);
                    update_post_meta($post_id,'_qpedia_canonical',$item['canonical']);
                    update_post_meta($post_id,'_qpedia_hreflang_fa',$item['hreflang_fa']);
                    update_post_meta($post_id,'_qpedia_hreflang_en',$item['hreflang_en']);
                    update_post_meta($post_id,'_qpedia_person_id','https://qpedia.ir/scientists/'.$item['slug'].'/#person');
                    wp_update_post(['ID'=>$post_id,'post_status'=>'publish']);
                    echo "   → Updated PUBLISH #{$post_id}\n";
                }
            }
            echo "\n";
        }
        echo '</div>';
        if($apply) echo '<div class="notice notice-success"><p>✅ هر 4 تا Publish شد: max-planck FA+EN + einstein FA+EN. حالا لینک فارسی https://qpedia.ir/scientists/max-planck/ و https://qpedia.ir/scientists/albert-einstein/ باز می‌شود و /max-plank/ 301 می‌شود. کش را پاک کنید.</p></div>';
    }
    echo '<form method="post" style="margin-top:20px;">'; wp_nonce_field('qp_sci_combined_importer');
    echo '<p><button type="submit" name="qp_sci_combined_action" value="cleanup" class="button">1) Cleanup old typos</button> <button type="submit" name="qp_sci_combined_action" value="dry" class="button">2) Dry-run 4 posts</button> <button type="submit" name="qp_sci_combined_action" value="apply" class="button button-primary" style="background:#dc2626;border-color:#dc2626;" onclick="return confirm('Publish 4 posts + trash typos?')">3) FIX NOW — Publish 4 + Trash typos</button></p></form>';
    echo '</div>';
}
