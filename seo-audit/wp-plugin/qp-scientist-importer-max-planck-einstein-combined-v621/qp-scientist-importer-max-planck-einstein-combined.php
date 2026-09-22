<?php
/**
 * Plugin Name: QP Scientist Importer — Max Planck + Einstein COMBINED v6.2.1 FIX Install
 * Description: COMBINED FIX v6.2.1 — Publishes 4 posts: max-planck FA+EN + albert-einstein FA+EN as PUBLISH, trashes typos max-plank / albert-einstein-2 / schrodingerr, built-in 301. Fixed install issue by loading HTML from separate files instead of big JSON.
 * Version: 6.2.1
 * Author: Arena Agent for qpedia.ir
 */
if (!defined('ABSPATH')) exit;
define('QP_SCI_COMB_V621_TABLE', 'qp_scientist_combined_backup');

function qp_sci_comb_v621_maybe_install(){ global $wpdb; $table=$wpdb->prefix . QP_SCI_COMB_V621_TABLE; if($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s',$table))===$table) return; require_once ABSPATH.'wp-admin/includes/upgrade.php'; $charset=$wpdb->get_charset_collate(); $sql="CREATE TABLE {$table} (id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, slug VARCHAR(100) NOT NULL, field_name VARCHAR(100) NOT NULL, old_value LONGTEXT, new_value LONGTEXT, created DATETIME NOT NULL, PRIMARY KEY (id), KEY slug (slug)) {$charset};"; dbDelta($sql); }
register_activation_hook(__FILE__, 'qp_sci_comb_v621_maybe_install');

function qp_sci_comb_v621_find($slug){
    $posts=get_posts(['name'=>$slug,'post_type'=>'quantum_scientist','post_status'=>['publish','draft','pending','private','trash'],'posts_per_page'=>1,'fields'=>'ids']);
    if($posts) return (int)$posts[0];
    $posts=get_posts(['name'=>$slug,'post_status'=>['publish','draft','pending','private','trash'],'posts_per_page'=>1,'fields'=>'ids']);
    return $posts?(int)$posts[0]:0;
}
function qp_sci_comb_v621_backup($slug,$field,$old,$new){ global $wpdb; $wpdb->insert($wpdb->prefix . QP_SCI_COMB_V621_TABLE, ['slug'=>$slug,'field_name'=>$field,'old_value'=>$old,'new_value'=>$new,'created'=>current_time('mysql')], ['%s','%s','%s','%s','%s']); }

function qp_sci_comb_v621_data(){
    $dir=plugin_dir_path(__FILE__);
    return [
        [
            'slug'=>'max-planck','locale'=>'fa-IR','title'=>'زندگی‌نامه ماکس پلانک | پدر نظریه کوانتوم','seo_title'=>'زندگی‌نامه ماکس پلانک | پدر کوانتوم و ثابت پلانک','meta_description'=>'زندگی‌نامه کامل ماکس پلانک از تولد 1858 تا نوبل 1918 و ثابت پلانک و قانون تابش با منابع معتبر سالم.','focus_keyword'=>'زندگی‌نامه ماکس پلانک','content_file'=>'max-planck.fa.html','canonical'=>'https://qpedia.ir/scientists/max-planck/','hreflang_en'=>'https://qpedia.ir/en/scientists/max-planck/','hreflang_fa'=>'https://qpedia.ir/scientists/max-planck/','old_slugs'=>['max-plank'],'status'=>'publish','post_type'=>'quantum_scientist'
        ],
        [
            'slug'=>'max-planck-en','locale'=>'en-US','title'=>'Max Planck Biography | Father of Quantum Theory','seo_title'=>'Max Planck Biography | Quantum Constant & Nobel 1918','meta_description'=>'Full biography of Max Planck: born 1858, quantum hypothesis 1900, Planck constant, Nobel 1918, verified sources.','focus_keyword'=>'Max Planck biography','content_file'=>'max-planck.en.html','canonical'=>'https://qpedia.ir/en/scientists/max-planck/','hreflang_en'=>'https://qpedia.ir/en/scientists/max-planck/','hreflang_fa'=>'https://qpedia.ir/scientists/max-planck/','old_slugs'=>[],'status'=>'publish','post_type'=>'quantum_scientist','translation_of'=>'max-planck'
        ],
        [
            'slug'=>'albert-einstein','locale'=>'fa-IR','title'=>'زندگی‌نامه آلبرت اینشتین | از فوتوالکتریک تا نسبیت عام','seo_title'=>'زندگی‌نامه آلبرت اینشتین | نوبل و نسبیت عام','meta_description'=>'زندگی‌نامه کامل آلبرت اینشتین از تولد ۱۴ مارس ۱۸۷۹ تا نوبل ۱۹۲۱ فوتوالکتریک و نسبیت ۱۹۰۵ و ۱۹۱۵ با ۱۰ منبع معتبر سالم.','focus_keyword'=>'زندگی‌نامه آلبرت اینشتین','content_file'=>'albert-einstein.fa.html','canonical'=>'https://qpedia.ir/scientists/albert-einstein/','hreflang_en'=>'https://qpedia.ir/en/scientists/albert-einstein/','hreflang_fa'=>'https://qpedia.ir/scientists/albert-einstein/','old_slugs'=>['albert-einstein-2','albert-einstein-3'],'status'=>'publish','post_type'=>'quantum_scientist'
        ],
        [
            'slug'=>'albert-einstein-en','locale'=>'en-US','title'=>'Albert Einstein Biography | Photoelectric Effect to General Relativity','seo_title'=>'Albert Einstein Biography | Photoelectric to Relativity','meta_description'=>'Full biography of Albert Einstein: born 14 March 1879 Ulm, Nobel 1921 photoelectric, relativity 1905 1915, 10 verified sources.','focus_keyword'=>'Albert Einstein biography','content_file'=>'albert-einstein.en.html','canonical'=>'https://qpedia.ir/en/scientists/albert-einstein/','hreflang_en'=>'https://qpedia.ir/en/scientists/albert-einstein/','hreflang_fa'=>'https://qpedia.ir/scientists/albert-einstein/','old_slugs'=>[],'status'=>'publish','post_type'=>'quantum_scientist','translation_of'=>'albert-einstein'
        ],
    ];
}

function qp_sci_comb_v621_301(){
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
add_action('template_redirect','qp_sci_comb_v621_301',1);

function qp_sci_comb_v621_force_slug($data,$postarr){
    if($data['post_type']==='quantum_scientist'){
        if($data['post_name']==='max-plank') $data['post_name']='max-planck';
        if($data['post_name']==='albert-einstein-2') $data['post_name']='albert-einstein';
        if($data['post_name']==='schrodingerr') $data['post_name']='erwin-schrodinger';
        if($data['post_name']==='ervin-schrodinger') $data['post_name']='erwin-schrodinger';
    }
    return $data;
}
add_filter('wp_insert_post_data','qp_sci_comb_v621_force_slug',10,2);

function qp_sci_comb_v621_admin_menu(){ add_management_page('QP Scientist COMBINED v6.2.1','QP Scientist COMBINED v6.2.1','manage_options','qp-scientist-combined-v621','qp_sci_comb_v621_render_admin'); }
add_action('admin_menu','qp_sci_comb_v621_admin_menu');

function qp_sci_comb_v621_render_admin(){
    if(!current_user_can('manage_options')) return;
    $data=qp_sci_comb_v621_data();
    $action=isset($_POST['qp_sci_comb_v621_action'])?$_POST['qp_sci_comb_v621_action']:'';
    $apply=($action==='apply'); $dry=($action==='dry'); $cleanup=($action==='cleanup');
    echo '<div class="wrap" style="max-width:1150px;"><h1>QP Scientist COMBINED v6.2.1 — Max Planck + Einstein PUBLISH</h1>';
    echo '<div style="background:#fef2f2;border:2px solid #f87171;padding:14px 18px;border-radius:10px;margin:12px 0;"><strong style="color:#dc2626;">فیکس نصب:</strong> این نسخه HTML ها را از فایل جدا می‌خواند، JSON بزرگ ندارد، نصبش سبک‌تر است.<br>هر 4 تا را یه دفعه Publish + Trash typos + 301</div>';
    if($cleanup || $dry || $apply){
        echo '<h2>Step 1: Cleanup typos</h2><div style="background:#f9f9f9;border:1px solid #ddd;padding:12px;white-space:pre-wrap;font-family:monospace;font-size:11px;max-height:300px;overflow:auto;">';
        foreach(['max-plank','max-plank-en','albert-einstein-2','albert-einstein-3','schrodingerr','ervin-schrodinger'] as $old){
            $oid=qp_sci_comb_v621_find($old);
            if($oid){ echo "Found {$old} (#{$oid} ".get_post_status($oid).") will trash\n"; if($apply||$cleanup){ wp_trash_post($oid); echo " → Trashed\n"; } } else { echo "No {$old} (good)\n"; }
        }
        echo '</div>';
        echo '<h2>'.($apply?'Publishing 4...':'Dry-run 4').'</h2><div style="background:#f0fdf4;border:1px solid #86efac;padding:12px;white-space:pre-wrap;font-family:monospace;font-size:11px;max-height:800px;overflow:auto;">';
        foreach($data as $idx=>$item){
            $slug=$item['slug']; $post_id=qp_sci_comb_v621_find($slug);
            $content_path=plugin_dir_path(__FILE__).$item['content_file'];
            $content=file_exists($content_path)?file_get_contents($content_path):'';
            echo ($idx+1).". {$slug} ({$item['locale']}) len ".strlen($content)." ";
            if(!$post_id){ echo "CREATE\n"; } else { echo "UPDATE #{$post_id} ".get_post_status($post_id)."\n"; }
            if($apply && $content){
                if(!$post_id){
                    $post_id=wp_insert_post(['post_title'=>$item['title'],'post_name'=>$slug,'post_content'=>$content,'post_type'=>$item['post_type'],'post_status'=>'publish']);
                    if($post_id){
                        update_post_meta($post_id,'rank_math_title',$item['seo_title']);
                        update_post_meta($post_id,'rank_math_description',$item['meta_description']);
                        update_post_meta($post_id,'rank_math_focus_keyword',$item['focus_keyword']);
                        update_post_meta($post_id,'_qpedia_lang',$item['locale']);
                        update_post_meta($post_id,'_qpedia_canonical',$item['canonical']);
                        update_post_meta($post_id,'_qpedia_hreflang_fa',$item['hreflang_fa']);
                        update_post_meta($post_id,'_qpedia_hreflang_en',$item['hreflang_en']);
                        update_post_meta($post_id,'_qpedia_translation_of',$item['translation_of']??'');
                        echo " → Created PUBLISH #{$post_id}\n";
                    }
                } else {
                    $old=get_post_field('post_content',$post_id);
                    qp_sci_comb_v621_backup($slug,'post_content',$old,$content);
                    wp_update_post(['ID'=>$post_id,'post_title'=>$item['title'],'post_name'=>$slug,'post_content'=>$content]);
                    update_post_meta($post_id,'rank_math_title',$item['seo_title']);
                    update_post_meta($post_id,'rank_math_description',$item['meta_description']);
                    update_post_meta($post_id,'rank_math_focus_keyword',$item['focus_keyword']);
                    wp_update_post(['ID'=>$post_id,'post_status'=>'publish']);
                    echo " → Updated PUBLISH #{$post_id}\n";
                }
            }
        }
        echo '</div>';
        if($apply) echo '<div class="notice notice-success"><p>✅ هر 4 تا Publish شد. کش را پاک کنید.</p></div>';
    }
    echo '<form method="post" style="margin-top:20px;">'; wp_nonce_field('qp_sci_comb_v621');
    echo '<p><button type="submit" name="qp_sci_comb_v621_action" value="cleanup" class="button">1) Cleanup typos</button> <button type="submit" name="qp_sci_comb_v621_action" value="dry" class="button">2) Dry-run 4</button> <button type="submit" name="qp_sci_comb_v621_action" value="apply" class="button button-primary" style="background:#dc2626;border-color:#dc2626;" onclick="return confirm('Publish 4?')">3) FIX NOW — Publish 4</button></p></form></div>';
}
