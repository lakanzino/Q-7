import json
import os

images_data_path = '/home/user/Q-7/article-rewrite-2026-09-22/importers/qpedia-glossary-200-images-importer/images_data.json'
output_path = '/home/user/Q-7/gallery/glossary.html'

with open(images_data_path, 'r', encoding='utf-8') as f:
    images_data = json.load(f)

categories = sorted(list(set(img['category'] for img in images_data)))

html_content = f'''<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>گالری ۲۰۰ تصویر مینیمال واژه‌نامه کوانتومی - Qpedia</title>
    <link rel="preconnect" href="https://cdn.jsdelivr.net">
    <link href="https://cdn.jsdelivr.net/gh/rastikerdar/vazirmatn@v33.003/Vazirmatn-font-face.css" rel="stylesheet" type="text/css" />
    <style>
        :root {{
            --bg-primary: #070a12;
            --bg-secondary: #0f1523;
            --bg-card: rgba(18, 25, 40, 0.75);
            --text-primary: #f8fafc;
            --text-secondary: #94a3b8;
            --text-muted: #64748b;
            --accent-cyan: #06b6d4;
            --accent-glow: rgba(6, 182, 212, 0.25);
            --border-color: rgba(255, 255, 255, 0.08);
            --radius-sm: 8px;
            --radius-md: 12px;
            --radius-lg: 16px;
        }}

        * {{
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }}

        body {{
            font-family: 'Vazirmatn', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
            background: radial-gradient(circle at 50% 0%, #111e33 0%, #070a12 60%, #04060a 100%);
            color: var(--text-primary);
            min-height: 100vh;
            padding: 0 0 60px 0;
            line-height: 1.6;
        }}

        header {{
            background: rgba(7, 10, 18, 0.88);
            backdrop-filter: blur(16px);
            border-bottom: 1px solid var(--border-color);
            position: sticky;
            top: 0;
            z-index: 100;
            padding: 16px 24px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.4);
        }}

        .header-content {{
            max-width: 1400px;
            margin: 0 auto;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 16px;
        }}

        .brand {{
            display: flex;
            align-items: center;
            gap: 12px;
        }}

        .brand-logo {{
            background: linear-gradient(135deg, #06b6d4, #3b82f6);
            color: #fff;
            padding: 6px 14px;
            border-radius: var(--radius-sm);
            font-weight: 800;
            font-size: 15px;
            letter-spacing: 0.5px;
        }}

        .brand-title h1 {{
            font-size: 18px;
            font-weight: 700;
        }}

        .brand-title p {{
            font-size: 12px;
            color: var(--text-secondary);
        }}

        .btn {{
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: linear-gradient(135deg, #06b6d4, #2563eb);
            color: #fff;
            text-decoration: none;
            padding: 9px 18px;
            border-radius: var(--radius-sm);
            font-size: 13px;
            font-weight: 600;
            transition: all 0.2s ease;
            box-shadow: 0 4px 12px rgba(6, 182, 212, 0.3);
            border: none;
            cursor: pointer;
        }}

        .btn:hover {{
            transform: translateY(-2px);
            box-shadow: 0 6px 18px rgba(6, 182, 212, 0.5);
        }}

        .container {{
            max-width: 1400px;
            margin: 24px auto;
            padding: 0 20px;
        }}

        .controls-card {{
            background: var(--bg-card);
            border: 1px solid var(--border-color);
            border-radius: var(--radius-lg);
            padding: 20px 24px;
            margin-bottom: 28px;
            backdrop-filter: blur(12px);
            display: flex;
            flex-direction: column;
            gap: 16px;
        }}

        .stats-bar {{
            display: flex;
            flex-wrap: wrap;
            gap: 20px;
            padding-bottom: 16px;
            border-bottom: 1px solid var(--border-color);
        }}

        .stat-item {{
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 13px;
            color: var(--text-secondary);
        }}

        .stat-item strong {{
            color: var(--accent-cyan);
            font-size: 16px;
        }}

        .filter-row {{
            display: flex;
            flex-wrap: wrap;
            gap: 16px;
            align-items: center;
            justify-content: space-between;
        }}

        .search-box {{
            flex: 1;
            min-width: 260px;
        }}

        .search-input {{
            width: 100%;
            background: rgba(7, 10, 18, 0.8);
            border: 1px solid var(--border-color);
            border-radius: var(--radius-sm);
            padding: 10px 16px;
            color: #fff;
            font-family: inherit;
            font-size: 13px;
            outline: none;
        }}

        .search-input:focus {{
            border-color: var(--accent-cyan);
        }}

        .select-filter {{
            background: rgba(7, 10, 18, 0.8);
            border: 1px solid var(--border-color);
            color: #fff;
            padding: 10px 14px;
            border-radius: var(--radius-sm);
            font-family: inherit;
            font-size: 13px;
            outline: none;
            cursor: pointer;
        }}

        /* Image Grid */
        .gallery-grid {{
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
            gap: 18px;
        }}

        .img-card {{
            background: var(--bg-card);
            border: 1px solid var(--border-color);
            border-radius: var(--radius-md);
            overflow: hidden;
            transition: all 0.25s ease;
            display: flex;
            flex-direction: column;
            cursor: pointer;
            position: relative;
        }}

        .img-card:hover {{
            transform: translateY(-4px);
            border-color: rgba(6, 182, 212, 0.4);
            box-shadow: 0 10px 24px rgba(0, 0, 0, 0.6), 0 0 16px var(--accent-glow);
        }}

        .img-frame {{
            width: 100%;
            position: relative;
            aspect-ratio: 16 / 9;
            background: #000;
            overflow: hidden;
        }}

        .img-frame img {{
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform 0.3s ease;
            display: block;
        }}

        .img-card:hover .img-frame img {{
            transform: scale(1.04);
        }}

        .badge-seq {{
            position: absolute;
            top: 6px;
            left: 6px;
            background: rgba(7, 10, 18, 0.85);
            backdrop-filter: blur(4px);
            color: var(--accent-cyan);
            font-size: 10px;
            font-weight: 700;
            padding: 2px 6px;
            border-radius: 4px;
            border: 1px solid rgba(6, 182, 212, 0.3);
        }}

        .badge-brand {{
            position: absolute;
            top: 6px;
            right: 6px;
            background: rgba(6, 182, 212, 0.15);
            backdrop-filter: blur(4px);
            color: #fff;
            font-size: 9px;
            font-weight: 800;
            padding: 2px 6px;
            border-radius: 4px;
            border: 1px solid rgba(6, 182, 212, 0.4);
        }}

        .card-body {{
            padding: 10px 12px;
            display: flex;
            flex-direction: column;
            gap: 4px;
            flex: 1;
            justify-content: space-between;
        }}

        .card-title {{
            font-size: 13px;
            font-weight: 700;
            color: #fff;
            line-height: 1.4;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }}

        .card-cat {{
            font-size: 11px;
            color: var(--accent-cyan);
        }}

        .card-slug {{
            font-size: 10.5px;
            color: var(--text-muted);
            direction: ltr;
            text-align: right;
            font-family: monospace;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }}

        /* Modal */
        .modal-overlay {{
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.88);
            backdrop-filter: blur(10px);
            z-index: 1000;
            display: none;
            justify-content: center;
            align-items: center;
            padding: 20px;
        }}

        .modal-overlay.active {{
            display: flex;
        }}

        .modal-box {{
            background: var(--bg-secondary);
            border: 1px solid var(--border-color);
            border-radius: var(--radius-lg);
            max-width: 900px;
            width: 100%;
            overflow: hidden;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.8);
            display: flex;
            flex-direction: column;
        }}

        .modal-header {{
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 16px 20px;
            border-bottom: 1px solid var(--border-color);
        }}

        .modal-close {{
            background: none;
            border: none;
            color: var(--text-secondary);
            font-size: 24px;
            cursor: pointer;
        }}

        .modal-close:hover {{
            color: #fff;
        }}

        .modal-img-wrap {{
            width: 100%;
            background: #000;
            aspect-ratio: 16 / 9;
        }}

        .modal-img-wrap img {{
            width: 100%;
            height: 100%;
            object-fit: contain;
        }}

        .modal-details {{
            padding: 18px 20px;
            display: flex;
            flex-direction: column;
            gap: 10px;
            font-size: 13px;
        }}

        .detail-row {{
            display: flex;
            gap: 8px;
        }}

        .detail-label {{
            color: var(--text-muted);
            min-width: 100px;
        }}

        .detail-val {{
            color: var(--text-primary);
            font-weight: 500;
        }}

        @media (max-width: 768px) {{
            .gallery-grid {{
                grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
                gap: 10px;
            }}
            .card-title {{
                font-size: 11.5px;
            }}
        }}
    </style>
</head>
<body>

    <header>
        <div class="header-content">
            <div class="brand">
                <span class="brand-logo">qpedia.ir</span>
                <div class="brand-title">
                    <h1>تصاویر مینیمال واژه‌نامه کوانتومی</h1>
                    <p>۲۰۰ تصویر شاخص بهینه‌شده با نشان نئونی شیشه‌ای qpedia.ir</p>
                </div>
            </div>
            <div>
                <a href="https://github.com/lakanzino/Q-7/raw/arena/01a0bba0-q-7/downloads/qpedia-glossary-200-images-importer.zip" class="btn">
                    📦 دانلود افزونه ۲۰۰ تصویر واژه‌نامه (2.9 MB)
                </a>
            </div>
        </div>
    </header>

    <div class="container">
        <div class="controls-card">
            <div class="stats-bar">
                <div class="stat-item">کل تصاویر اصطلاحات: <strong>{len(images_data)}</strong> تصویر</div>
                <div class="stat-item">ابعاد: <strong>1200x675 (16:9)</strong></div>
                <div class="stat-item">فرمت: <strong>WebP مینیمال</strong></div>
                <div class="stat-item">میانگین حجم هر تصویر: <strong>~۱۵ KB</strong></div>
                <div class="stat-item">تصاویر نمایان: <strong id="visible-count">{len(images_data)}</strong></div>
            </div>

            <div class="filter-row">
                <div class="search-box">
                    <input type="text" id="searchInput" class="search-input" placeholder="🔍 جستجو در نام اصطلاح، اسلاگ یا دسته..." oninput="filterGallery()">
                </div>
                <div>
                    <select id="catSelect" class="select-filter" onchange="filterGallery()">
                        <option value="all">نمایش تمامی دسته‌ها (۱۲ دسته)</option>
'''

for cat in categories:
    html_content += f'                        <option value="{cat}">{cat}</option>\n'

html_content += '''                    </select>
                </div>
            </div>
        </div>

        <div class="gallery-grid" id="galleryGrid">
'''

for img in images_data:
    seq = img['sequence']
    term = img['term']
    slug = img['slug']
    cat = img['category']
    filename = img['filename']
    alt = img['alt']

    html_content += f'''            <div class="img-card" data-cat="{cat}" data-term="{term}" data-slug="{slug}" onclick="openModal('{filename}', '{term}', '{slug}', '{cat}', '{alt}', {seq})">
                <div class="img-frame">
                    <img src="glossary-images/{filename}" alt="{alt}" loading="lazy">
                    <span class="badge-seq">#{seq:03d}</span>
                    <span class="badge-brand">qpedia.ir</span>
                </div>
                <div class="card-body">
                    <h3 class="card-title">{term}</h3>
                    <span class="card-cat">{cat}</span>
                    <span class="card-slug">{slug}</span>
                </div>
            </div>
'''

html_content += '''        </div>
    </div>

    <div class="modal-overlay" id="modalOverlay" onclick="closeModal(event)">
        <div class="modal-box" onclick="event.stopPropagation()">
            <div class="modal-header">
                <h3 id="modalTitle" style="font-size: 16px; font-weight: 700;"></h3>
                <button class="modal-close" onclick="closeModal()">&times;</button>
            </div>
            <div class="modal-img-wrap">
                <img id="modalImg" src="" alt="">
            </div>
            <div class="modal-details">
                <div class="detail-row">
                    <span class="detail-label">نام اصطلاح:</span>
                    <span class="detail-val" id="modalTerm"></span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">اسلاگ انگلیسی:</span>
                    <span class="detail-val" id="modalSlug" style="direction: ltr; font-family: monospace;"></span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">دسته‌بندی:</span>
                    <span class="detail-val" id="modalCat"></span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">نام فایل:</span>
                    <span class="detail-val" id="modalFilename" style="direction: ltr; font-family: monospace;"></span>
                </div>
            </div>
        </div>
    </div>

    <script>
        function filterGallery() {
            const search = document.getElementById('searchInput').value.toLowerCase().trim();
            const cat = document.getElementById('catSelect').value;
            const cards = document.querySelectorAll('.img-card');
            let visible = 0;

            cards.forEach(card => {
                const cardCat = card.getAttribute('data-cat');
                const term = (card.getAttribute('data-term') || '').toLowerCase();
                const slug = (card.getAttribute('data-slug') || '').toLowerCase();

                const matchCat = (cat === 'all' || cardCat === cat);
                const matchSearch = (!search || term.includes(search) || slug.includes(search));

                if (matchCat && matchSearch) {
                    card.style.display = 'flex';
                    visible++;
                } else {
                    card.style.display = 'none';
                }
            });

            document.getElementById('visible-count').textContent = visible;
        }

        function openModal(filename, term, slug, cat, alt, seq) {
            document.getElementById('modalImg').src = 'glossary-images/' + filename;
            document.getElementById('modalTitle').textContent = '#' + String(seq).padStart(3, '0') + ' - ' + term;
            document.getElementById('modalTerm').textContent = term;
            document.getElementById('modalSlug').textContent = slug;
            document.getElementById('modalCat').textContent = cat;
            document.getElementById('modalFilename').textContent = filename;
            document.getElementById('modalOverlay').classList.add('active');
        }

        function closeModal(e) {
            document.getElementById('modalOverlay').classList.remove('active');
        }

        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') closeModal();
        });
    </script>
</body>
</html>
'''

with open(output_path, 'w', encoding='utf-8') as f:
    f.write(html_content)

print(f"Glossary Gallery HTML successfully generated at {output_path} with {len(images_data)} images.")
