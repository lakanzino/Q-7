<?php
/**
 * Plugin Name: Qpedia – اصلاح لینک منابع علمی
 * Description: بخش منابع ۱۲۳ مقاله واردشده Qpedia را بدون تغییر متن مقاله با استنادهای لینک‌دار به DOI یا صفحه منبع به‌روزرسانی می‌کند.
 * Version: 1.0.0
 * Author: Qpedia Editorial
 */
if (!defined('ABSPATH')) exit;
add_action('admin_menu', function(){
 add_management_page('اصلاح لینک منابع Qpedia','اصلاح منابع Qpedia','manage_options','qpedia-source-links','qpsl_page');
});
function qpsl_page(){
 if(!current_user_can('manage_options'))return;
 echo '<div class="wrap" dir="rtl"><h1>اصلاح لینک منابع علمی Qpedia</h1>';
 if(!empty($_POST['qpsl_run'])){
  check_admin_referer('qpsl_action'); $r=qpsl_run();
  echo '<div class="notice notice-success"><p><strong>عملیات پایان یافت.</strong></p>';
  echo '<p>به‌روزرسانی‌شده: '.intval($r['updated']).' | بدون تغییر: '.intval($r['unchanged']).' | پیدا نشد: '.intval($r['missing']).' | خطا: '.intval($r['errors']).'</p>';
  if($r['messages'])echo '<details><summary>گزارش جزئی</summary><ul><li>'.implode('</li><li>',array_map('esc_html',$r['messages'])).'</li></ul></details>';
  echo '</div>';
 }
 echo '<p>این ابزار فقط بخش <strong>منابع</strong> را در ۱۲۳ مقاله هدف بازسازی می‌کند. وضعیت پیش‌نویس یا منتشرشده، عنوان، متن، تصویر، سئو و FAQ تغییر نمی‌کنند.</p>';
 echo '<p>پیش از نخستین تغییر، نسخه کامل محتوای قبلی در متای خصوصی همان نوشته ذخیره می‌شود.</p>';
 echo '<form method="post">';wp_nonce_field('qpsl_action');submit_button('افزودن لینک معتبر به منابع','primary','qpsl_run');echo '</form></div>';
}
function qpsl_find($slug){
 foreach(array('quantum_article','post','page') as $type){$p=get_page_by_path($slug,OBJECT,$type);if($p)return $p;}
 $q=get_posts(array('name'=>$slug,'post_type'=>'any','post_status'=>'any','numberposts'=>1));return $q?$q[0]:null;
}
function qpsl_run(){
 $map=json_decode(file_get_contents(plugin_dir_path(__FILE__).'sources.json'),true);
 $r=array('updated'=>0,'unchanged'=>0,'missing'=>0,'errors'=>0,'messages'=>array());
 if(!is_array($map)){$r['errors']++;$r['messages'][]='فایل داده خوانده نشد.';return $r;}
 foreach($map as $slug=>$sources){
  $p=qpsl_find($slug);if(!$p){$r['missing']++;$r['messages'][]='پیدا نشد: '.$slug;continue;}
  $list='<h2>منابع</h2>' . "\n" . '<ol class="qpedia-scientific-sources">';
  foreach($sources as $source)$list.='<li>'.$source.'</li>';
  $list.='</ol>';
  $old=$p->post_content;
  // منابع طبق استاندارد Qpedia آخرین بخش‌اند. هر محتوای قبلی از تیتر منابع تا انتها با فهرست کنترل‌شده جایگزین می‌شود.
  if(preg_match('~<h2[^>]*>\s*منابع\s*</h2>~u',$old,$m,PREG_OFFSET_CAPTURE)){
   $start=$m[0][1];$new=substr($old,0,$start).$list;
  } else {
   $new=rtrim($old)."\n".$list;
  }
  if($new===$old){$r['unchanged']++;continue;}
  if(!metadata_exists('post',$p->ID,'_qpedia_before_source_link_update'))update_post_meta($p->ID,'_qpedia_before_source_link_update',$old);
  $ok=wp_update_post(array('ID'=>$p->ID,'post_content'=>$new),true);
  if(is_wp_error($ok)){$r['errors']++;$r['messages'][]='خطا: '.$slug.' — '.$ok->get_error_message();}
  else $r['updated']++;
 }
 return $r;
}
