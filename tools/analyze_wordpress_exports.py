#!/usr/bin/env python3
from pathlib import Path
import xml.etree.ElementTree as ET
from html.parser import HTMLParser
from urllib.parse import urlparse
from collections import Counter
import re,csv,json,html,statistics,hashlib
ROOT=Path(__file__).resolve().parents[1]; OUT=ROOT/'site-analysis-2026-09-20'; OUT.mkdir(exist_ok=True)
ARTICLE_XML=ROOT/'WordPress.2026-09-20.xml'; SCI_XML=ROOT/'WordPress.2026-09-20 (1).xml'
NS={'wp':'http://wordpress.org/export/1.2/','content':'http://purl.org/rss/1.0/modules/content/','dc':'http://purl.org/dc/elements/1.1/','excerpt':'http://wordpress.org/export/1.2/excerpt/'}
class Text(HTMLParser):
 def __init__(self):super().__init__();self.a=[];self.skip=0
 def handle_starttag(self,t,a):
  if t in ('script','style'):self.skip+=1
  elif t in ('p','h1','h2','h3','li','br','div'):self.a.append(' ')
 def handle_endtag(self,t):
  if t in ('script','style') and self.skip:self.skip-=1
  elif t in ('p','h1','h2','h3','li','div'):self.a.append(' ')
 def handle_data(self,d):
  if not self.skip:self.a.append(d)
def text(x):
 p=Text();p.feed(x or '');return re.sub(r'\s+',' ',' '.join(p.a)).strip()
def words(x):return re.findall(r'[\w\u200c]+',text(x),re.UNICODE)
def meta(item):
 d={}
 for m in item.findall('wp:postmeta',NS):
  k=m.findtext('wp:meta_key',default='',namespaces=NS);v=m.findtext('wp:meta_value',default='',namespaces=NS)
  d.setdefault(k,[]).append(v)
 return d
def first(d,*ks):
 for k in ks:
  if d.get(k) and d[k][0].strip():return d[k][0].strip()
 return ''
def items(path,ptype):
 root=ET.parse(path).getroot();out=[];attachments={}
 for i in root.find('channel').findall('item'):
  typ=i.findtext('wp:post_type',default='',namespaces=NS);m=meta(i);pid=i.findtext('wp:post_id',default='',namespaces=NS)
  if typ=='attachment':attachments[pid]={'title':i.findtext('title') or '','url':i.findtext('wp:attachment_url',default='',namespaces=NS),'alt':first(m,'_wp_attachment_image_alt')}
  if typ!=ptype:continue
  content=i.findtext('content:encoded',default='',namespaces=NS) or ''; status=i.findtext('wp:status',default='',namespaces=NS);title=i.findtext('title') or '';slug=i.findtext('wp:post_name',default='',namespaces=NS)
  cats=[{'domain':c.get('domain',''),'slug':c.get('nicename',''),'name':c.text or ''} for c in i.findall('category')]
  out.append({'id':int(pid or 0),'title':title,'slug':slug,'status':status,'date':i.findtext('wp:post_date',default='',namespaces=NS),'modified':i.findtext('wp:post_modified',default='',namespaces=NS),'author':i.findtext('dc:creator',default='',namespaces=NS),'content':content,'meta':m,'categories':cats,'link':i.findtext('link') or ''})
 return out,attachments
arts,att=items(ARTICLE_XML,'quantum_article');scis,satt=items(SCI_XML,'quantum_scientist')
urlrx=re.compile(r'https?://[^\s<"\']+')
for a in arts:
 c=a['content'];plain=text(c);all_links=re.findall(r'<a\b[^>]*href=["\']([^"\']+)',c,re.I);external=[u for u in all_links if urlparse(u).netloc and 'qpedia.ir' not in urlparse(u).netloc.lower()];internal=[u for u in all_links if not urlparse(u).netloc or 'qpedia.ir' in urlparse(u).netloc.lower()]
 sm=re.search(r'<h[1-6][^>]*>\s*منابع\s*</h[1-6]>',c,re.I);src=c[sm.end():] if sm else '';src_links=re.findall(r'<a\b[^>]*href=["\']([^"\']+)',src,re.I);src_items=len(re.findall(r'<li\b',src,re.I));bare=[u.rstrip('.,;)') for u in urlrx.findall(text(src))]
 faq_start=re.search(r'<h[1-6][^>]*>\s*پرسش(?:‌|\s)*های متداول\s*</h[1-6]>',c,re.I);pre_faq=c[:faq_start.start()] if faq_start else c;pre_src=pre_faq[:re.search(r'<h[1-6][^>]*>\s*منابع\s*</h[1-6]>',pre_faq,re.I).start()] if re.search(r'<h[1-6][^>]*>\s*منابع\s*</h[1-6]>',pre_faq,re.I) else pre_faq
 m=a['meta'];thumb=first(m,'_thumbnail_id');focus=first(m,'_qpedia_focus_keyphrase','_jetica_focus_keyword','rank_math_focus_keyword','_yoast_wpseo_focuskw');desc=first(m,'_qpedia_meta_description','_jetica_meta_description','rank_math_description','_yoast_wpseo_metadesc');seo=first(m,'_qpedia_seo_title','_jetica_seo_title','rank_math_title','_yoast_wpseo_title')
 faq_vis=len(re.findall(r'<p>\s*<strong>.*?</strong>\s*</p>\s*<p>.*?</p>',c,re.I|re.S));faq_schema=bool(first(m,'_qpedia_faq_schema','rank_math_schema_FAQPage'))
 a.update(word_count=len(words(c)),main_word_count=len(words(pre_src)),links=len(all_links),external_links=len(external),internal_links=len(internal),has_sources=bool(sm),source_links=len(src_links),source_items=src_items,bare_source_urls=len(bare),thumb_id=thumb,has_thumb=bool(thumb),thumb_alt=(att.get(thumb) or {}).get('alt',''),focus=focus,seo=seo,desc=desc,faq_visible=faq_vis,faq_schema=faq_schema,content_hash=hashlib.sha256(plain.encode()).hexdigest(),domains=Counter(urlparse(u).netloc.lower().removeprefix('www.') for u in external))
for s in scis:
 c=s['content'];m=s['meta'];thumb=first(m,'_thumbnail_id');links=re.findall(r'<a\b[^>]*href=["\']([^"\']+)',c,re.I);s.update(word_count=len(words(c)),links=len(links),external_links=sum(bool(urlparse(u).netloc and 'qpedia.ir' not in urlparse(u).netloc.lower()) for u in links),has_thumb=bool(thumb),thumb_alt=(satt.get(thumb) or {}).get('alt',''),en_name=first(m,'_scientist_en_name'),focus=first(m,'_qpedia_focus_keyphrase','_jetica_focus_keyword','rank_math_focus_keyword'),desc=first(m,'_qpedia_meta_description','_jetica_meta_description','rank_math_description','_yoast_wpseo_metadesc'))
# Explicit audit scales. These are project QA thresholds, not claims about scientific quality.
def traffic(a):
 red=[];yellow=[]
 if a['word_count']<500:red.append('کمتر از ۵۰۰ کلمه')
 elif a['word_count']<900:yellow.append('۵۰۰ تا ۸۹۹ کلمه')
 if not a['has_sources']:red.append('فاقد بخش منابع')
 elif a['source_links']==0:red.append('بخش منابع بدون لینک قابل‌کلیک')
 elif a['source_links']<3:yellow.append('کمتر از ۳ منبع لینک‌دار')
 if not a['has_thumb']:red.append('فاقد تصویر شاخص')
 elif not a['thumb_alt']:yellow.append('تصویر شاخص بدون ALT')
 if not a['desc']:red.append('فاقد توضیحات متا')
 if not a['focus']:yellow.append('فاقد کلیدواژه کانونی')
 if a['internal_links']<3:yellow.append('کمتر از ۳ لینک داخلی')
 return ('red',red+yellow) if red else (('yellow',yellow) if yellow else ('green',[]))
for a in arts:a['traffic'],a['issues']=traffic(a)
# output CSVs
article_cols=['id','status','title','slug','date','modified','word_count','main_word_count','has_sources','source_items','source_links','bare_source_urls','internal_links','external_links','has_thumb','thumb_alt','focus','seo','desc','faq_visible','faq_schema','traffic','issues']
with open(OUT/'articles-inventory.csv','w',encoding='utf-8-sig',newline='') as f:
 w=csv.DictWriter(f,fieldnames=article_cols);w.writeheader()
 for a in arts:w.writerow({k:(' | '.join(a[k]) if isinstance(a.get(k),list) else a.get(k,'')) for k in article_cols})
sci_cols=['id','status','title','slug','word_count','links','external_links','has_thumb','thumb_alt','en_name','focus','desc']
with open(OUT/'scientists-inventory.csv','w',encoding='utf-8-sig',newline='') as f:
 w=csv.DictWriter(f,fieldnames=sci_cols);w.writeheader();w.writerows({k:s.get(k,'') for k in sci_cols} for s in scis)
# statistics
pub=[a for a in arts if a['status']=='publish'];draft=[a for a in arts if a['status']=='draft'];valid=[a for a in arts if a['status'] in ('publish','draft')]
def med(v):return round(statistics.median(v),1) if v else 0
def avg(v):return round(statistics.mean(v),1) if v else 0
status=Counter(a['status'] for a in arts);trafficc=Counter(a['traffic'] for a in valid);wc=[a['word_count'] for a in valid]
bands=Counter('<500' if n<500 else '500–899' if n<900 else '900–1499' if n<1500 else '1500+' for n in wc)
focus=Counter(a['focus'] for a in valid if a['focus']);cats=Counter(c['name'] for a in valid for c in a['categories'] if c['domain']=='quantum_category');domains=Counter();[domains.update(a['domains']) for a in valid]
dups={h:v for h,v in {}.items()}
hashes={}
for a in valid:hashes.setdefault(a['content_hash'],[]).append(a)
dups={h:v for h,v in hashes.items() if len(v)>1 and text(v[0]['content'])}
summary={'articles_total':len(arts),'published':len(pub),'draft':len(draft),'trash':status['trash'],'scientists_total':len(scis),'scientists_published':sum(s['status']=='publish' for s in scis),'word_avg':avg(wc),'word_median':med(wc),'word_min':min(wc),'word_max':max(wc),'with_sources':sum(a['has_sources'] for a in valid),'with_source_links':sum(a['source_links']>0 for a in valid),'without_source_links':sum(a['source_links']==0 for a in valid),'with_3_source_links':sum(a['source_links']>=3 for a in valid),'featured':sum(a['has_thumb'] for a in valid),'featured_alt':sum(bool(a['thumb_alt']) for a in valid),'focus':sum(bool(a['focus']) for a in valid),'desc':sum(bool(a['desc']) for a in valid),'faq_schema':sum(a['faq_schema'] for a in valid),'traffic':dict(trafficc),'word_bands':dict(bands),'duplicate_groups':len(dups)}
(OUT/'summary.json').write_text(json.dumps(summary,ensure_ascii=False,indent=2))
# Markdown exact problem lists
def table(rows,cols):
 out=['| '+' | '.join(h for h,_ in cols)+' |','|'+'|'.join(['---']*len(cols))+'|']
 for r in rows:out.append('| '+' | '.join(str(fn(r)).replace('|','\\|').replace('\n',' ') for _,fn in cols)+' |')
 return '\n'.join(out)
def pct(n,d):return f'{n/d*100:.1f}٪' if d else '۰٪'
md=f'''# تحلیل کامل خروجی وردپرس Qpedia — ۲۰ سپتامبر ۲۰۲۶

## دامنه و روش

این گزارش فقط از دو فایل `WordPress.2026-09-20.xml` و `WordPress.2026-09-20 (1).xml` استخراج شده است. هیچ عددی از روی حافظه، حدس یا ظاهر سایت نوشته نشده است. «آنلاین» در این گزارش دقیقاً معادل `post_status=publish` در خروجی وردپرس است.

مقیاس چراغ راهنمای QA قراردادی و صریح است؛ سنجش کیفیت علمی نیست:
- قرمز: یکی از موارد بحرانیِ کمتر از ۵۰۰ کلمه، نبود بخش/لینک منابع، نبود تصویر شاخص یا نبود توضیحات متا.
- زرد: بدون ایراد قرمز، اما ۵۰۰–۸۹۹ کلمه، کمتر از سه منبع لینک‌دار، ALT/کلیدواژه کانونی ناقص یا کمتر از سه لینک داخلی.
- سبز: هیچ‌یک از شروط قرمز و زرد بالا را ندارد.

## خلاصه قطعی

- مقاله کوانتومی در فایل: **{len(arts)}**
- منتشرشده: **{len(pub)}** ({pct(len(pub),len(arts))})
- پیش‌نویس: **{len(draft)}** ({pct(len(draft),len(arts))})
- زباله‌دان: **{status['trash']}**
- دانشمند: **{len(scis)}**؛ منتشرشده: **{sum(s['status']=='publish' for s in scis)}**
- مقاله فعال منتشرشده+پیش‌نویس: **{len(valid)}**
- مقاله دارای دست‌کم یک لینک قابل‌کلیک در بخش منابع: **{sum(a['source_links']>0 for a in valid)}**
- مقاله بدون لینک قابل‌کلیک در بخش منابع: **{sum(a['source_links']==0 for a in valid)}**
- مقاله دارای حداقل ۳ منبع لینک‌دار: **{sum(a['source_links']>=3 for a in valid)}**
- میانگین حجم: **{avg(wc)} کلمه**؛ میانه: **{med(wc)}**؛ کمینه: **{min(wc)}**؛ بیشینه: **{max(wc)}**
- تصویر شاخص: **{sum(a['has_thumb'] for a in valid)}**؛ تصویر شاخص دارای ALT: **{sum(bool(a['thumb_alt']) for a in valid)}**
- توضیحات متا: **{sum(bool(a['desc']) for a in valid)}**؛ کلیدواژه کانونی: **{sum(bool(a['focus']) for a in valid)}**
- FAQ Schema: **{sum(a['faq_schema'] for a in valid)}**
- گروه محتوای دقیقاً تکراری براساس SHA-256 متن پاک‌شده: **{len(dups)}**

## تفکیک کامل منتشرشده و پیش‌نویس

| شاخص | منتشرشده ({len(pub)}) | پیش‌نویس ({len(draft)}) |
|---|---:|---:|
| دارای حداقل یک لینک منبع | {sum(a['source_links']>=1 for a in pub)} | {sum(a['source_links']>=1 for a in draft)} |
| دارای حداقل ۳ لینک منبع | {sum(a['source_links']>=3 for a in pub)} | {sum(a['source_links']>=3 for a in draft)} |
| بدون لینک قابل‌کلیک منابع | {sum(a['source_links']==0 for a in pub)} | {sum(a['source_links']==0 for a in draft)} |
| دارای تصویر شاخص | {sum(a['has_thumb'] for a in pub)} | {sum(a['has_thumb'] for a in draft)} |
| تصویر شاخص دارای ALT | {sum(bool(a['thumb_alt']) for a in pub)} | {sum(bool(a['thumb_alt']) for a in draft)} |
| دارای توضیحات متا | {sum(bool(a['desc']) for a in pub)} | {sum(bool(a['desc']) for a in draft)} |
| دارای کلیدواژه کانونی | {sum(bool(a['focus']) for a in pub)} | {sum(bool(a['focus']) for a in draft)} |
| دارای FAQ Schema | {sum(a['faq_schema'] for a in pub)} | {sum(a['faq_schema'] for a in draft)} |
| کمتر از ۵۰۰ کلمه | {sum(a['word_count']<500 for a in pub)} | {sum(a['word_count']<500 for a in draft)} |
| قرمز | {sum(a['traffic']=='red' for a in pub)} | {sum(a['traffic']=='red' for a in draft)} |
| زرد | {sum(a['traffic']=='yellow' for a in pub)} | {sum(a['traffic']=='yellow' for a in draft)} |
| سبز | {sum(a['traffic']=='green' for a in pub)} | {sum(a['traffic']=='green' for a in draft)} |

## توزیع وضعیت QA

- قرمز: **{trafficc['red']}**
- زرد: **{trafficc['yellow']}**
- سبز: **{trafficc['green']}**

## توزیع حجم

- کمتر از ۵۰۰ کلمه: **{bands['<500']}**
- ۵۰۰ تا ۸۹۹: **{bands['500–899']}**
- ۹۰۰ تا ۱۴۹۹: **{bands['900–1499']}**
- ۱۵۰۰ و بیشتر: **{bands['1500+']}**

## مقاله‌های بدون لینک قابل‌کلیک در بخش منابع

{table([a for a in valid if a['source_links']==0],[('ID',lambda a:a['id']),('وضعیت',lambda a:a['status']),('عنوان',lambda a:a['title']),('اسلاگ',lambda a:a['slug']),('کلمه',lambda a:a['word_count']),('بخش منابع',lambda a:'دارد' if a['has_sources'] else 'ندارد')])}

## مقاله‌های قرمز

{table([a for a in valid if a['traffic']=='red'],[('ID',lambda a:a['id']),('وضعیت',lambda a:a['status']),('عنوان',lambda a:a['title']),('کلمه',lambda a:a['word_count']),('ایراد دقیق',lambda a:'؛ '.join(a['issues']))])}

## مقاله‌های زرد

{table([a for a in valid if a['traffic']=='yellow'],[('ID',lambda a:a['id']),('وضعیت',lambda a:a['status']),('عنوان',lambda a:a['title']),('کلمه',lambda a:a['word_count']),('ایراد دقیق',lambda a:'؛ '.join(a['issues']))])}

## مقاله‌های سبز

{table([a for a in valid if a['traffic']=='green'],[('ID',lambda a:a['id']),('وضعیت',lambda a:a['status']),('عنوان',lambda a:a['title']),('کلمه',lambda a:a['word_count']),('منابع لینک‌دار',lambda a:a['source_links'])])}

## کلیدواژه‌های کانونی پرتکرار

{table([{'k':k,'n':n} for k,n in focus.most_common(40)],[('کلیدواژه',lambda x:x['k']),('تعداد',lambda x:x['n'])])}

## دسته‌بندی‌های پرتکرار

{table([{'k':k,'n':n} for k,n in cats.most_common()],[('دسته',lambda x:x['k']),('تعداد تخصیص',lambda x:x['n'])])}

## دامنه‌های خارجی پرتکرار در لینک‌های بدنه

{table([{'k':k,'n':n} for k,n in domains.most_common(30)],[('دامنه',lambda x:x['k']),('تعداد لینک',lambda x:x['n'])])}

## تحلیل بخش دانشمندان

- رکورد دانشمند: **{len(scis)}**
- منتشرشده: **{sum(s['status']=='publish' for s in scis)}**
- دارای تصویر شاخص: **{sum(s['has_thumb'] for s in scis)}**
- دارای ALT تصویر شاخص: **{sum(bool(s['thumb_alt']) for s in scis)}**
- دارای نام انگلیسی متادیتا: **{sum(bool(s['en_name']) for s in scis)}**
- دارای توضیحات متا: **{sum(bool(s['desc']) for s in scis)}**
- میانگین حجم متن: **{avg([s['word_count'] for s in scis])} کلمه**؛ میانه: **{med([s['word_count'] for s in scis])}**

### دانشمندان با داده ناقص

{table([s for s in scis if not s['has_thumb'] or not s['thumb_alt'] or not s['en_name'] or not s['desc']],[('ID',lambda s:s['id']),('نام',lambda s:s['title']),('کلمه',lambda s:s['word_count']),('ایراد دقیق',lambda s:'؛ '.join(([] if s['has_thumb'] else ['بدون تصویر'])+([] if s['thumb_alt'] else ['بدون ALT'])+([] if s['en_name'] else ['بدون نام انگلیسی متا'])+([] if s['desc'] else ['بدون توضیحات متا'])))])}

## راه‌حل‌های قطعی قابل خودکارسازی

1. منابع: برای هر مقاله بدون لینک، منبع باید از نسخه معتبر موجود یا صفحه ناشر استخراج و بخش منابع با تگ `a` بازسازی شود. افزودن لینک ساختگی ممنوع است. این کار برای منابعی که DOI/URL معتبر در خروجی یا مخزن دارند قطعی و خودکارشدنی است.
2. ALT تصویر: ALT خالی را می‌توان از عنوان دقیق مقاله/دانشمند و محتوای واقعی تصویر ساخت؛ برای ۵۶ تصویر دانشمندِ بدون ALT، بدون مشاهده تصویر نمی‌توان توصیف بصری دقیق نوشت. استفاده صرف از نام فرد ممکن است، اما توصیف تصویر نیست.
3. متادیتای SEO: توضیحات متای خالی را می‌توان از متن واقعی همان نوشته با محدودیت طول تولید و سپس جداگانه کنترل کرد.
4. کلیدواژه: کلیدواژه خالی را می‌توان فقط از عنوان و موضوع واقعی نوشته استخراج کرد؛ مترادف‌های تجاری یا حجم جست‌وجو از این دو فایل قابل تعیین نیست.
5. حجم مقاله: مقاله‌های زیر ۵۰۰ و ۹۰۰ کلمه باید با محتوای علمی واقعی گسترش یابند، نه با تکرار. اولویت قطعی: منتشرشده‌های قرمز، سپس پیش‌نویس‌های قرمز، سپس زردها.
6. لینک داخلی: برای نوشته‌های زیر ۳ لینک داخلی می‌توان گراف موضوعی براساس slug، دسته و کلیدواژه ساخت و فقط مقصدهای موجود در همین خروجی را پیشنهاد یا درج کرد.
7. FAQ Schema: برای مقاله دارای FAQ قابل‌مشاهده و بدون Schema می‌توان Schema را عیناً از همان سؤال/پاسخ ساخت؛ اگر FAQ قابل‌مشاهده نیست، ساخت Schema جداگانه مجاز نیست.
8. محتوای تکراری: گروه‌های hash یکسان باید دستی از نظر هدف URL بررسی شوند؛ حذف یا canonical بدون دیدن قصد انتشار قطعی نیست.

## مواردی که از این فایل‌ها نمی‌دانم

- درست یا فعال‌بودن HTTP همه لینک‌های خارجی در لحظه مشاهده؛ خروجی XML فقط URL را نشان می‌دهد و آزمون شبکه جدا لازم است.
- تعداد بازدید، نرخ کلیک، رتبه گوگل، impression و conversion؛ داده Analytics/Search Console در دو فایل وجود ندارد.
- حجم جست‌وجوی کلیدواژه‌ها و سختی SEO؛ این داده در WXR نیست.
- اینکه مقاله پیش‌نویس دقیقاً چه زمانی قرار است منتشر شود.
- کیفیت بصری واقعی هر تصویر و انطباق آن با چهره دانشمند بدون بازبینی خود فایل تصویر.
- صحت علمی نهایی تمام ۳۱۱ مقاله؛ این گزارش ساختاری و محتوایی کمّی است، نه peer review تک‌تک گزاره‌ها.
- علت هر رکورد ناقص؛ فقط می‌توان وجود یا نبود داده را قطعی گزارش کرد.

## فایل‌های مکمل

- `articles-inventory.csv`: ریز کامل ۳۱۱ مقاله
- `scientists-inventory.csv`: ریز کامل ۵۶ دانشمند
- `summary.json`: خلاصه ماشین‌خوان
- `dashboard.html`: داشبورد نموداری مستقل
'''
(OUT/'report.md').write_text(md)
# HTML dashboard charts via CSS (no external libs)
def bar(label,n,total,color):return f'<div class="bar"><span>{html.escape(label)}</span><i><b style="width:{(n/total*100 if total else 0):.2f}%;background:{color}"></b></i><strong>{n} <small>({pct(n,total)})</small></strong></div>'
def rows_problem(xs,limit=30):return ''.join(f'<tr><td>{a["id"]}</td><td>{html.escape(a["title"])}</td><td>{a["word_count"]}</td><td>{html.escape("؛ ".join(a["issues"]))}</td></tr>' for a in xs[:limit])
htmlout=f'''<!doctype html><html lang="fa" dir="rtl"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>داشبورد تحلیل Qpedia</title><style>*{{box-sizing:border-box}}body{{margin:0;background:#09111f;color:#e8f1fb;font-family:system-ui,sans-serif;line-height:1.7}}main{{max-width:1280px;margin:auto;padding:35px 18px}}h1{{font-size:clamp(28px,5vw,52px);margin:0}}.sub{{color:#91a4b9}}.grid{{display:grid;grid-template-columns:repeat(4,1fr);gap:12px;margin:25px 0}}.card,.panel{{background:#101c2d;border:1px solid #21334a;border-radius:16px;padding:20px}}.card b{{font-size:34px;color:#7ce8ff;display:block}}.card span{{font-size:12px;color:#91a4b9}}.cols{{display:grid;grid-template-columns:1fr 1fr;gap:14px;margin:14px 0}}h2{{font-size:19px;margin:0 0 18px}}.bar{{display:grid;grid-template-columns:115px 1fr 110px;gap:10px;align-items:center;margin:11px 0;font-size:12px}}.bar i{{height:12px;background:#1a293d;border-radius:99px;overflow:hidden}}.bar b{{display:block;height:100%;border-radius:99px}}small{{color:#8295a9}}table{{width:100%;border-collapse:collapse;font-size:12px}}th,td{{text-align:right;padding:9px;border-bottom:1px solid #203149}}th{{color:#7ce8ff}}.red{{color:#ff7b83}}.yellow{{color:#ffd166}}.green{{color:#63e6a5}}.note{{border-right:3px solid #ffd166;padding:10px 14px;background:#191d24;color:#d9c58d}}a{{color:#7ce8ff}}@media(max-width:900px){{.grid{{grid-template-columns:1fr 1fr}}.cols{{grid-template-columns:1fr}}}}@media(max-width:520px){{.grid{{grid-template-columns:1fr}}.bar{{grid-template-columns:85px 1fr 70px}}}}</style></head><body><main><h1>تحلیل کامل Qpedia</h1><p class="sub">مبنای تحلیل: دو خروجی WordPress مورخ ۲۰ سپتامبر ۲۰۲۶؛ بدون داده حدسی</p><div class="grid"><div class="card"><b>{len(pub)}</b><span>مقاله منتشرشده</span></div><div class="card"><b>{len(draft)}</b><span>پیش‌نویس</span></div><div class="card"><b>{sum(a['source_links']>0 for a in valid)}</b><span>دارای لینک منابع</span></div><div class="card"><b>{len(scis)}</b><span>دانشمند منتشرشده</span></div></div><div class="cols"><section class="panel"><h2>وضعیت انتشار مقاله‌ها</h2>{bar('منتشرشده',len(pub),len(arts),'#63e6a5')}{bar('پیش‌نویس',len(draft),len(arts),'#ffd166')}{bar('زباله‌دان',status['trash'],len(arts),'#ff7b83')}</section><section class="panel"><h2>چراغ راهنمای QA</h2>{bar('سبز',trafficc['green'],len(valid),'#63e6a5')}{bar('زرد',trafficc['yellow'],len(valid),'#ffd166')}{bar('قرمز',trafficc['red'],len(valid),'#ff7b83')}</section><section class="panel"><h2>حجم مقاله‌ها</h2>{bar('زیر ۵۰۰',bands['<500'],len(valid),'#ff7b83')}{bar('۵۰۰–۸۹۹',bands['500–899'],len(valid),'#ffd166')}{bar('۹۰۰–۱۴۹۹',bands['900–1499'],len(valid),'#5ab5ff')}{bar('۱۵۰۰+',bands['1500+'],len(valid),'#63e6a5')}</section><section class="panel"><h2>کامل‌بودن داده</h2>{bar('لینک منابع',sum(a['source_links']>0 for a in valid),len(valid),'#63e6a5')}{bar('تصویر شاخص',sum(a['has_thumb'] for a in valid),len(valid),'#5ab5ff')}{bar('توضیح متا',sum(bool(a['desc']) for a in valid),len(valid),'#a78bfa')}{bar('کلیدواژه',sum(bool(a['focus']) for a in valid),len(valid),'#7ce8ff')}</section></div><section class="panel"><h2 class="red">نمونه موارد قرمز — فهرست کامل در report.md و CSV</h2><table><thead><tr><th>ID</th><th>عنوان</th><th>کلمه</th><th>ایراد دقیق</th></tr></thead><tbody>{rows_problem([a for a in valid if a['traffic']=='red'])}</tbody></table></section><p class="note">این چراغ راهنما براساس آستانه‌های صریح QA است و درباره صحت علمی مقاله حکم نمی‌دهد. جزئیات کامل، موارد نامعلوم و راه‌حل‌ها در report.md ثبت شده‌اند.</p></main></body></html>'''
(OUT/'dashboard.html').write_text(htmlout)
print(json.dumps(summary,ensure_ascii=False,indent=2))
