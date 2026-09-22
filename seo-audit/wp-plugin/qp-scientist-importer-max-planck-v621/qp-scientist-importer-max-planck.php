<?php
/**
 * Plugin Name: QP Scientist Importer — Max Planck FA+EN Publish v6.2.1 FIX Install
 * Description: FIX v6.2.1 — Publishes max-planck FA + max-planck-en EN as PUBLISH, trashes old max-plank typo, built-in 301 max-plank→max-planck, loads HTML from separate files (no big JSON) — same method as Einstein v6 which works.
 * Version: 6.2.1
 * Author: Arena Agent for qpedia.ir
 */
if (!defined('ABSPATH')) exit;
define('QP_SCI_MAX_V621_TABLE', 'qp_scientist_max_planck_backup');

function qp_sci_max_v621_maybe_install(){ global $wpdb; $table=$wpdb->prefix . QP_SCI_MAX_V621_TABLE; if($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s',$table))===$table) return; require_once ABSPATH.'wp-admin/includes/upgrade.php'; $charset=$wpdb->get_charset_collate(); $sql="CREATE TABLE {$table} (id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, slug VARCHAR(100) NOT NULL, field_name VARCHAR(100) NOT NULL, old_value LONGTEXT, new_value LONGTEXT, created DATETIME NOT NULL, PRIMARY KEY (id), KEY slug (slug)) {$charset};"; dbDelta($sql); }
register_activation_hook(__FILE__, 'qp_sci_max_v621_maybe_install');

function qp_sci_max_v621_find($slug){
    $posts=get_posts(['name'=>$slug,'post_type'=>'quantum_scientist','post_status'=>['publish','draft','pending','private','trash'],'posts_per_page'=>1,'fields'=>'ids']);
    if($posts) return (int)$posts[0];
    $posts=get_posts(['name'=>$slug,'post_status'=>['publish','draft','pending','private','trash'],'posts_per_page'=>1,'fields'=>'ids']);
    return $posts?(int)$posts[0]:0;
}
function qp_sci_max_v621_backup($slug,$field,$old,$new){ global $wpdb; $wpdb->insert($wpdb->prefix . QP_SCI_MAX_V621_TABLE, ['slug'=>$slug,'field_name'=>$field,'old_value'=>$old,'new_value'=>$new,'created'=>current_time('mysql')], ['%s','%s','%s','%s','%s']); }

function qp_sci_max_v621_data(){
    return [
        ['slug'=>'max-planck','locale'=>'fa-IR','title'=>'زندگی‌نامه ماکس پلانک | پدر نظریه کوانتوم','seo_title'=>'زندگی‌نامه ماکس پلانک | پدر کوانتوم و ثابت پلانک','meta_description'=>'زندگی‌نامه کامل ماکس پلانک از تولد 1858 تا نوبل 1918 و ثابت پلانک و قانون تابش با منابع معتبر سالم.','focus_keyword'=>'زندگی‌نامه ماکس پلانک','content_file'=>'max-planck.fa.html','canonical'=>'https://qpedia.ir/scientists/max-planck/','hreflang_en'=>'https://qpedia.ir/en/scientists/max-planck/','hreflang_fa'=>'https://qpedia.ir/scientists/max-planck/','old_slugs'=>['max-plank'],'status'=>'publish','post_type'=>'quantum_scientist'],
        ['slug'=>'max-planck-en','locale'=>'en-US','title'=>'Max Planck Biography | Father of Quantum Theory','seo_title'=>'Max Planck Biography | Quantum Constant & Nobel 1918','meta_description'=>'Full biography of Max Planck: born 1858, quantum hypothesis 1900, Planck constant, Nobel 1918, verified sources.','focus_keyword'=>'Max Planck biography','content_file'=>'max-planck.en.html','canonical'=>'https://qpedia.ir/en/scientists/max-planck/','hreflang_en'=>'https://qpedia.ir/en/scientists/max-planck/','hreflang_fa'=>'https://qpedia.ir/scientists/max-planck/','old_slugs'=>[],'status'=>'publish','post_type'=>'quantum_scientist','translation_of'=>'max-planck'],
    ];
}

function qp_sci_max_v621_301(){
    if(is_admin()) return;
    $r=$_SERVER['REQUEST_URI']??''; $p=parse_url($r, PHP_URL_PATH); if(!$p) return; $low=strtolower($p);
    if($low==='/scientists/max-plank/' || $low==='/scientists/max-plank' || $low==='/en/scientists/max-plank/' || $low==='/en/scientists/max-plank' || strpos($low,'/scientists/max-plank/')===0 || strpos($low,'/en/scientists/max-plank/')===0){
        wp_redirect(home_url(strpos($low,'/en/')===0?'/en/scientists/max-planck/':'/scientists/max-planck/'),301); exit;
    }
}
add_action('template_redirect','qp_sci_max_v621_301',1);

function qp_sci_max_v621_force_slug($data,$postarr){
    if($data['post_type']==='quantum_scientist' && $data['post_name']==='max-plank') $data['post_name']='max-planck';
    return $data;
}
add_filter('wp_insert_post_data','qp_sci_max_v621_force_slug',10,2);

function qp_sci_max_v621_admin_menu(){ add_management_page('QP Scientist Max Planck v6.2.1','QP Scientist Max Planck v6.2.1','manage_options','qp-scientist-max-planck-v621','qp_sci_max_v621_render_admin'); }
add_action('admin_menu','qp_sci_max_v621_admin_menu');

function qp_sci_max_v621_render_admin(){
    if(!current_user_can('manage_options')) return;
    $data=qp_sci_max_v621_data();
    $action=isset($_POST['qp_sci_max_v621_action'])?$_POST['qp_sci_max_v621_action']:'';
    $apply=($action==='apply'); $dry=($action==='dry'); $cleanup=($action==='cleanup');
    echo '<div class="wrap" style="max-width:1100px;"><h1>QP Scientist Max Planck v6.2.1 FIX Install — PUBLISH</h1>';
    echo '<div style="background:#fef2f2;border:2px solid #f87171;padding:14px 18px;border-radius:10px;margin:12px 0;"><strong style="color:#dc2626;">فیکس نصب:</strong> این نسخه مثل اینشتین که نصب شد، HTML را از فایل جدا می‌خواند، JSON بزرگ ندارد.<br>1) max-plank غلط را Trash می‌کند، 2) max-planck FA+EN را Publish می‌کند، 3) خودش 301 دارد.</div>';
    if($cleanup||$dry||$apply){
        echo '<h2>Step1: Cleanup max-plank typo</h2><div style="background:#f9f9f9;border:1px solid #ddd;padding:12px;white-space:pre-wrap;font-family:monospace;font-size:11px;max-height:300px;overflow:auto;">';
        foreach(['max-plank','max-plank-en'] as $old){
            $oid=qp_sci_max_v621_find($old);
            if($oid){ echo "Found {$old} (#{$oid} ".get_post_status($oid).") will trash\n"; if($apply||$cleanup){ wp_trash_post($oid); echo " → Trashed\n"; } } else { echo "No {$old} (good)\n"; }
        }
        echo '</div>';
        echo '<h2>'.($apply?'Publishing...':'Dry-run').'</h2><div style="background:#f0fdf4;border:1px solid #86efac;padding:12px;white-space:pre-wrap;font-family:monospace;font-size:11px;max-height:600px;overflow:auto;">';
        foreach($data as $i=>$item){
            $slug=$item['slug']; $pid=qp_sci_max_v621_find($slug);
            $path=plugin_dir_path(__FILE__).$item['content_file'];
            $content=file_exists($path)?file_get_contents($path):'';
            echo ($i+1).". {$slug} len ".strlen($content)." ";
            if(!$pid) echo "CREATE\n"; else echo "UPDATE #{$pid} ".get_post_status($pid)."\n";
            if($apply && $content){
                if(!$pid){
                    $pid=wp_insert_post(['post_title'=>$item['title'],'post_name'=>$slug,'post_content'=>$content,'post_type'=>$item['post_type'],'post_status'=>'publish']);
                    if($pid){
                        update_post_meta($pid,'rank_math_title',$item['seo_title']);
                        update_post_meta($pid,'rank_math_description',$item['meta_description']);
                        update_post_meta($pid,'rank_math_focus_keyword',$item['focus_keyword']);
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
                    qp_sci_max_v621_backup($slug,'post_content',$old,$content);
                    wp_update_post(['ID'=>$pid,'post_title'=>$item['title'],'post_name'=>$slug,'post_content'=>$content]);
                    update_post_meta($pid,'rank_math_title',$item['seo_title']);
                    update_post_meta($pid,'rank_math_description',$item['meta_description']);
                    wp_update_post(['ID'=>$pid,'post_status'=>'publish']);
                    echo " → Updated PUBLISH #{$pid}\n";
                }
            }
        }
        echo '</div>';
        if($apply) echo '<div class="notice notice-success"><p>✅ max-planck FA+EN Publish شد. حالا https://qpedia.ir/scientists/max-planck/ باز می‌شود و /max-plank/ 301 می‌شود. کش را پاک کن.</p></div>';
    }
    echo '<form method="post" style="margin-top:20px;">'; wp_nonce_field('qp_sci_max_v621');
    echo '<p><button type="submit" name="qp_sci_max_v621_action" value="cleanup" class="button">1) Cleanup max-plank</button> <button type="submit" name="qp_sci_max_v621_action" value="dry" class="button">2) Dry-run</button> <button type="submit" name="qp_sci_max_v621_action" value="apply" class="button button-primary" style="background:#dc2626;border-color:#dc2626;" onclick="return confirm('Publish?')">3) FIX NOW — Publish FA+EN</button></p></form></div>';
}
