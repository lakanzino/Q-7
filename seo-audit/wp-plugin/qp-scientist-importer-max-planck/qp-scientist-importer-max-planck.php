<?php
/**
 * Plugin Name: QP Scientist Importer — Max Planck (FA+EN) Publish v6.2 NoImg — FIX max-plank typo
 * Description: FIX for https://qpedia.ir/scientists/max-plank/ typo 404 — deletes/trashes old max-plank post, creates max-planck FA + max-planck-en EN as PUBLISH (not draft), no inline images, gray sources, FAQ fixed, Person @id shared, 301 max-plank→max-planck built-in.
 * Version: 6.2.0
 * Author: Arena Agent for qpedia.ir
 */
if (!defined('ABSPATH')) exit;
require_once plugin_dir_path(__FILE__) . 'qp-import-data-max-planck.php';
define('QP_SCI_MAX_PLANCK_TABLE', 'qp_scientist_max_planck_backup');

function qp_sci_max_planck_maybe_install(){ global $wpdb; $table=$wpdb->prefix . QP_SCI_MAX_PLANCK_TABLE; if($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s',$table))===$table) return; require_once ABSPATH.'wp-admin/includes/upgrade.php'; $charset=$wpdb->get_charset_collate(); $sql="CREATE TABLE {$table} (id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, slug VARCHAR(100) NOT NULL, field_name VARCHAR(100) NOT NULL, old_value LONGTEXT, new_value LONGTEXT, created DATETIME NOT NULL, PRIMARY KEY (id), KEY slug (slug)) {$charset};"; dbDelta($sql); }
register_activation_hook(__FILE__, 'qp_sci_max_planck_maybe_install');

function qp_sci_max_planck_find_post_id($slug){
    $posts=get_posts(['name'=>$slug,'post_type'=>'quantum_scientist','post_status'=>['publish','draft','pending','private','trash'],'posts_per_page'=>1,'fields'=>'ids']);
    if($posts) return (int)$posts[0];
    $posts=get_posts(['name'=>$slug,'post_status'=>['publish','draft','pending','private','trash'],'posts_per_page'=>1,'fields'=>'ids']);
    return $posts?(int)$posts[0]:0;
}
function qp_sci_max_planck_backup($slug,$field,$old,$new){ global $wpdb; $wpdb->insert($wpdb->prefix . QP_SCI_MAX_PLANCK_TABLE, ['slug'=>$slug,'field_name'=>$field,'old_value'=>$old,'new_value'=>$new,'created'=>current_time('mysql')], ['%s','%s','%s','%s','%s']); }
function qp_sci_max_planck_data(){ return qp_scientist_max_planck_import_data(); }

// 301 built-in so max-plank typo never 404 again even without separate plugin
function qp_sci_max_planck_301(){
    if(is_admin()) return;
    $request=$_SERVER['REQUEST_URI']??'';
    $path=parse_url($request, PHP_URL_PATH);
    if(!$path) return;
    $low=strtolower($path);
    // direct typo
    if($low==='/scientists/max-plank/' || $low==='/scientists/max-plank' || $low==='/en/scientists/max-plank/' || $low==='/en/scientists/max-plank'){
        wp_redirect(home_url(strpos($low,'/en/')===0 ? '/en/scientists/max-planck/' : '/scientists/max-planck/'), 301); exit;
    }
    if(strpos($low,'/scientists/max-plank/')===0 || $low==='/scientists/max-plank'){
        wp_redirect(home_url('/scientists/max-planck/'), 301); exit;
    }
    // albert-einstein old
    if(strpos($low,'/scientists/albert-einstein-2/')===0 || $low==='/scientists/albert-einstein-2' || $low==='/scientists/albert-einstein-2/'){
        wp_redirect(home_url('/scientists/albert-einstein/'), 301); exit;
    }
    if(strpos($low,'/scientists/schrodingerr/')===0){
        $rest=substr($path, strlen('/scientists/schrodingerr/')); $rest=ltrim($rest,'/');
        $rest=str_replace(['ervin-schrodinger','arnold-sommer-feld','herman-weyl','john-bell-3'], ['erwin-schrodinger','arnold-sommerfeld','hermann-weyl','john-bell'], strtolower($rest));
        wp_redirect(home_url($rest?'/scientists/'.$rest:'/scientists/erwin-schrodinger/'), 301); exit;
    }
}
add_action('template_redirect','qp_sci_max_planck_301',1);

function qp_sci_max_planck_force_slug($data,$postarr){
    if($data['post_type']==='quantum_scientist'){
        if($data['post_name']==='max-plank') $data['post_name']='max-planck';
        if($data['post_name']==='albert-einstein-2') $data['post_name']='albert-einstein';
        if($data['post_name']==='schrodingerr') $data['post_name']='erwin-schrodinger';
        if($data['post_name']==='ervin-schrodinger') $data['post_name']='erwin-schrodinger';
    }
    return $data;
}
add_filter('wp_insert_post_data','qp_sci_max_planck_force_slug',10,2);

function qp_sci_max_planck_admin_menu(){ add_management_page('QP Scientist Max Planck FIX','QP Scientist Max Planck FIX','manage_options','qp-scientist-max-planck','qp_sci_max_planck_render_admin'); }
add_action('admin_menu','qp_sci_max_planck_admin_menu');

function qp_sci_max_planck_render_admin(){
    if(!current_user_can('manage_options')) return;
    $data=qp_sci_max_planck_data();
    $action=isset($_POST['qp_sci_max_planck_action'])?$_POST['qp_sci_max_planck_action']:'';
    $apply=($action==='apply'); $dry=($action==='dry'); $cleanup=($action==='cleanup');
    echo '<div class="wrap" style="max-width:1100px;"><h1>QP Scientist Importer — Max Planck v6.2 FIX max-plank typo → PUBLISH</h1>';
    echo '<div style="background:#fef2f2;border:2px solid #f87171;padding:14px 18px;border-radius:10px;margin:12px 0;"><strong style="color:#dc2626;">⚠️ فیکس لینک خراب شما:</strong><br>لینک <code>https://qpedia.ir/scientists/max-plank/</code> غلط املایی است (plank بدون c). صحیح <code>https://qpedia.ir/scientists/max-planck/</code> با c است.<br>این افزونه: 1) پست قدیمی با اسلاگ <code>max-plank</code> را Trash می‌کند، 2) هر دو FA+EN را <strong>Publish</strong> می‌کند (نه Draft)، 3) خودش 301 دارد، پس حتی بدون افزونه 301 هم ریدایرکت می‌کند.</div>';
    echo '<p>Canonical FA: https://qpedia.ir/scientists/max-planck/ | EN: https://qpedia.ir/en/scientists/max-planck/ | Person @id: https://qpedia.ir/scientists/max-planck/#person</p>';

    if($cleanup || $dry || $apply){
        echo '<h2>Step 1: Cleanup old typo max-plank</h2><div style="background:#f9f9f9;border:1px solid #ddd;padding:12px;white-space:pre-wrap;font-family:monospace;font-size:11px;max-height:300px;overflow:auto;">';
        $old_ids=[];
        foreach(['max-plank','max-plank-en'] as $old_slug){
            $oid=qp_sci_max_planck_find_post_id($old_slug);
            if($oid){
                $old_ids[]=$oid;
                $st=get_post_status($oid);
                echo "Found OLD typo {$old_slug} (#{$oid} status={$st}) — will trash\n";
                if($apply || $cleanup){
                    wp_trash_post($oid);
                    echo " → Trashed #{$oid}\n";
                }
            } else {
                echo "No post with slug {$old_slug} (good)\n";
            }
        }
        echo '</div>';

        echo '<h2>'.($apply?'Applying as PUBLISH...':'Dry-run preview — will PUBLISH').'</h2><div style="background:#f0fdf4;border:1px solid #86efac;padding:12px;white-space:pre-wrap;font-family:monospace;font-size:11px;max-height:700px;overflow:auto;">';
        foreach($data as $idx=>$item){
            $num=$idx+1; $slug=$item['slug']; $post_id=qp_sci_max_planck_find_post_id($slug);
            if(!$post_id && !empty($item['old_slugs'])){
                foreach($item['old_slugs'] as $old){
                    $old_id=qp_sci_max_planck_find_post_id($old);
                    if($old_id){ $post_id=$old_id; echo "Found old {$old} → rename to {$slug} (#{$old_id})\n"; break; }
                }
            }
            if(!$post_id){
                echo "{$num}. 🆕 {$slug} ({$item['locale']}): CREATE as PUBLISH — {$item['title']}\n";
            } else {
                $old_title=get_post_field('post_title',$post_id);
                $old_status=get_post_status($post_id);
                echo "{$num}. ✔ {$slug} ({$item['locale']}) (#{$post_id} {$old_status}): {$old_title} → {$item['title']}\n";
            }
            echo "   Canonical: {$item['canonical']}\n";
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
                        update_post_meta($post_id,'_qpedia_person_id','https://qpedia.ir/scientists/max-planck/#person');
                        echo "   → Created PUBLISH #{$post_id}\n";
                    }
                } else {
                    $old_content=get_post_field('post_content',$post_id);
                    qp_sci_max_planck_backup($slug,'post_content',$old_content,$new_content);
                    wp_update_post(['ID'=>$post_id,'post_title'=>$item['title'],'post_name'=>$slug,'post_content'=>$new_content]);
                    update_post_meta($post_id,'rank_math_title',$new_seo_title);
                    update_post_meta($post_id,'rank_math_description',$new_meta);
                    update_post_meta($post_id,'rank_math_focus_keyword',$new_kw);
                    update_post_meta($post_id,'_qpedia_lang',$item['locale']);
                    update_post_meta($post_id,'_qpedia_canonical',$item['canonical']);
                    update_post_meta($post_id,'_qpedia_hreflang_fa',$item['hreflang_fa']);
                    update_post_meta($post_id,'_qpedia_hreflang_en',$item['hreflang_en']);
                    update_post_meta($post_id,'_qpedia_person_id','https://qpedia.ir/scientists/max-planck/#person');
                    wp_update_post(['ID'=>$post_id,'post_status'=>'publish']);
                    echo "   → Updated PUBLISH #{$post_id}\n";
                }
            }
            echo "\n";
        }
        echo '</div>';
        if($apply) echo '<div class="notice notice-success"><p>✅ FIX DONE: max-plank trashed, max-planck FA + max-planck-en EN published. حالا <a href="https://qpedia.ir/scientists/max-planck/" target="_blank">https://qpedia.ir/scientists/max-planck/</a> باید باز شود و <a href="https://qpedia.ir/scientists/max-plank/" target="_blank">/max-plank/</a> 301 شود. کش LiteSpeed را پاک کنید.</p></div>';
    }
    echo '<form method="post" style="margin-top:20px;">'; wp_nonce_field('qp_sci_max_planck_importer');
    echo '<p><button type="submit" name="qp_sci_max_planck_action" value="cleanup" class="button">1) Cleanup only — Trash max-plank</button> <button type="submit" name="qp_sci_max_planck_action" value="dry" class="button">2) Dry-run preview PUBLISH</button> <button type="submit" name="qp_sci_max_planck_action" value="apply" class="button button-primary" style="background:#dc2626;border-color:#dc2626;" onclick="return confirm('Publish both + trash max-plank?')">3) FIX NOW — Trash max-plank + Publish FA+EN</button></p></form>';
    echo '</div>';
}
