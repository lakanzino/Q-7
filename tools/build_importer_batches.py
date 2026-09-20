#!/usr/bin/env python3
from pathlib import Path
import re,json,html,shutil,zipfile,csv
R=Path(__file__).resolve().parents[1]; OUT=R/'plugin-packages'; OUT.mkdir(exist_ok=True)
imgs={}
for d in ['featured-images','featured-images-006-090','featured-images-final']:
 for p in (R/d).glob('*.webp'): imgs[p.stem]=p
alts={}
for mf in [R/'featured-images-006-090/manifest.csv',R/'featured-images-final/manifest.csv']:
 if mf.exists():
  for x in csv.DictReader(open(mf,encoding='utf-8-sig')): alts[x['slug']]=x['alt_fa']

def fields(s):
 def get(k):
  m=re.search(r'^- \*\*'+re.escape(k)+r':\*\*\s*(.+)$',s,re.M); return m.group(1).strip() if m else ''
 h=re.search(r'^# (.+)$',s,re.M).group(1).strip(); fm=re.search(r'^article_number:\s*(\d+)',s,re.M); sm=re.search(r'^slug:\s*(.+)',s,re.M)
 return int(fm.group(1)),sm.group(1).strip(),h,get('عنوان SEO'),get('توضیحات متا'),get('کلیدواژه اصلی'),get('کلیدواژه‌های فرعی'),get('دسته')
def inline(s):
 s=re.sub(r'\[([^]]+)\]\((https?://[^)]+)\)',r'<a href="\2">\1</a>',s); s=re.sub(r'\*\*([^*]+)\*\*',r'<strong>\1</strong>',s); s=re.sub(r'`([^`]+)`',r'<code>\1</code>',s); return s
def body(raw):
 x=raw[re.search(r'^# .+$',raw,re.M).end():].strip(); lines=x.splitlines(); o=[]; para=[]; i=0
 def flush():
  if para:o.append('<p>'+inline(' '.join(para))+'</p>');para.clear()
 while i<len(lines):
  z=lines[i].strip()
  if not z:flush();i+=1;continue
  if re.match(r'^<p><strong>.*</strong></p>$',z):
   flush();o.append(z);i+=1
   if i<len(lines) and re.match(r'^<p>.*</p>$',lines[i].strip()):o.append(lines[i].strip());i+=1
   continue
  m=re.match(r'^(#{2,4})\s+(.+)',z)
  if m:flush();o.append(f'<h{len(m.group(1))}>{inline(m.group(2))}</h{len(m.group(1))}>');i+=1;continue
  if re.match(r'^\d+\.\s',z):
   flush();a=[]
   while i<len(lines) and re.match(r'^\d+\.\s',lines[i].strip()):a.append(re.sub(r'^\d+\.\s*','',lines[i].strip()));i+=1
   o.append('<ol>'+''.join('<li>'+inline(q)+'</li>' for q in a)+'</ol>');continue
  para.append(z);i+=1
 flush();return '\n'.join(o)
items=[]
for p in R.glob('articles/[0-9]*-*.md'):
 if p.name.endswith('.review.md'):continue
 raw=p.read_text(); n,slug,title,seo,desc,focus,secondary,cat=fields(raw)
 if slug not in imgs:continue
 htmlbody=body(raw); fq=[]
 for q,a in re.findall(r'<p><strong>(.*?)</strong></p>\s*<p>(.*?)</p>',htmlbody,re.S):fq.append({'question':re.sub('<[^>]+>','',q),'answer':re.sub('<[^>]+>','',a)})
 assert len(fq)==5,(n,len(fq))
 first=re.search(r'<p>(.*?)</p>',htmlbody,re.S); excerpt=re.sub('<[^>]+>','',first.group(1))[:320]
 tags=[x.strip() for x in re.split('[،,]',secondary) if x.strip()][:5]
 items.append(dict(number=n,slug=slug,title=title,seo=seo,desc=desc,focus=focus,secondary=secondary,category=cat,excerpt=excerpt,tags=tags,image=slug+'.webp',alt=alts.get(slug,title),caption='تصویر شاخص مقاله «'+title+'» در Qpedia.',content=htmlbody,faqs=fq,image_path=imgs[slug]))
items.sort(key=lambda x:x['number'])
# Articles 1-5 already have their dedicated importer; make subsequent groups of 20.
items=[x for x in items if x['number']>5]
PHP=r'''<?php
/** Plugin Name: Qpedia – درون‌ریز بسته __LABEL__
 * Description: درون‌ریز یک‌بارمصرف مقاله‌های بررسی‌شده Qpedia همراه تصویر، سئو و FAQ؛ همه به‌صورت پیش‌نویس.
 * Version: 1.0.0
 * Author: Qpedia Editorial
 */
if(!defined('ABSPATH'))exit;
add_action('admin_menu',function(){add_management_page('درون‌ریز Qpedia __LABEL__','درون‌ریز Qpedia __LABEL__','manage_options','qpedia-import-__ID__','qpb_page');});
function qpb_page(){if(!current_user_can('manage_options'))return;echo '<div class="wrap" dir="rtl"><h1>درون‌ریزی Qpedia __LABEL__</h1>';if(!empty($_POST['qpb_import'])){check_admin_referer('qpb_action');echo '<div class="notice notice-success"><ul>';foreach(qpb_run() as $r)echo '<li>'.esc_html($r).'</li>';echo '</ul></div>';}echo '<p>همه نوشته‌ها پیش‌نویس‌اند؛ اسلاگ موجود دوباره ساخته نمی‌شود.</p><form method="post">';wp_nonce_field('qpb_action');submit_button('ساخت پیش‌نویس‌ها','primary','qpb_import');echo '</form></div>';}
function qpb_run(){$a=json_decode(file_get_contents(plugin_dir_path(__FILE__).'articles.json'),true);if(!is_array($a))return array('خطا در داده');$r=array();foreach($a as $x){$e=get_page_by_path($x['slug'],OBJECT,'quantum_article');if($e){$r[]='از قبل موجود: '.$x['title'];continue;}$id=wp_insert_post(array('post_type'=>'quantum_article','post_status'=>'draft','post_title'=>$x['title'],'post_name'=>$x['slug'],'post_content'=>$x['content'],'post_excerpt'=>$x['excerpt'],'post_author'=>get_current_user_id()),true);if(is_wp_error($id)){$r[]='خطا: '.$x['title'];continue;}if(taxonomy_exists('quantum_category')){$t=term_exists($x['category'],'quantum_category');if(!$t)$t=wp_insert_term($x['category'],'quantum_category');if(!is_wp_error($t))wp_set_object_terms($id,array((int)(is_array($t)?$t['term_id']:$t)),'quantum_category');}wp_set_post_tags($id,$x['tags'],false);$m=array('_qpedia_seo_title'=>$x['seo'],'_qpedia_meta_description'=>$x['desc'],'_qpedia_focus_keyphrase'=>$x['focus'],'_qpedia_secondary_keyphrases'=>$x['secondary'],'_jetica_seo_title'=>$x['seo'],'_jetica_meta_description'=>$x['desc'],'_jetica_focus_keyword'=>$x['focus'],'rank_math_title'=>$x['seo'],'rank_math_description'=>$x['desc'],'rank_math_focus_keyword'=>$x['focus'],'_yoast_wpseo_title'=>$x['seo'],'_yoast_wpseo_metadesc'=>$x['desc'],'_yoast_wpseo_focuskw'=>$x['focus']);foreach($m as $k=>$v)update_post_meta($id,$k,$v);$en=array();foreach($x['faqs'] as $f)$en[]=array('@type'=>'Question','name'=>$f['question'],'acceptedAnswer'=>array('@type'=>'Answer','text'=>$f['answer']));$schema=array('@context'=>'https://schema.org','@type'=>'FAQPage','mainEntity'=>$en);update_post_meta($id,'rank_math_schema_FAQPage',$schema);update_post_meta($id,'_qpedia_faq_schema',wp_json_encode($schema,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES));$im=qpb_img($id,$x);if(is_wp_error($im))$r[]='مقاله ساخته شد، خطای تصویر: '.$x['title'];else{set_post_thumbnail($id,$im);$r[]='ساخته شد: '.$x['title'];}}return $r;}
function qpb_img($pid,$x){$p=plugin_dir_path(__FILE__).'assets/'.$x['image'];if(!file_exists($p))return new WP_Error('missing','تصویر نیست');$b=wp_upload_bits($x['image'],null,file_get_contents($p));if($b['error'])return new WP_Error('upload',$b['error']);$ft=wp_check_filetype($b['file']);$id=wp_insert_attachment(array('post_mime_type'=>$ft['type'],'post_title'=>$x['title'],'post_excerpt'=>$x['caption'],'post_status'=>'inherit'),$b['file'],$pid,true);if(is_wp_error($id))return $id;require_once ABSPATH.'wp-admin/includes/image.php';$md=wp_generate_attachment_metadata($id,$b['file']);if($md)wp_update_attachment_metadata($id,$md);update_post_meta($id,'_wp_attachment_image_alt',$x['alt']);return $id;}
'''
for old in OUT.glob('qpedia-import-*.zip'):old.unlink()
for bi in range(0,len(items),20):
 batch=items[bi:bi+20]; idx=bi//20+1; label=f"بسته {idx+1}؛ {batch[0]['number']} تا {batch[-1]['number']}"; folder=R/'.plugin-build'/f'qpedia-import-{idx+1:02d}'
 if folder.exists():shutil.rmtree(folder)
 (folder/'assets').mkdir(parents=True)
 data=[]
 for x in batch:
  y={k:v for k,v in x.items() if k!='image_path'};data.append(y);shutil.copy2(x['image_path'],folder/'assets'/x['image'])
 (folder/'articles.json').write_text(json.dumps(data,ensure_ascii=False,indent=2))
 (folder/'qpedia-importer.php').write_text(PHP.replace('__LABEL__',label).replace('__ID__',f'{idx+1:02d}'))
 (folder/'README.txt').write_text(f'Qpedia {label}\nتعداد: {len(batch)}\nفقط پیش‌نویس؛ اجرای دوباره اسلاگ تکراری نمی‌سازد.\n')
 z=OUT/f'qpedia-import-{idx+1:02d}-{batch[0]["number"]:03d}-{batch[-1]["number"]:03d}.zip'
 with zipfile.ZipFile(z,'w',zipfile.ZIP_DEFLATED) as q:
  for p in folder.rglob('*'):
   if p.is_file():q.write(p,p.relative_to(folder.parent))
 print(z.name,len(batch),[x['number'] for x in batch])
