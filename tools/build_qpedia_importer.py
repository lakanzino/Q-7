#!/usr/bin/env python3
"""Build the one-use Qpedia WordPress draft importer from reviewed Markdown."""
from pathlib import Path
import html, json, re, shutil, zipfile

ROOT = Path(__file__).resolve().parents[1]
BUILD = ROOT / "qpedia-five-article-importer"
OUT = ROOT / "qpedia-five-article-importer.zip"

CONFIG = [
    dict(file="01-quantum-illumination.md", title="تصویربرداری کوانتومی در تاریکی؛ چگونه هدف پنهان در نویز را پیدا کنیم؟", slug="quantum-illumination", seo="تصویربرداری کوانتومی در تاریکی؛ تشخیص هدف در نویز", desc="تابش کوانتومی با مقایسه سیگنال بازگشتی و فوتون مرجع، هدف کم‌بازتاب را در نویز تشخیص می‌دهد؛ سازوکار، آزمایش‌ها و محدودیت‌هایش را بخوانید.", focus="تابش کوانتومی", secondary="تصویربرداری کوانتومی در تاریکی، تشخیص هدف کوانتومی، فوتون درهم‌تنیده، حسگری کوانتومی، رادار کوانتومی", excerpt="تابش کوانتومی روشی برای تشخیص حضور یک جسم کم‌بازتاب در محیط پرنویز است که هم‌بستگی میان سیگنال ارسالی و مرجع نگه‌داشته‌شده را به کار می‌گیرد.", tags=["اپتیک کوانتومی","درهم‌تنیدگی","فوتون","حسگر کوانتومی","تصویربرداری کوانتومی"], image="quantum-illumination.webp", alt="نمودار تصویربرداری کوانتومی با فوتون‌های درهم‌تنیده برای یافتن هدف پنهان در محیط پرنویز", caption="سازوکار مفهومی تصویربرداری کوانتومی برای جداسازی بازتاب هدف از نویز پس‌زمینه."),
    dict(file="02-quantum-lidar.md", title="لیدار کوانتومی چیست و چگونه فاصله را با فوتون‌ها اندازه می‌گیرد؟", slug="quantum-lidar", seo="لیدار کوانتومی چیست؟ سازوکار، کاربرد و محدودیت‌ها", desc="لیدار کوانتومی با فوتون‌های منفرد، هم‌بستگی یا نور فشرده فاصله و سرعت هدف را می‌سنجد؛ تفاوت آن با لیدار کلاسیک و محدودیت‌های واقعی را بخوانید.", focus="لیدار کوانتومی", secondary="فاصله‌یابی کوانتومی، لیدار تک‌فوتونی، حسگر کوانتومی، تصویربرداری سه‌بعدی، تشخیص هدف", excerpt="لیدار کوانتومی خانواده‌ای از روش‌های فاصله‌یابی نوری است که از آشکارسازی تک‌فوتونی یا منابع و هم‌بستگی‌های غیرکلاسیک برای اندازه‌گیری هدف استفاده می‌کند.", tags=["لیدار","فوتون","اپتیک کوانتومی","فاصله‌یابی","حسگر کوانتومی"], image="quantum-lidar.webp", alt="نمودار لیدار کوانتومی و محاسبه فاصله هدف از زمان رفت‌وبرگشت فوتون‌ها", caption="لیدار کوانتومی فاصله را با اندازه‌گیری زمان رفت‌وبرگشت پالس‌های نوری برآورد می‌کند."),
    dict(file="03-single-photon-source.md", title="چشمهٔ تک‌فوتون چیست و چرا ساخت یک فوتونِ مطمئن دشوار است؟", slug="single-photon-source", seo="چشمهٔ تک‌فوتون چیست؟ روش تولید و کاربردها", desc="چشمهٔ تک‌فوتون چگونه هر بار فقط یک فوتون می‌سازد؟ با سازوکار نقاط کوانتومی، منابع بشارت‌داده‌شده، معیارهای کیفیت و محدودیت‌ها آشنا شوید.", focus="چشمهٔ تک‌فوتون", secondary="منبع تک‌فوتونی، فوتون منفرد، نقطه کوانتومی، آنتی‌بانچینگ، فوتون بشارت‌داده‌شده", excerpt="چشمهٔ تک‌فوتون دستگاهی است که در هر چرخه زمانی، با احتمال زیاد یک فوتون و با احتمال بسیار کم دو یا چند فوتون تحویل می‌دهد.", tags=["فوتون","اپتیک کوانتومی","نقطه کوانتومی","رمزنگاری کوانتومی","رایانش فوتونی"], image="single-photon-source.webp", alt="نمودار چشمه تک‌فوتون و چالش تولید یک فوتون خالص و قابل‌اعتماد", caption="چشمه تک‌فوتون باید احتمال پالس‌های چندفوتونی و نویز را تا حد ممکن کاهش دهد."),
    dict(file="04-entangled-photon-source.md", title="چشمهٔ فوتون درهم‌تنیده چگونه جفت‌های کوانتومی تولید می‌کند؟", slug="entangled-photon-source", seo="چشمهٔ فوتون درهم‌تنیده چیست و چگونه کار می‌کند؟", desc="چشمهٔ فوتون درهم‌تنیده چگونه جفت‌فوتون‌های هم‌بسته می‌سازد؟ سازوکار SPDC، انواع درهم‌تنیدگی، آزمون کیفیت و کاربردهای واقعی را بخوانید.", focus="چشمهٔ فوتون درهم‌تنیده", secondary="جفت‌فوتون درهم‌تنیده، منبع فوتون درهم‌تنیده، SPDC، درهم‌تنیدگی قطبشی، اپتیک کوانتومی", excerpt="چشمهٔ فوتون درهم‌تنیده دو فوتون را در حالتی مشترک تولید می‌کند که ویژگی‌های آن‌ها را نمی‌توان به دو حالت مستقل فروکاست.", tags=["درهم‌تنیدگی","فوتون","اپتیک کوانتومی","رمزنگاری کوانتومی","SPDC"], image="entangled-photon-source.webp", alt="نمودار تولید جفت‌فوتون درهم‌تنیده با لیزر پمپ و بلور غیرخطی", caption="طرح مفهومی تولید و اندازه‌گیری جفت‌فوتون‌های درهم‌تنیده."),
    dict(file="05-spontaneous-parametric-down-conversion.md", title="تبدیل پارامتری خودبه‌خودی چیست؟ سازوکار SPDC برای تولید جفت‌فوتون", slug="spontaneous-parametric-down-conversion", seo="تبدیل پارامتری خودبه‌خودی (SPDC) چیست؟", desc="SPDC چگونه در یک بلور غیرخطی جفت‌فوتون تولید می‌کند؟ شرط‌های انرژی و تطبیق فاز، انواع فرایند، کاربردها و محدودیت‌های آن را بخوانید.", focus="تبدیل پارامتری خودبه‌خودی", secondary="SPDC، تولید جفت‌فوتون، بلور غیرخطی، تطبیق فاز، فوتون سیگنال و آیدلر", excerpt="SPDC فرایندی غیرخطی و احتمالاتی است که در آن میدان پمپ در یک بلور، جفت‌فوتون‌های سیگنال و آیدلر با انرژی و تکانه سازگار تولید می‌کند.", tags=["اپتیک غیرخطی","فوتون","درهم‌تنیدگی","چشمه تک‌فوتون","بلور غیرخطی"], image="spontaneous-parametric-down-conversion.webp", alt="نمودار فرایند SPDC و تولید فوتون‌های سیگنال و آیدلر در بلور غیرخطی", caption="در تبدیل پارامتری خودبه‌خودی، میدان پمپ در بلور غیرخطی جفت‌فوتون تولید می‌کند."),
]

def inline(s):
    s = re.sub(r'\[([^]]+)\]\((https?://[^)]+)\)', r'<a href="\2">\1</a>', s)
    s = re.sub(r'\*\*([^*]+)\*\*', r'<strong>\1</strong>', s)
    s = re.sub(r'`([^`]+)`', r'<code>\1</code>', s)
    return s

def md_to_html(text):
    # Publishable content begins after the sole H1; WordPress template renders title.
    text = text.split('\n# ', 1)[1]
    text = text.split('\n', 1)[1]
    lines = text.splitlines(); out=[]; para=[]; ul=False; i=0
    def flush():
        nonlocal para
        if para:
            out.append('<p>'+inline(' '.join(x.strip() for x in para))+'</p>'); para=[]
    while i < len(lines):
        line=lines[i]
        if line.startswith(('<aside','<div','<style','<p style')):
            flush()
            tag=re.match(r'<(\w+)',line).group(1); block=[line]
            if f'</{tag}>' not in line:
                i+=1
                while i<len(lines):
                    block.append(lines[i])
                    if f'</{tag}>' in lines[i]: break
                    i+=1
            out.append('\n'.join(block)); i+=1; continue
        if not line.strip():
            flush()
            if ul: out.append('</ul>'); ul=False
            i+=1; continue
        m=re.match(r'^(#{2,4})\s+(.+)',line)
        if m:
            flush()
            if ul: out.append('</ul>'); ul=False
            level=len(m.group(1)); out.append(f'<h{level}>{inline(m.group(2))}</h{level}>'); i+=1; continue
        if line.startswith('- '):
            flush()
            if not ul: out.append('<ul>'); ul=True
            out.append('<li>'+inline(line[2:])+'</li>'); i+=1; continue
        if re.match(r'^\d+\.\s',line):
            flush(); items=[]
            while i<len(lines) and re.match(r'^\d+\.\s',lines[i]):
                items.append(re.sub(r'^\d+\.\s*','',lines[i])); i+=1
            out.append('<ol>'+''.join('<li>'+inline(x)+'</li>' for x in items)+'</ol>'); continue
        para.append(line); i+=1
    flush()
    if ul: out.append('</ul>')
    return '\n'.join(out)

if BUILD.exists(): shutil.rmtree(BUILD)
(BUILD/'assets').mkdir(parents=True)
articles=[]
for cfg in CONFIG:
    raw=(ROOT/'articles'/cfg['file']).read_text()
    content=md_to_html(raw)
    faqs=[dict(question=re.sub('<[^>]+>','',q).strip(), answer=re.sub('<[^>]+>','',a).strip()) for q,a in re.findall(r'<summary>(.*?)</summary><div class="answer">(.*?)</div>',content,re.S)]
    row={**cfg, 'content':content, 'faqs':faqs}
    del row['file']
    articles.append(row)
    shutil.copy2(ROOT/'featured-images'/cfg['image'], BUILD/'assets'/cfg['image'])
(BUILD/'articles.json').write_text(json.dumps(articles,ensure_ascii=False,indent=2))

plugin=r'''<?php
/**
 * Plugin Name: Qpedia – درون‌ریز یک‌بارمصرف پنج مقاله
 * Description: پنج مقاله بررسی‌شده Qpedia را همراه تصویر شاخص، طبقه‌بندی، متادیتای سئو و FAQ Schema به‌صورت پیش‌نویس وارد می‌کند.
 * Version: 1.0.0
 * Author: Qpedia Editorial
 */
if (!defined('ABSPATH')) exit;

add_action('admin_menu', function () {
    add_management_page('درون‌ریزی پنج مقاله Qpedia','درون‌ریز Qpedia','manage_options','qpedia-five-import','qp5_page');
});

function qp5_page() {
    if (!current_user_can('manage_options')) return;
    echo '<div class="wrap" dir="rtl"><h1>درون‌ریزی پنج مقاله Qpedia</h1>';
    if (!empty($_POST['qp5_import'])) {
        check_admin_referer('qp5_import_action');
        $results = qp5_import_all();
        echo '<div class="notice notice-success"><p><strong>عملیات تمام شد.</strong></p><ul>';
        foreach ($results as $result) echo '<li>'.esc_html($result).'</li>';
        echo '</ul></div>';
    }
    echo '<p>مقاله‌ها فقط به‌صورت <strong>پیش‌نویس</strong> ساخته می‌شوند. اجرای دوباره، مقاله موجود با همان اسلاگ را تکثیر نمی‌کند.</p>';
    echo '<form method="post">'; wp_nonce_field('qp5_import_action');
    submit_button('ساخت پنج پیش‌نویس','primary','qp5_import'); echo '</form></div>';
}

function qp5_import_all() {
    $json = file_get_contents(plugin_dir_path(__FILE__).'articles.json');
    $items = json_decode($json, true);
    if (!is_array($items)) return array('خطا: فایل داده خوانده نشد.');
    $results = array();
    foreach ($items as $item) {
        $existing = get_page_by_path($item['slug'], OBJECT, 'quantum_article');
        if ($existing) { $results[] = 'رد شد (از قبل موجود): '.$item['title']; continue; }
        $post_id = wp_insert_post(array(
            'post_type'=>'quantum_article','post_status'=>'draft','post_title'=>$item['title'],
            'post_name'=>$item['slug'],'post_content'=>$item['content'],'post_excerpt'=>$item['excerpt'],
            'post_author'=>get_current_user_id(),'comment_status'=>'open'
        ), true);
        if (is_wp_error($post_id)) { $results[]='خطا در '.$item['title'].': '.$post_id->get_error_message(); continue; }

        if (taxonomy_exists('quantum_category')) {
            $term = term_exists('technology','quantum_category');
            if (!$term) $term = wp_insert_term('فناوری و کاربردهای کوانتومی','quantum_category',array('slug'=>'technology'));
            if (!is_wp_error($term)) wp_set_object_terms($post_id,array((int)(is_array($term)?$term['term_id']:$term)),'quantum_category');
        }
        wp_set_post_tags($post_id,$item['tags'],false);
        $metas = array(
            '_qpedia_seo_title'=>$item['seo'],'_qpedia_meta_description'=>$item['desc'],
            '_qpedia_focus_keyphrase'=>$item['focus'],'_qpedia_secondary_keyphrases'=>$item['secondary'],
            '_qpedia_tags'=>implode('، ',$item['tags']),'_jetica_seo_title'=>$item['seo'],
            '_jetica_meta_description'=>$item['desc'],'_jetica_focus_keyword'=>$item['focus'],
            '_jetica_schema_enabled'=>'1','_jetica_schema_type'=>'Article',
            'rank_math_title'=>$item['seo'],'rank_math_description'=>$item['desc'],'rank_math_focus_keyword'=>$item['focus'],
            '_yoast_wpseo_title'=>$item['seo'],'_yoast_wpseo_metadesc'=>$item['desc'],'_yoast_wpseo_focuskw'=>$item['focus']
        );
        foreach ($metas as $key=>$value) update_post_meta($post_id,$key,$value);

        $entities=array();
        foreach ($item['faqs'] as $faq) $entities[]=array('@type'=>'Question','name'=>$faq['question'],'acceptedAnswer'=>array('@type'=>'Answer','text'=>$faq['answer']));
        update_post_meta($post_id,'rank_math_schema_FAQPage',array('@context'=>'https://schema.org','@type'=>'FAQPage','mainEntity'=>$entities));
        update_post_meta($post_id,'_qpedia_faq_schema',wp_json_encode(array('@context'=>'https://schema.org','@type'=>'FAQPage','mainEntity'=>$entities),JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES));

        $attachment_id = qp5_attach_image($post_id,$item);
        if (is_wp_error($attachment_id)) $results[]='ساخته شد، اما خطای تصویر برای '.$item['title'].': '.$attachment_id->get_error_message();
        else { set_post_thumbnail($post_id,$attachment_id); $results[]='پیش‌نویس ساخته شد: '.$item['title']; }
    }
    return $results;
}

function qp5_attach_image($post_id,$item) {
    $path=plugin_dir_path(__FILE__).'assets/'.$item['image'];
    if (!file_exists($path)) return new WP_Error('missing_image','فایل تصویر پیدا نشد.');
    $bits=wp_upload_bits($item['image'],null,file_get_contents($path));
    if (!empty($bits['error'])) return new WP_Error('upload_error',$bits['error']);
    $type=wp_check_filetype($bits['file'],null);
    $id=wp_insert_attachment(array('post_mime_type'=>$type['type'],'post_title'=>pathinfo($item['image'],PATHINFO_FILENAME),'post_excerpt'=>$item['caption'],'post_content'=>'','post_status'=>'inherit'),$bits['file'],$post_id,true);
    if (is_wp_error($id)) return $id;
    require_once ABSPATH.'wp-admin/includes/image.php';
    $meta=wp_generate_attachment_metadata($id,$bits['file']);
    if ($meta) wp_update_attachment_metadata($id,$meta);
    update_post_meta($id,'_wp_attachment_image_alt',$item['alt']);
    return $id;
}
'''
(BUILD/'qpedia-five-article-importer.php').write_text(plugin)
(BUILD/'README.txt').write_text('''Qpedia five-article one-use importer\n\n1. افزونه را نصب و فعال کنید.\n2. از ابزارها ← درون‌ریز Qpedia وارد شوید.\n3. دکمه «ساخت پنج پیش‌نویس» را یک بار بزنید.\n4. پیش‌نویس‌ها، تصاویر شاخص، ALT، کپشن، دسته، برچسب و متادیتای SEO را بررسی کنید.\n5. پس از اطمینان از درون‌ریزی، افزونه را غیرفعال و حذف کنید.\n\nافزونه هرگز مقاله‌ای را منتشر نمی‌کند و در اجرای مجدد، اسلاگ موجود را تکثیر نمی‌کند.\n''')
if OUT.exists(): OUT.unlink()
with zipfile.ZipFile(OUT,'w',zipfile.ZIP_DEFLATED) as z:
    for p in BUILD.rglob('*'):
        if p.is_file(): z.write(p,p.relative_to(BUILD.parent))
print(OUT)
