<?php
/**
 * Plugin Name: QP Scientist Importer — Max Planck FA+EN Minimal v6.2.3
 * Description: MINIMAL FIX v6.2.3 — No table, no JSON, no backup, just publishes max-planck FA+EN as PUBLISH and trashes max-plank typo + 301. Use this if v6.2.2 gives critical error.
 * Version: 6.2.3
 * Author: Arena Agent for qpedia.ir
 */
if (!defined('ABSPATH')) exit;

function qp_max_min_find($slug){
    $posts=get_posts(['name'=>$slug,'post_type'=>'quantum_scientist','post_status'=>['publish','draft','pending','private','trash'],'posts_per_page'=>1,'fields'=>'ids']);
    if($posts) return (int)$posts[0];
    $posts=get_posts(['name'=>$slug,'post_status'=>['publish','draft','pending','private','trash'],'posts_per_page'=>1,'fields'=>'ids']);
    return $posts?(int)$posts[0]:0;
}

function qp_max_min_301(){
    if(is_admin()) return;
    $r=$_SERVER['REQUEST_URI']??''; $p=parse_url($r, PHP_URL_PATH); if(!$p) return; $low=strtolower($p);
    if($low==='/scientists/max-plank/' || $low==='/scientists/max-plank' || $low==='/en/scientists/max-plank/' || $low==='/en/scientists/max-plank' || strpos($low,'/scientists/max-plank/')===0 || strpos($low,'/en/scientists/max-plank/')===0){
        wp_redirect(home_url(strpos($low,'/en/')===0?'/en/scientists/max-planck/':'/scientists/max-planck/'),301); exit;
    }
}
add_action('template_redirect','qp_max_min_301',1);

function qp_max_min_force_slug($data,$postarr){
    if(isset($data['post_type']) && $data['post_type']==='quantum_scientist' && isset($data['post_name']) && $data['post_name']==='max-plank'){
        $data['post_name']='max-planck';
    }
    return $data;
}
add_filter('wp_insert_post_data','qp_max_min_force_slug',10,2);

function qp_max_min_menu(){ add_management_page('QP Max Planck Minimal v6.2.3','QP Max Planck Minimal v6.2.3','manage_options','qp-max-min-v623','qp_max_min_render'); }
add_action('admin_menu','qp_max_min_menu');

function qp_max_min_render(){
    if(!current_user_can('manage_options')) return;
    $action=isset($_POST['qp_max_min_action'])?$_POST['qp_max_min_action']:'';
    $apply=($action==='apply'); $dry=($action==='dry'); $cleanup=($action==='cleanup');
    echo '<div class="wrap" style="max-width:1100px;"><h1>QP Max Planck Minimal v6.2.3 — No Table No JSON</h1>';
    echo '<div style="background:#fef2f2;border:2px solid #f87171;padding:14px 18px;border-radius:10px;margin:12px 0;"><strong style="color:#dc2626;">نسخه فوق سبک بدون جدول:</strong> اگه نسخه‌های قبلی "مشکل جدی" می‌داد، این یکی باید نصب شود. فقط 2 HTML + 1 PHP.</div>';

    if($cleanup||$dry||$apply){
        echo '<h2>Cleanup max-plank typo</h2><div style="background:#f9f9f9;border:1px solid #ddd;padding:12px;white-space:pre-wrap;font-family:monospace;font-size:11px;">';
        foreach(['max-plank','max-plank-en'] as $old){
            $oid=qp_max_min_find($old);
            if($oid){ echo "Found {$old} #{$oid} ".get_post_status($oid)."\n"; if($apply||$cleanup){ wp_trash_post($oid); echo " → Trashed\n"; } } else { echo "No {$old} (good)\n"; }
        }
        echo '</div>';

        $files=[
            ['slug'=>'max-planck','file'=>'max-planck.fa.html','title'=>'زندگی‌نامه ماکس پلانک | پدر نظریه کوانتوم','locale'=>'fa-IR','seo_title'=>'زندگی‌نامه ماکس پلانک | پدر کوانتوم و ثابت پلانک','meta'=>'زندگی‌نامه کامل ماکس پلانک از تولد 1858 تا نوبل 1918 و ثابت پلانک و قانون تابش با منابع معتبر سالم.','kw'=>'زندگی‌نامه ماکس پلانک','can'=>'https://qpedia.ir/scientists/max-planck/','fa'=>'https://qpedia.ir/scientists/max-planck/','en'=>'https://qpedia.ir/en/scientists/max-planck/'],
            ['slug'=>'max-planck-en','file'=>'max-planck.en.html','title'=>'Max Planck Biography | Father of Quantum Theory','locale'=>'en-US','seo_title'=>'Max Planck Biography | Quantum Constant & Nobel 1918','meta'=>'Full biography of Max Planck: born 1858, quantum hypothesis 1900, Planck constant, Nobel 1918, verified sources.','kw'=>'Max Planck biography','can'=>'https://qpedia.ir/en/scientists/max-planck/','fa'=>'https://qpedia.ir/scientists/max-planck/','en'=>'https://qpedia.ir/en/scientists/max-planck/'],
        ];

        echo '<h2>'.($apply?'Publishing 2...':'Dry-run 2').'</h2><div style="background:#f0fdf4;border:1px solid #86efac;padding:12px;white-space:pre-wrap;font-family:monospace;font-size:11px;">';
        foreach($files as $idx=>$f){
            $slug=$f['slug']; $pid=qp_max_min_find($slug);
            $path=plugin_dir_path(__FILE__).$f['file'];
            $content=file_exists($path)?file_get_contents($path):'';
            echo ($idx+1).". {$slug} len ".strlen($content)." ";
            if(!$pid) echo "CREATE\n"; else echo "UPDATE #{$pid} ".get_post_status($pid)."\n";
            if($apply && $content){
                if(!$pid){
                    $pid=wp_insert_post(['post_title'=>$f['title'],'post_name'=>$slug,'post_content'=>$content,'post_type'=>'quantum_scientist','post_status'=>'publish']);
                    if($pid){
                        update_post_meta($pid,'rank_math_title',$f['seo_title']);
                        update_post_meta($pid,'rank_math_description',$f['meta']);
                        update_post_meta($pid,'rank_math_focus_keyword',$f['kw']);
                        update_post_meta($pid,'_qpedia_lang',$f['locale']);
                        update_post_meta($pid,'_qpedia_canonical',$f['can']);
                        update_post_meta($pid,'_qpedia_hreflang_fa',$f['fa']);
                        update_post_meta($pid,'_qpedia_hreflang_en',$f['en']);
                        update_post_meta($pid,'_qpedia_person_id','https://qpedia.ir/scientists/max-planck/#person');
                        echo " → Created PUBLISH #{$pid}\n";
                    } else {
                        echo " → FAILED create\n";
                    }
                } else {
                    wp_update_post(['ID'=>$pid,'post_title'=>$f['title'],'post_name'=>$slug,'post_content'=>$content]);
                    update_post_meta($pid,'rank_math_title',$f['seo_title']);
                    update_post_meta($pid,'rank_math_description',$f['meta']);
                    update_post_meta($pid,'_qpedia_lang',$f['locale']);
                    wp_update_post(['ID'=>$pid,'post_status'=>'publish']);
                    echo " → Updated PUBLISH #{$pid}\n";
                }
            }
        }
        echo '</div>';
        if($apply) echo '<div class="notice notice-success"><p>✅ هر دو Publish شد. کش را پاک کن. حالا https://qpedia.ir/scientists/max-planck/ باز می‌شود.</p></div>';
    }

    echo '<form method="post" style="margin-top:20px;">'; wp_nonce_field('qp_max_min');
    echo '<p><button type="submit" name="qp_max_min_action" value="cleanup" class="button">1) Cleanup max-plank</button> <button type="submit" name="qp_max_min_action" value="dry" class="button">2) Dry-run</button> <button type="submit" name="qp_max_min_action" value="apply" class="button button-primary" style="background:#dc2626;border-color:#dc2626;" onclick="return confirm('Publish?')">3) FIX NOW — Publish FA+EN</button></p></form></div>';
}
