#!/usr/bin/env python3
"""Generate all 200 minimal, elegant featured images for Qpedia Quantum Glossary and build the WordPress importer plugin."""
import json
import math
import os
import shutil
import sys
import zipfile
from pathlib import Path
from PIL import Image, ImageDraw, ImageFont

import arabic_reshaper
from bidi.algorithm import get_display

ROOT = Path('/home/user/Q-7')
sys.path.insert(0, str(ROOT / 'tools'))
from glossary_data_1 import SLUG_MAP
from glossary_data_2 import SLUG_MAP_2

TERMS_JSON_PATH = ROOT / 'article-rewrite-2026-09-22/importers/glossary-200-replacement/qpedia-glossary-200-replacement/terms.json'
OUT_IMAGES_DIR = ROOT / 'article-rewrite-2026-09-22/glossary-images-200'
OUT_IMAGES_DIR.mkdir(parents=True, exist_ok=True)

IMPORTER_DIR = ROOT / 'article-rewrite-2026-09-22/importers/qpedia-glossary-200-images-importer'
IMPORTER_DIR.mkdir(parents=True, exist_ok=True)
IMPORTER_IMG_DIR = IMPORTER_DIR / 'images'
IMPORTER_IMG_DIR.mkdir(parents=True, exist_ok=True)

DL_DIR = ROOT / 'downloads'
DL_DIR.mkdir(parents=True, exist_ok=True)

# Font paths
FONT_BOLD = '/home/user/fonts/Vazirmatn-Bold.ttf'
FONT_MED = '/home/user/fonts/Vazirmatn-SemiBold.ttf'
FONT_REG = '/home/user/fonts/Vazirmatn-Regular.ttf'
FONT_EN = '/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf'
FONT_EN_BOLD = '/usr/share/fonts/truetype/dejavu/DejaVuSans-Bold.ttf'

font_title = ImageFont.truetype(FONT_BOLD, 42)
font_title_sm = ImageFont.truetype(FONT_BOLD, 35)
font_badge = ImageFont.truetype(FONT_MED, 15)
font_en = ImageFont.truetype(FONT_EN, 17)
font_brand = ImageFont.truetype(FONT_EN_BOLD, 15)

# Category styling: (Primary Glow Color, Secondary Color, Badge BG)
CAT_THEMES = {
    'مفاهیم پایه': {
        'glow': (6, 182, 212),      # Cyan
        'sec': (59, 130, 246),      # Blue
        'bg': (10, 25, 47),
        'icon_type': 'orbitals'
    },
    'رایانش کوانتومی': {
        'glow': (0, 242, 254),      # Bright Cyan
        'sec': (79, 172, 254),      # Royal Blue
        'bg': (10, 28, 50),
        'icon_type': 'bloch'
    },
    'نور کوانتومی': {
        'glow': (245, 158, 11),     # Amber Gold
        'sec': (251, 146, 60),      # Laser Orange
        'bg': (35, 25, 10),
        'icon_type': 'optics'
    },
    'ارتباطات کوانتومی': {
        'glow': (16, 185, 129),     # Emerald Green
        'sec': (20, 184, 166),      # Teal
        'bg': (10, 32, 25),
        'icon_type': 'crypto'
    },
    'حسگرها و ابزارها': {
        'glow': (14, 165, 233),     # Sky Blue
        'sec': (56, 189, 248),      # Light Cyan
        'bg': (12, 27, 45),
        'icon_type': 'squid'
    },
    'مواد کوانتومی': {
        'glow': (244, 63, 94),      # Rose
        'sec': (251, 113, 133),     # Coral
        'bg': (35, 12, 22),
        'icon_type': 'graphene'
    },
    'کیهان کوانتومی': {
        'glow': (139, 92, 246),     # Violet
        'sec': (168, 85, 247),      # Purple
        'bg': (25, 15, 45),
        'icon_type': 'wormhole'
    },
    'ذرات و نیروها': {
        'glow': (239, 68, 68),      # Crimson
        'sec': (249, 115, 22),      # Orange
        'bg': (35, 15, 15),
        'icon_type': 'particles'
    },
    'زیست و پزشکی': {
        'glow': (34, 197, 94),      # Spring Green
        'sec': (132, 204, 22),      # Lime
        'bg': (12, 35, 18),
        'icon_type': 'biology'
    },
    'هوش مصنوعی کوانتومی': {
        'glow': (168, 85, 247),     # Neon Purple
        'sec': (99, 102, 241),      # Indigo
        'bg': (25, 15, 45),
        'icon_type': 'ai'
    },
    'فلسفه کوانتوم': {
        'glow': (234, 179, 8),      # Topaz Gold
        'sec': (253, 224, 71),      # Light Gold
        'bg': (35, 30, 10),
        'icon_type': 'philosophy'
    },
    'تاریخ و جامعه': {
        'glow': (217, 119, 6),      # Ochre
        'sec': (245, 158, 11),      # Warm Amber
        'bg': (32, 22, 10),
        'icon_type': 'history'
    }
}

def shape_text(text):
    return get_display(arabic_reshaper.reshape(text))

def draw_icon(draw, icon_type, cx, cy, glow_col, sec_col):
    """Draw minimal high-tech geometric diagram in the center."""
    if icon_type == 'orbitals':
        # Wave harmonics & electron orbits
        r_out = 95
        # Dual orbital ellipses
        for angle_offset in [-35, 35]:
            draw.ellipse((cx - r_out, cy - 32, cx + r_out, cy + 32), outline=sec_col, width=2)
        # Sine wave overlay
        points = []
        for x in range(cx - 110, cx + 110, 4):
            val = math.sin((x - cx) * 0.06) * 22
            points.append((x, cy + val))
        if len(points) > 1:
            draw.line(points, fill=glow_col, width=3)
        # Central glowing nucleus
        draw.ellipse((cx - 14, cy - 14, cx + 14, cy + 14), fill=glow_col)
        draw.ellipse((cx - 7, cy - 7, cx + 7, cy + 7), fill=(255, 255, 255))
        # Orbital nodes
        for angle in [45, 135, 225, 315]:
            nx = cx + int(80 * math.cos(math.radians(angle)))
            ny = cy + int(30 * math.sin(math.radians(angle)))
            draw.ellipse((nx - 4, ny - 4, nx + 4, ny + 4), fill=sec_col)

    elif icon_type == 'bloch':
        # 3D Bloch sphere
        r = 85
        draw.ellipse((cx - r, cy - r, cx + r, cy + r), outline=glow_col, width=2)
        draw.ellipse((cx - r, cy - 28, cx + r, cy + 28), outline=sec_col, width=2)
        # Z axis
        draw.line((cx, cy - r - 15, cx, cy + r + 15), fill=(148, 163, 184), width=2)
        # State vector |psi>
        vx = cx + int(r * 0.72 * math.cos(math.radians(-50)))
        vy = cy + int(r * 0.72 * math.sin(math.radians(-50)))
        draw.line((cx, cy, vx, vy), fill=(244, 63, 94), width=4)
        draw.ellipse((vx - 5, vy - 5, vx + 5, vy + 5), fill=(244, 63, 94))
        # Axis poles |0> |1>
        draw.ellipse((cx - 4, cy - r - 4, cx + 4, cy - r + 4), fill=glow_col)
        draw.ellipse((cx - 4, cy + r - 4, cx + 4, cy + r + 4), fill=glow_col)

    elif icon_type == 'optics':
        # Beam splitter & photon beams
        size = 50
        # Prism box
        draw.rectangle((cx - size, cy - size, cx + size, cy + size), outline=sec_col, width=2)
        # Diagonal beam splitting mirror
        draw.line((cx - size, cy + size, cx + size, cy - size), fill=glow_col, width=3)
        # Input beam
        draw.line((cx - 120, cy, cx, cy), fill=glow_col, width=3)
        # Transmitted beam
        draw.line((cx, cy, cx + 120, cy), fill=glow_col, width=3)
        # Reflected beam
        draw.line((cx, cy, cx, cy - 100), fill=glow_col, width=3)
        # Detectors
        draw.arc((cx + 110, cy - 18, cx + 130, cy + 18), 90, 270, fill=sec_col, width=4)
        draw.arc((cx - 18, cy - 110, cx + 18, cy - 90), 0, 180, fill=sec_col, width=4)

    elif icon_type == 'crypto':
        # Entangled QKD nodes & key link
        r_node = 24
        # Alice node
        draw.ellipse((cx - 90 - r_node, cy - r_node, cx - 90 + r_node, cy + r_node), outline=glow_col, width=3)
        # Bob node
        draw.ellipse((cx + 90 - r_node, cy - r_node, cx + 90 + r_node, cy + r_node), outline=glow_col, width=3)
        # Entanglement wave connecting them
        points = []
        for x in range(cx - 90, cx + 90, 4):
            val = math.sin((x - cx) * 0.08) * 18
            points.append((x, cy + val))
        if len(points) > 1:
            draw.line(points, fill=sec_col, width=3)
        # Central key icon
        draw.rectangle((cx - 12, cy - 10, cx + 12, cy + 10), outline=glow_col, width=2)
        draw.arc((cx - 7, cy - 18, cx + 7, cy - 8), 180, 360, fill=glow_col, width=2)

    elif icon_type == 'squid':
        # Superconducting quantum interference loop
        r = 70
        draw.ellipse((cx - r, cy - r, cx + r, cy + r), outline=glow_col, width=4)
        # Josephson junctions (crosses)
        for y_pos in [cy - r, cy + r]:
            draw.line((cx - 12, y_pos, cx + 12, y_pos), fill=sec_col, width=4)
            draw.line((cx, y_pos - 10, cx, y_pos + 10), fill=sec_col, width=4)
        # Flux arrow vector through center
        draw.line((cx, cy + 45, cx, cy - 45), fill=(244, 63, 94), width=3)
        draw.polygon([(cx, cy - 50), (cx - 7, cy - 35), (cx + 7, cy - 35)], fill=(244, 63, 94))

    elif icon_type == 'graphene':
        # 2D Hexagonal Honeycomb lattice
        side = 32
        for ox, oy in [(-55, -30), (0, -30), (55, -30), (-28, 20), (28, 20)]:
            pts = []
            for i in range(6):
                ang = math.radians(60 * i)
                pts.append((cx + ox + int(side * math.cos(ang)), cy + oy + int(side * math.sin(ang))))
            draw.polygon(pts, outline=glow_col)
            for px, py in pts:
                draw.ellipse((px - 3, py - 3, px + 3, py + 3), fill=sec_col)

    elif icon_type == 'wormhole':
        # Spacetime curvature throat
        for r_curv in [85, 65, 45, 25]:
            draw.ellipse((cx - r_curv, cy - 65, cx + r_curv, cy - 65 + r_curv * 0.4), outline=glow_col, width=2)
            draw.ellipse((cx - r_curv, cy + 65 - r_curv * 0.4, cx + r_curv, cy + 65), outline=sec_col, width=2)
        # Bridge throat lines
        draw.line((cx - 25, cy - 50, cx - 25, cy + 50), fill=glow_col, width=2)
        draw.line((cx + 25, cy - 50, cx + 25, cy + 50), fill=glow_col, width=2)
        # Central singularity glow
        draw.ellipse((cx - 10, cy - 10, cx + 10, cy + 10), fill=(255, 255, 255))

    elif icon_type == 'particles':
        # Tri-quark hadron triangle
        pts = [(cx, cy - 60), (cx - 65, cy + 45), (cx + 65, cy + 45)]
        # Gluon tube lines
        draw.line([pts[0], pts[1], pts[2], pts[0]], fill=sec_col, width=3)
        # Quarks
        colors = [(239, 68, 68), (34, 197, 94), (59, 130, 246)]
        for (px, py), col in zip(pts, colors):
            draw.ellipse((px - 16, py - 16, px + 16, py + 16), fill=col)
            draw.ellipse((px - 8, py - 8, px + 8, py + 8), fill=(255, 255, 255))

    elif icon_type == 'biology':
        # Quantum tunneling through potential well / biomolecule
        # Potential barrier
        draw.line((cx - 110, cy + 50, cx - 40, cy + 50), fill=sec_col, width=3)
        draw.line((cx - 40, cy + 50, cx - 40, cy - 60), fill=sec_col, width=3)
        draw.line((cx - 40, cy - 60, cx + 40, cy - 60), fill=sec_col, width=3)
        draw.line((cx + 40, cy - 60, cx + 40, cy + 50), fill=sec_col, width=3)
        draw.line((cx + 40, cy + 50, cx + 110, cy + 50), fill=sec_col, width=3)
        # Tunneling wave packet
        points = []
        for x in range(cx - 100, cx + 100, 3):
            if x < cx - 40:
                val = math.sin((x - cx) * 0.15) * 25
            elif x <= cx + 40:
                # Exponential decay in barrier
                val = math.exp(-(x - (cx - 40)) * 0.04) * 20 * math.sin((x - cx) * 0.15)
            else:
                val = math.sin((x - cx) * 0.15) * 10
            points.append((x, cy - 10 + val))
        if len(points) > 1:
            draw.line(points, fill=glow_col, width=3)

    elif icon_type == 'ai':
        # Quantum Neural Circuit
        for y_wire in [cy - 40, cy, cy + 40]:
            draw.line((cx - 100, y_wire, cx + 100, y_wire), fill=(148, 163, 184), width=2)
        # Rotation gate boxes
        for gx, gy in [(cx - 60, cy - 40), (cx + 50, cy), (cx - 30, cy + 40)]:
            draw.rectangle((gx - 16, gy - 16, gx + 16, gy + 16), fill=glow_col)
        # CNOT control & target
        draw.ellipse((cx, cy - 40 - 5, cx, cy - 40 + 5), fill=sec_col)
        draw.line((cx, cy - 40, cx, cy + 40), fill=sec_col, width=2)
        draw.ellipse((cx - 10, cy + 40 - 10, cx + 10, cy + 40 + 10), outline=sec_col, width=2)

    elif icon_type == 'philosophy':
        # Superposition branching trees
        draw.line((cx - 80, cy, cx - 20, cy), fill=glow_col, width=3)
        # Branch top
        draw.line((cx - 20, cy, cx + 60, cy - 50), fill=glow_col, width=3)
        draw.ellipse((cx + 60 - 8, cy - 50 - 8, cx + 60 + 8, cy - 50 + 8), fill=sec_col)
        # Branch bottom
        draw.line((cx - 20, cy, cx + 60, cy + 50), fill=glow_col, width=3)
        draw.ellipse((cx + 60 - 8, cy + 50 - 8, cx + 60 + 8, cy + 50 + 8), fill=sec_col)
        # Observation eye silhouette
        draw.ellipse((cx - 20 - 10, cy - 10, cx - 20 + 10, cy + 10), fill=(255, 255, 255))

    elif icon_type == 'history':
        # Classical quanta energy levels & atom
        for r_lvl in [40, 70, 95]:
            draw.ellipse((cx - r_lvl, cy - r_lvl, cx + r_lvl, cy + r_lvl), outline=sec_col, width=1, fill=None)
        # Transition quantum jump photon emission
        draw.line((cx + 40, cy, cx + 95, cy), fill=glow_col, width=3)
        draw.polygon([(cx + 95, cy), (cx + 85, cy - 5), (cx + 85, cy + 5)], fill=glow_col)
        # Core
        draw.ellipse((cx - 15, cy - 15, cx + 15, cy + 15), fill=glow_col)

def generate_glossary_card(term_item, out_path, seq_num):
    term = term_item['term']
    category = term_item.get('category', 'مفاهیم پایه')
    slug = term_item.get('slug', f'term-{seq_num}')

    theme = CAT_THEMES.get(category, CAT_THEMES['مفاهیم پایه'])
    glow_col = theme['glow']
    sec_col = theme['sec']

    w, h = 1200, 675
    img = Image.new('RGB', (w, h), (8, 13, 26))
    draw = ImageDraw.Draw(img)

    # 1. Background radial gradient glow
    for r in range(360, 0, -8):
        factor = (1 - r / 360) ** 1.5
        r_col = int(8 + theme['bg'][0] * factor * 1.5)
        g_col = int(13 + theme['bg'][1] * factor * 1.5)
        b_col = int(26 + theme['bg'][2] * factor * 1.5)
        draw.ellipse((w//2 - r*1.6, 240 - r, w//2 + r*1.6, 240 + r), fill=(r_col, g_col, b_col))

    # 2. Tech blueprint grid
    for x in range(0, w, 60):
        draw.line((x, 0, x, h), fill=(14, 20, 35), width=1)
    for y in range(0, h, 60):
        draw.line((0, y, w, y), fill=(14, 20, 35), width=1)

    # 3. Top Badges
    # Category badge
    cat_text = shape_text(category)
    badge_w = 160
    draw.rounded_rectangle((45, 35, 45 + badge_w, 75), radius=8, fill=(15, 23, 42), outline=glow_col, width=1)
    draw.text((45 + badge_w // 2, 55), cat_text, fill=glow_col, font=font_badge, anchor='mm')

    # Sequence badge (e.g. #001)
    seq_str = f"#{seq_num:03d}"
    draw.rounded_rectangle((220, 35, 290, 75), radius=8, fill=(15, 23, 42), outline=(50, 65, 95), width=1)
    draw.text((255, 55), seq_str, fill=(148, 163, 184), font=font_en, anchor='mm')

    # Watermark brand badge (Top Right)
    brand_text = 'qpedia.ir'
    draw.rounded_rectangle((w - 155, 35, w - 45, 75), radius=8, fill=(15, 23, 42), outline=glow_col, width=1)
    draw.text((w - 100, 55), brand_text, fill=(241, 245, 249), font=font_brand, anchor='mm')

    # 4. Center Geometric Graphic
    draw_icon(draw, theme['icon_type'], w // 2, 240, glow_col, sec_col)

    # 5. Persian Term Title
    p_title = shape_text(term)
    # Check length for font size
    current_font = font_title if len(term) <= 24 else font_title_sm
    # Text shadow
    draw.text((w // 2 + 2, 482), p_title, fill=(0, 0, 0, 180), font=current_font, anchor='mm')
    draw.text((w // 2, 480), p_title, fill=(255, 255, 255), font=current_font, anchor='mm')

    # 6. English Subtitle (Slug)
    draw.text((w // 2, 540), slug, fill=(148, 163, 184), font=font_en, anchor='mm')

    # 7. Bottom neon accent bar
    line_w = 140
    draw.line((w // 2 - line_w, 575, w // 2 + line_w, 575), fill=glow_col, width=3)
    draw.ellipse((w // 2 - 4, 575 - 4, w // 2 + 4, 575 + 4), fill=(255, 255, 255))

    img.save(out_path, 'WEBP', quality=85, method=6)

def main():
    with open(TERMS_JSON_PATH, 'r', encoding='utf-8') as f:
        terms = json.load(f)

    slugs_map = dict(SLUG_MAP)
    slugs_map.update(SLUG_MAP_2)

    manifest = []
    print(f"Generating 200 minimal glossary images for Qpedia...")

    for i, t in enumerate(terms, start=1):
        term_name = t['term']
        slug = slugs_map.get(term_name, f"term-{i:03d}")
        t['slug'] = slug
        filename = f"glossary-{i:03d}-{slug}.webp"
        out_p = OUT_IMAGES_DIR / filename
        generate_glossary_card(t, out_p, i)

        # Copy to importer images
        shutil.copy2(out_p, IMPORTER_IMG_DIR / filename)

        manifest.append({
            'sequence': i,
            'term': term_name,
            'slug': slug,
            'category': t.get('category', 'مفاهیم پایه'),
            'filename': filename,
            'alt': f"تصویر شاخص مینیمال اصطلاح {term_name} در واژه‌نامه تخصصی کوانتوم با نشان qpedia.ir"
        })

        if i % 25 == 0 or i == len(terms):
            print(f"  [{i:3d}/{len(terms)}] Generated {filename}")

    # Save manifest in importer
    with open(IMPORTER_DIR / 'images_data.json', 'w', encoding='utf-8') as f:
        json.dump(manifest, f, ensure_ascii=False, indent=2)

    # Write WordPress Importer Plugin PHP
    php_content = '''<?php
/**
 * Plugin Name: Qpedia Glossary 200 Featured Images Importer
 * Description: افزونه خودکار اتصال و جایگزینی 200 تصویر مینیمال و استاندارد WebP برای اصطلاحات واژه‌نامه تخصصی کوانتوم با نشان qpedia.ir و متادیتاهای کامل سئو در وردپرس.
 * Version: 1.0.0
 * Author: Qpedia Team
 * Text Domain: qpedia-glossary-200-images
 */

defined('ABSPATH') || exit;

add_action('admin_menu', 'qp_glossary_img_menu');
function qp_glossary_img_menu() {
    add_management_page(
        'اتصال تصاویر اصطلاحات واژه‌نامه',
        'تصاویر واژه‌نامه (200)',
        'manage_options',
        'qpedia-glossary-200-images',
        'qp_glossary_img_render_page'
    );
}

function qp_glossary_img_render_page() {
    if (!current_user_can('manage_options')) {
        return;
    }

    echo '<div class="wrap" dir="rtl" style="font-family:Tahoma,sans-serif;line-height:1.7;">';
    echo '<h1>🎨 اتصال و جایگزینی خودکار ۲۰۰ تصویر مینیمال واژه‌نامه کوانتوم</h1>';

    if (isset($_POST['qp_run_glossary_images'])) {
        check_admin_referer('qp_glossary_img_nonce');
        $result = qp_glossary_img_execute();

        $class = $result['ok'] ? 'notice-success' : 'notice-error';
        echo '<div class="notice ' . esc_attr($class) . '"><p><strong>' . esc_html($result['message']) . '</strong></p></div>';

        if (!empty($result['log'])) {
            echo '<div style="max-height:400px;overflow-y:auto;background:#fff;border:1px solid #ccd0d4;padding:12px;margin:15px 0;border-radius:6px;">';
            echo '<ul style="margin:0;padding:0 20px;font-size:13px;line-height:1.8;">';
            foreach ($result['log'] as $line) {
                echo '<li>' . esc_html($line) . '</li>';
            }
            echo '</ul></div>';
        }
    }

    echo '<div class="card" style="max-width:850px;margin-top:20px;padding:20px 25px;border-radius:8px;box-shadow:0 2px 8px rgba(0,0,0,0.08);">';
    echo '<h2>مشخصات بسته تصاویر مینیمال واژه‌نامه:</h2>';
    echo '<p>این بسته شامل <strong>۲۰۰ تصویر شاخص مینیمال WebP</strong> در ابعاد استاندارد ۱۶:۹ (1200x675) با نشان رسمی <code>qpedia.ir</code>، تفکیک رنگی ۱۲ دسته، الگوهای هندسی وکتور و متن‌های جایگزین سئو است.</p>';
    echo '<p style="color:#555;">با کلیک روی دکمه زیر، افزونه تمام اصطلاحات پست‌تایپ <code>qp_glossary</code> را شناسایی و تصویر مینیمال مربوط به هر اصطلاح را به عنوان تصویر شاخص متصل می‌نماید.</p>';

    echo '<form method="post" style="margin-top:20px;">';
    wp_nonce_field('qp_glossary_img_nonce');
    submit_button('شروع اتصال یک‌جای تصاویر به ۲۰۰ اصطلاح واژه‌نامه', 'primary', 'qp_run_glossary_images');
    echo '</form>';
    echo '</div></div>';
}

function qp_glossary_img_execute() {
    $data_file = plugin_dir_path(__FILE__) . 'images_data.json';
    if (!is_readable($data_file)) {
        return array('ok' => false, 'message' => 'فایل متادیتا یافت نشد.', 'log' => array());
    }

    $items = json_decode(file_get_contents($data_file), true);
    if (!is_array($items) || empty($items)) {
        return array('ok' => false, 'message' => 'فرمت متادیتا نامعتبر است.', 'log' => array());
    }

    $count_ok = 0;
    $count_miss = 0;
    $log = array();

    $upload_dir = wp_upload_dir();
    require_once(ABSPATH . 'wp-admin/includes/image.php');

    foreach ($items as $item) {
        $term      = trim((string)$item['term']);
        $slug      = trim((string)$item['slug']);
        $filename  = basename((string)$item['filename']);
        $alt_text  = trim((string)$item['alt']);

        // Find post by term title in qp_glossary
        $posts = get_posts(array(
            'post_type'   => array('qp_glossary', 'post'),
            'post_status' => 'any',
            'title'       => $term,
            'numberposts' => 1
        ));

        // Fallback by slug
        if (empty($posts)) {
            $posts = get_posts(array(
                'name'        => $slug,
                'post_type'   => array('qp_glossary', 'post'),
                'post_status' => 'any',
                'numberposts' => 1
            ));
        }

        if (empty($posts)) {
            $log[] = '⚠️ اصطلاح «' . $term . '» در سایت یافت نشد.';
            $count_miss++;
            continue;
        }

        $post_id = (int)$posts[0]->ID;
        $img_src = plugin_dir_path(__FILE__) . 'images/' . $filename;

        if (!file_exists($img_src)) {
            $log[] = '❌ فایل تصویر «' . $filename . '» در افزونه موجود نیست.';
            $count_miss++;
            continue;
        }

        $target_file = $upload_dir['path'] . '/' . $filename;
        if (!file_exists($target_file)) {
            wp_mkdir_p($upload_dir['path']);
            copy($img_src, $target_file);
        }

        $base_name = pathinfo($filename, PATHINFO_FILENAME);
        $existing_attach = get_posts(array(
            'post_type'      => 'attachment',
            'name'           => $base_name,
            'posts_per_page' => 1,
            'post_status'    => 'inherit'
        ));

        if (!empty($existing_attach)) {
            $attach_id = (int)$existing_attach[0]->ID;
        } else {
            $wp_filetype = wp_check_filetype($filename, null);
            $attachment = array(
                'guid'           => $upload_dir['url'] . '/' . $filename,
                'post_mime_type' => !empty($wp_filetype['type']) ? $wp_filetype['type'] : 'image/webp',
                'post_title'     => sanitize_text_field('تصویر ' . $term),
                'post_content'   => '',
                'post_status'    => 'inherit'
            );
            $attach_id = wp_insert_attachment($attachment, $target_file, $post_id);
            if (!is_wp_error($attach_id) && $attach_id > 0) {
                $attach_data = wp_generate_attachment_metadata($attach_id, $target_file);
                wp_update_attachment_metadata($attach_id, $attach_data);
            }
        }

        if (!empty($attach_id) && !is_wp_error($attach_id)) {
            set_post_thumbnail($post_id, $attach_id);
            update_post_meta($attach_id, '_wp_attachment_image_alt', sanitize_text_field($alt_text));
            update_post_meta($post_id, '_thumbnail_id', $attach_id);
            $log[] = '✅ تصویر مینیمال «' . $term . '» متصل شد -> ' . $filename . ' (شناسه ' . $attach_id . ')';
            $count_ok++;
        } else {
            $log[] = '❌ خطا در اتصال تصویر برای اصطلاح ' . $term;
            $count_miss++;
        }
    }

    $msg = 'عملیات موفقیت‌آمیز: ' . $count_ok . ' تصویر مینیمال با نشان qpedia.ir به اصطلاحات واژه‌نامه متصل گردید.';
    return array('ok' => true, 'message' => $msg, 'log' => $log);
}
'''

    with open(IMPORTER_DIR / 'qpedia-glossary-200-images-importer.php', 'w', encoding='utf-8') as f:
        f.write(php_content)

    # Package into Zip
    zip_p = DL_DIR / 'qpedia-glossary-200-images-importer.zip'
    with zipfile.ZipFile(zip_p, 'w', zipfile.ZIP_DEFLATED) as zipf:
        for root, dirs, files in os.walk(IMPORTER_DIR):
            for file in files:
                full_p = Path(root) / file
                rel_p = full_p.relative_to(IMPORTER_DIR.parent)
                zipf.write(full_p, arcname=str(rel_p))

    print(f"Successfully packaged Glossary 200 Images Plugin at {zip_p} ({zip_p.stat().st_size / 1024 / 1024:.2f} MB)")

if __name__ == '__main__':
    main()
