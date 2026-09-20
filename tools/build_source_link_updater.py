#!/usr/bin/env python3
from pathlib import Path
import json,re,html,zipfile,shutil
R=Path(__file__).resolve().parents[1]
# Only slugs that were actually delivered in importer packages 1-7.
slugs=set()
# package 1
p=R/'qpedia-five-article-importer/articles.json'
if p.exists(): slugs.update(x['slug'] for x in json.loads(p.read_text()))
# packages 2-7
for z in (R/'plugin-packages').glob('qpedia-import-*.zip'):
 with zipfile.ZipFile(z) as q:
  name=next(n for n in q.namelist() if n.endswith('/articles.json'))
  slugs.update(x['slug'] for x in json.loads(q.read(name)))
assert len(slugs)==123,len(slugs)
data={}
for p in R.glob('articles/[0-9]*-*.md'):
 if p.name.endswith('.review.md'):continue
 s=p.read_text(); m=re.search(r'^slug:\s*(.+)$',s,re.M)
 if not m or m.group(1).strip() not in slugs:continue
 slug=m.group(1).strip(); sec=s.split('## منابع',1)
 assert len(sec)==2,p
 lines=[]
 for line in sec[1].splitlines():
  line=line.strip()
  if not re.match(r'^\d+\.\s+',line):continue
  text=re.sub(r'^\d+\.\s*','',line)
  urls=re.findall(r'https?://\S+',text)
  if urls:
   url=urls[-1].rstrip('.,;')
   citation=text[:text.rfind(url)].strip().rstrip('—- ')
  else:
   # Stable publisher/DOI pages for the seven legacy book citations that lacked URLs.
   fallbacks={
    'Optical Resonance and Two-Level Atoms':'https://store.doverpublications.com/products/9780486655338',
    'Atom–Photon Interactions':'https://doi.org/10.1002/9783527617197',
    'Quantum Computation and Quantum Information':'https://doi.org/10.1017/CBO9780511976667',
   }
   url=next((u for title,u in fallbacks.items() if title in text),None)
   assert url,(p,line)
   citation=text
  # Entire scientific citation is clickable, not a raw URL.
  linked=f'<a href="{html.escape(url,quote=True)}" target="_blank" rel="noopener noreferrer">{html.escape(citation)}</a>'
  lines.append(linked)
 assert len(lines)>=3,(p,len(lines))
 data[slug]=lines
assert len(data)==123,(len(data),sorted(slugs-set(data)))
B=R/'qpedia-source-link-updater'; O=R/'qpedia-source-link-updater.zip'
if B.exists():shutil.rmtree(B)
B.mkdir(); (B/'sources.json').write_text(json.dumps(data,ensure_ascii=False,indent=2))
php=r'''<?php
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
'''
(B/'qpedia-source-link-updater.php').write_text(php)
(B/'README.txt').write_text('''Qpedia Source Link Updater\n\n1. افزونه را نصب و فعال کنید.\n2. ابزارها ← اصلاح منابع Qpedia را باز کنید.\n3. دکمه افزودن لینک معتبر به منابع را یک بار بزنید.\n4. چند پیش‌نویس و مقاله منتشرشده را کنترل کنید.\n5. افزونه را غیرفعال و حذف کنید.\n\nفقط بخش منابع تغییر می‌کند و نسخه محتوای قبلی در متای خصوصی هر نوشته نگهداری می‌شود.\n''')
if O.exists():O.unlink()
with zipfile.ZipFile(O,'w',zipfile.ZIP_DEFLATED) as z:
 for p in B.rglob('*'):
  if p.is_file():z.write(p,p.relative_to(B.parent))
print(O,'slugs',len(data),'sources',sum(map(len,data.values())))
