#!/usr/bin/env python3
"""Build importer plugin for incomplete-depth-second-10."""
import json, os, zipfile
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
BASE = ROOT / 'article-rewrite-2026-09-22'
ART_DIR = BASE / 'articles'
IMP_DIR = BASE / 'importers' / 'incomplete-depth-second-10'
PLUGIN_DIR = IMP_DIR / 'qpedia-importer-incomplete-depth-second-10'
PLUGIN_DIR.mkdir(parents=True, exist_ok=True)

folders = [
    '107-is-many-worlds-real',
    '108-quantum-career-future-learn',
    '109-heisenberg-uncertainty-principle',
    '110-why-large-objects-dont-superpose',
    '111-determinism-vs-probability',
    '113-quantum-physics-vs-quantum-mechanics',
    '114-coin-vs-dice-quantum-uncertainty',
    '115-quantum-analogy-exercise-boundary',
    '116-quantum-understanding-achievement',
    '117-quantum-number'
]

articles = []
for f in folders:
    p = ART_DIR / f
    meta = json.loads((p / 'metadata.json').read_text(encoding='utf-8'))
    html = (p / 'article.html').read_text(encoding='utf-8')
    articles.append({
        "sequence": meta["sequence"],
        "post_id": meta["post_id"],
        "slug": meta["slug"],
        "title": meta["title"],
        "seo_title": meta["seo_title"],
        "description": meta["description"],
        "focus_keyword": meta["focus_keyword"],
        "taxonomy": meta["taxonomy"],
        "faqs": meta["faqs"],
        "content": html
    })

(PLUGIN_DIR / 'articles.json').write_text(json.dumps(articles, ensure_ascii=False, indent=2), encoding='utf-8')

php_code = """<?php
/**
 * Plugin Name: Qpedia One-Time Importer Incomplete Depth Second 10
 * Description: به‌روزرسانی کنترل‌شده ده مقاله دوم گروه ناقص Qpedia با SEO (سازگار با Rank Math و TamRank)، دسته‌بندی و FAQ Schema.
 * Version: 1.0.0
 * Author: Qpedia Editorial
 */

defined('ABSPATH') || exit;

const QPID10_2_OPTION = 'qpedia_importer_incomplete_depth_second_10_completed';

add_action('admin_menu', function() {
    add_management_page(
        'درون‌ریز ده مقاله دوم Qpedia',
        'درون‌ریز ده مقاله دوم Qpedia',
        'manage_options',
        'qpedia-importer-incomplete-depth-second-10',
        'qpid10_2_page'
    );
});

function qpid10_2_page() {
    if (!current_user_can('manage_options')) return;
    $done = get_option(QPID10_2_OPTION);
    echo '<div class="wrap" dir="rtl"><h1>درون‌ریز ده مقاله دوم گروه ناقص بازنویسی‌شده Qpedia</h1>';
    
    if (isset($_POST['qpid10_2_run'])) {
        check_admin_referer('qpid10_2_run');
        $r = qpid10_2_run();
        $bad = false;
        foreach ($r as $x) if (!$x['ok']) $bad = true;
        echo '<div class="notice ' . ($bad ? 'notice-warning' : 'notice-success') . '"><ul>';
        foreach ($r as $x) echo '<li>' . esc_html($x['message']) . '</li>';
        echo '</ul></div>';
        if (!$bad) {
            update_option(QPID10_2_OPTION, current_time('mysql'), false);
            echo '<p><strong>هر ده مقاله با موفقیت به‌روزرسانی شدند؛ اکنون می‌توانید افزونه را غیرفعال و حذف کنید.</strong></p></div>';
            return;
        }
    }

    if ($done) {
        echo '<div class="notice notice-info"><p>این بسته قبلاً در ' . esc_html($done) . ' اجرا شده است. برای اجرای مجدد دکمه زیر را بزنید.</p></div>';
    }

    echo '<p>این افزونه ده مقاله موجود را با ID یا slug به‌روزرسانی می‌کند. کلیدواژه‌ها، عنوان و توضیحات متا برای TamRank SEO و Rank Math، دسته‌بندی‌ها، اسکیما و متن کامل ثبت می‌شوند. تاریخ انتشار قدیمی، نویسنده و تصویر شاخص تغییر نمی‌کنند.</p>';
    echo '<form method="post">';
    wp_nonce_field('qpid10_2_run');
    submit_button('اجرای به‌روزرسانی ده مقاله', 'primary', 'qpid10_2_run');
    echo '</form></div>';
}

function qpid10_2_run() {
    $file = plugin_dir_path(__FILE__) . 'articles.json';
    if (!file_exists($file)) return [['ok' => false, 'message' => 'فایل articles.json پیدا نشد.']];
    $items = json_decode(file_get_contents($file), true);
    if (!is_array($items) || count($items) !== 10) return [['ok' => false, 'message' => 'فایل داده معتبر نیست؛ انتظار ۱۰ مقاله است.']];

    $r = [];
    foreach ($items as $i) {
        $p = get_post((int)$i['post_id']);
        if (!$p) {
            $p = get_page_by_path(sanitize_title($i['slug']), OBJECT, 'quantum_article');
        }
        if (!$p) {
            $p = get_page_by_path(sanitize_title($i['slug']), OBJECT, 'post');
        }
        if (!$p) {
            $r[] = ['ok' => false, 'message' => 'پیدا نشد: ' . $i['title'] . ' (ID: ' . $i['post_id'] . ')'];
            continue;
        }

        $u = wp_update_post([
            'ID'           => $p->ID,
            'post_title'   => $i['title'],
            'post_name'    => $i['slug'],
            'post_content' => $i['content']
        ], true);

        if (is_wp_error($u)) {
            $r[] = ['ok' => false, 'message' => $i['title'] . ': ' . $u->get_error_message()];
            continue;
        }

        $meta = [
            // Rank Math
            'rank_math_focus_keyword'   => $i['focus_keyword'],
            'rank_math_title'           => $i['seo_title'],
            'rank_math_description'     => $i['description'],
            // TamRank SEO
            '_tam_rank_focus_keyword'   => $i['focus_keyword'],
            '_tam_rank_meta_title'      => $i['seo_title'],
            '_tam_rank_meta_description'=> $i['description'],
            // Theme and Yoast compatibility
            '_qpedia_focus_keyphrase'   => $i['focus_keyword'],
            '_qpedia_seo_title'         => $i['seo_title'],
            '_qpedia_meta_description'  => $i['description'],
            '_yoast_wpseo_focuskw'      => $i['focus_keyword'],
            '_yoast_wpseo_title'        => $i['seo_title'],
            '_yoast_wpseo_metadesc'     => $i['description']
        ];

        foreach ($meta as $k => $v) {
            update_post_meta($p->ID, $k, $v);
        }

        // Taxonomy
        $t = $i['taxonomy'];
        $tax_name = taxonomy_exists('quantum_category') ? 'quantum_category' : 'category';
        
        $m = term_exists($t['mother_slug'], $tax_name);
        if (!$m) $m = wp_insert_term($t['mother_name'], $tax_name, ['slug' => $t['mother_slug']]);
        $mid = is_array($m) ? (int)$m['term_id'] : (int)$m;

        $c = term_exists($t['category_slug'], $tax_name);
        if (!$c) $c = wp_insert_term($t['category_name'], $tax_name, ['slug' => $t['category_slug'], 'parent' => $mid]);
        $cid = is_array($c) ? (int)$c['term_id'] : (int)$c;

        if ($mid && $cid) {
            wp_set_object_terms($p->ID, [$mid, $cid], $tax_name, false);
        }

        // FAQ Schema
        $e = [];
        foreach ($i['faqs'] as $f) {
            $e[] = [
                '@type'          => 'Question',
                'name'           => $f['question'],
                'acceptedAnswer' => [
                    '@type' => 'Answer',
                    'text'  => $f['answer']
                ]
            ];
        }
        $schema = [
            '@context'   => 'https://schema.org',
            '@type'      => 'FAQPage',
            'mainEntity' => $e
        ];
        update_post_meta($p->ID, 'rank_math_schema_FAQPage', $schema);
        update_post_meta($p->ID, '_qpedia_faq_schema', wp_json_encode($schema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

        clean_post_cache($p->ID);
        $r[] = ['ok' => true, 'message' => 'به‌روزرسانی شد: ' . $i['title'] . ' (ID: ' . $p->ID . ')'];
    }

    return $r;
}
"""

(PLUGIN_DIR / 'qpedia-importer-incomplete-depth-second-10.php').write_text(php_code.strip(), encoding='utf-8')

readme = """=== Qpedia One-Time Importer Incomplete Depth Second 10 ===
Contributors: Qpedia Editorial
Tags: importer, quantum, seo, tamrank
Requires at least: 5.8
Tested up to: 6.7
Stable tag: 1.0.0
License: GPLv2 or later

== Description ==
افزونه یک‌بارمصرف برای به‌روزرسانی ۱۰ مقاله دوم گروه ناقص Qpedia (شامل مقاله‌های ۱۰۷ تا ۱۱۷) با متادیتای سئو، کلیدواژه‌ها، عنوان و توضیحات متا برای TamRank SEO و Rank Math، اسکیما و لینک‌های داخلی.
"""
(PLUGIN_DIR / 'README.txt').write_text(readme.strip(), encoding='utf-8')

# Zip packaging
zip_path = IMP_DIR / 'qpedia-importer-incomplete-depth-second-10.zip'
with zipfile.ZipFile(zip_path, 'w', zipfile.ZIP_DEFLATED) as z:
    for root, dirs, files in os.walk(PLUGIN_DIR):
        for file in files:
            full_p = Path(root) / file
            rel_p = full_p.relative_to(IMP_DIR)
            z.write(full_p, rel_p)

print("Importer zip created successfully:", zip_path)
print("Zip size:", zip_path.stat().st_size, "bytes")
