import json
import os

images_data_path = '/home/user/Q-7/article-rewrite-2026-09-22/importers/all-featured-images-batches/qpedia-all-featured-images-master/images_data.json'
catalog_path = '/home/user/Q-7/docs/all-featured-images-catalog.json'
output_path = '/home/user/Q-7/gallery/index.html'

with open(images_data_path, 'r', encoding='utf-8') as f:
    images_data = json.load(f)

with open(catalog_path, 'r', encoding='utf-8') as f:
    catalog = json.load(f)

# Map title/slug to batch number
batch_map = {}
for batch in catalog:
    b_num = batch['batch_num']
    for title in batch['titles']:
        batch_map[title] = b_num

# Enrich images_data with batch number
for img in images_data:
    img['batch'] = batch_map.get(img['title'], 1)

html_content = f'''<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>گالری بازبینی تصاویر شاخص کیوپدیا (172 تصویر) - Qpedia</title>
    <link rel="preconnect" href="https://cdn.jsdelivr.net">
    <link href="https://cdn.jsdelivr.net/gh/rastikerdar/vazirmatn@v33.003/Vazirmatn-font-face.css" rel="stylesheet" type="text/css" />
    <style>
        :root {{
            --bg-primary: #0a0e17;
            --bg-secondary: #121826;
            --bg-card: rgba(26, 34, 52, 0.7);
            --text-primary: #f1f5f9;
            --text-secondary: #94a3b8;
            --text-muted: #64748b;
            --accent-cyan: #06b6d4;
            --accent-glow: rgba(6, 182, 212, 0.3);
            --accent-blue: #3b82f6;
            --accent-green: #10b981;
            --border-color: rgba(255, 255, 255, 0.08);
            --card-border: rgba(6, 182, 212, 0.2);
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
            background: radial-gradient(circle at 50% 0%, #152238 0%, #0a0e17 60%, #05070d 100%);
            color: var(--text-primary);
            min-height: 100vh;
            padding: 0 0 60px 0;
            line-height: 1.6;
        }}

        header {{
            background: rgba(10, 14, 23, 0.85);
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
            font-size: 16px;
            letter-spacing: 0.5px;
            box-shadow: 0 0 15px var(--accent-glow);
        }}

        .brand-title h1 {{
            font-size: 18px;
            font-weight: 700;
            color: #fff;
        }}

        .brand-title p {{
            font-size: 12px;
            color: var(--text-secondary);
        }}

        .header-actions {{
            display: flex;
            align-items: center;
            gap: 12px;
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

        .btn-outline {{
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid var(--border-color);
            color: var(--text-secondary);
            box-shadow: none;
        }}

        .btn-outline:hover {{
            background: rgba(255, 255, 255, 0.1);
            color: #fff;
            border-color: rgba(255, 255, 255, 0.2);
            box-shadow: none;
        }}

        .container {{
            max-width: 1400px;
            margin: 24px auto;
            padding: 0 20px;
        }}

        /* Control Panel */
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
            position: relative;
        }}

        .search-input {{
            width: 100%;
            background: rgba(10, 14, 23, 0.8);
            border: 1px solid var(--border-color);
            border-radius: var(--radius-sm);
            padding: 10px 16px;
            color: #fff;
            font-family: inherit;
            font-size: 13px;
            outline: none;
            transition: all 0.2s ease;
        }}

        .search-input:focus {{
            border-color: var(--accent-cyan);
            box-shadow: 0 0 0 3px rgba(6, 182, 212, 0.2);
        }}

        .batch-filter-wrap {{
            display: flex;
            align-items: center;
            gap: 10px;
        }}

        .select-filter {{
            background: rgba(10, 14, 23, 0.8);
            border: 1px solid var(--border-color);
            color: #fff;
            padding: 10px 14px;
            border-radius: var(--radius-sm);
            font-family: inherit;
            font-size: 13px;
            outline: none;
            cursor: pointer;
        }}

        .select-filter:focus {{
            border-color: var(--accent-cyan);
        }}

        /* Image Grid */
        .gallery-grid {{
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(240px, 1fr));
            gap: 20px;
        }}

        .img-card {{
            background: var(--bg-card);
            border: 1px solid var(--border-color);
            border-radius: var(--radius-md);
            overflow: hidden;
            transition: all 0.25s cubic-bezier(0.16, 1, 0.3, 1);
            display: flex;
            flex-direction: column;
            cursor: pointer;
            position: relative;
        }}

        .img-card:hover {{
            transform: translateY(-4px);
            border-color: var(--card-border);
            box-shadow: 0 10px 24px rgba(0, 0, 0, 0.5), 0 0 16px var(--accent-glow);
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
            transform: scale(1.05);
        }}

        .badge-batch {{
            position: absolute;
            top: 8px;
            left: 8px;
            background: rgba(10, 14, 23, 0.85);
            backdrop-filter: blur(6px);
            color: var(--accent-cyan);
            font-size: 11px;
            font-weight: 700;
            padding: 3px 8px;
            border-radius: 4px;
            border: 1px solid rgba(6, 182, 212, 0.4);
        }}

        .badge-watermark {{
            position: absolute;
            top: 8px;
            right: 8px;
            background: rgba(6, 182, 212, 0.2);
            backdrop-filter: blur(6px);
            color: #fff;
            font-size: 10px;
            font-weight: 800;
            padding: 2px 7px;
            border-radius: 4px;
            border: 1px solid rgba(6, 182, 212, 0.5);
            letter-spacing: 0.5px;
        }}

        .card-body {{
            padding: 12px 14px;
            display: flex;
            flex-direction: column;
            gap: 6px;
            flex: 1;
            justify-content: space-between;
        }}

        .card-title {{
            font-size: 13.5px;
            font-weight: 700;
            color: #fff;
            line-height: 1.4;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }}

        .card-slug {{
            font-size: 11px;
            color: var(--text-muted);
            direction: ltr;
            text-align: right;
            font-family: monospace;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }}

        .card-footer {{
            margin-top: 8px;
            padding-top: 8px;
            border-top: 1px solid rgba(255, 255, 255, 0.05);
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 11px;
            color: var(--text-secondary);
        }}

        /* Modal */
        .modal-overlay {{
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.85);
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
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.7);
            display: flex;
            flex-direction: column;
            position: relative;
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
            line-height: 1;
        }}

        .modal-close:hover {{
            color: #fff;
        }}

        .modal-img-wrap {{
            width: 100%;
            background: #000;
            aspect-ratio: 16 / 9;
            overflow: hidden;
        }}

        .modal-img-wrap img {{
            width: 100%;
            height: 100%;
            object-fit: contain;
        }}

        .modal-details {{
            padding: 20px;
            display: flex;
            flex-direction: column;
            gap: 12px;
        }}

        .detail-row {{
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            font-size: 13px;
        }}

        .detail-label {{
            color: var(--text-muted);
            min-width: 90px;
        }}

        .detail-val {{
            color: var(--text-primary);
            font-weight: 500;
        }}

        /* Responsive */
        @media (max-width: 768px) {{
            .gallery-grid {{
                grid-template-columns: repeat(auto-fill, minmax(160px, 1fr));
                gap: 12px;
            }}
            .card-title {{
                font-size: 12px;
            }}
            .header-content {{
                flex-direction: column;
                align-items: stretch;
            }}
            .header-actions {{
                justify-content: space-between;
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
                    <h1>گالری بازبینی تصاویر شاخص دانشنامه</h1>
                    <p>۱۷۲ تصویر شاخص بازنویسی‌شده با نشان نئونی شیشه‌ای qpedia.ir</p>
                </div>
            </div>
            <div class="header-actions">
                <a href="https://github.com/lakanzino/Q-7/raw/arena/01a0bba0-q-7/downloads/qpedia-all-featured-images-master.zip" class="btn">
                    📦 دانلود افزونه جامع مستر (172 تصویر)
                </a>
                <button class="btn btn-outline" onclick="window.scrollTo({{ top: document.body.scrollHeight, behavior: 'smooth' }})">
                    فهرست ۳۵ بسته ۵ عددی ▾
                </button>
            </div>
        </div>
    </header>

    <div class="container">
        <!-- Controls & Filter -->
        <div class="controls-card">
            <div class="stats-bar">
                <div class="stat-item">کل تصاویر شاخص: <strong id="total-count">{len(images_data)}</strong> تصویر</div>
                <div class="stat-item">ابعاد استاندارد: <strong>1200x675 (16:9)</strong></div>
                <div class="stat-item">فرمت: <strong>WebP بهینه‌شده</strong></div>
                <div class="stat-item">وضعیت واترمارک: <strong style="color: var(--accent-green);">✓ درج شده (NorthEast)</strong></div>
                <div class="stat-item">تصاویر در حال نمایش: <strong id="visible-count">{len(images_data)}</strong></div>
            </div>

            <div class="filter-row">
                <div class="search-box">
                    <input type="text" id="searchInput" class="search-input" placeholder="🔍 جستجو در عنوان مقاله، اسلاگ انگلیسی یا متن جایگزین..." oninput="filterGallery()">
                </div>
                <div class="batch-filter-wrap">
                    <label for="batchSelect" style="font-size: 13px; color: var(--text-secondary);">فیلتر بر اساس بسته:</label>
                    <select id="batchSelect" class="select-filter" onchange="filterGallery()">
                        <option value="all">نمایش تمامی بسته‌ها (۱ تا ۳۵)</option>
'''

for i in range(1, 36):
    html_content += f'                        <option value="{i}">بسته {i:02d} (۵ تصویر)</option>\n'

html_content += '''                    </select>
                </div>
            </div>
        </div>

        <!-- Gallery Grid -->
        <div class="gallery-grid" id="galleryGrid">
'''

for img in images_data:
    b_num = img['batch']
    slug = img['slug']
    title = img['title']
    filename = img['filename']
    alt = img.get('alt', title)
    
    html_content += f'''            <div class="img-card" data-batch="{b_num}" data-title="{title}" data-slug="{slug}" data-alt="{alt}" onclick="openModal('{filename}', '{title}', '{slug}', '{alt}', {b_num})">
                <div class="img-frame">
                    <img src="images/{filename}" alt="{alt}" loading="lazy">
                    <span class="badge-batch">بسته {b_num:02d}</span>
                    <span class="badge-watermark">qpedia.ir</span>
                </div>
                <div class="card-body">
                    <h3 class="card-title">{title}</h3>
                    <span class="card-slug">{slug}</span>
                    <div class="card-footer">
                        <span>WebP</span>
                        <span>1200×675</span>
                    </div>
                </div>
            </div>
'''

html_content += '''        </div>
    </div>

    <!-- Modal for Zoom & Details -->
    <div class="modal-overlay" id="modalOverlay" onclick="closeModal(event)">
        <div class="modal-box" onclick="event.stopPropagation()">
            <div class="modal-header">
                <h3 id="modalTitle" style="font-size: 16px; font-weight: 700;">عنوان تصویر</h3>
                <button class="modal-close" onclick="closeModal()">&times;</button>
            </div>
            <div class="modal-img-wrap">
                <img id="modalImg" src="" alt="">
            </div>
            <div class="modal-details">
                <div class="detail-row">
                    <span class="detail-label">شناسه (اسلاگ):</span>
                    <span class="detail-val" id="modalSlug" style="direction: ltr; font-family: monospace;"></span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">نام فایل:</span>
                    <span class="detail-val" id="modalFilename" style="direction: ltr; font-family: monospace;"></span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">متن جایگزین (Alt):</span>
                    <span class="detail-val" id="modalAlt"></span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">بسته افزونه:</span>
                    <span class="detail-val" id="modalBatch"></span>
                </div>
            </div>
        </div>
    </div>

    <script>
        function filterGallery() {
            const search = document.getElementById('searchInput').value.toLowerCase().trim();
            const batch = document.getElementById('batchSelect').value;
            const cards = document.querySelectorAll('.img-card');
            let visible = 0;

            cards.forEach(card => {
                const cardBatch = card.getAttribute('data-batch');
                const title = (card.getAttribute('data-title') || '').toLowerCase();
                const slug = (card.getAttribute('data-slug') || '').toLowerCase();
                const alt = (card.getAttribute('data-alt') || '').toLowerCase();

                const matchBatch = (batch === 'all' || cardBatch === batch);
                const matchSearch = (!search || title.includes(search) || slug.includes(search) || alt.includes(search));

                if (matchBatch && matchSearch) {
                    card.style.display = 'flex';
                    visible++;
                } else {
                    card.style.display = 'none';
                }
            });

            document.getElementById('visible-count').textContent = visible;
        }

        function openModal(filename, title, slug, alt, batch) {
            document.getElementById('modalImg').src = 'images/' + filename;
            document.getElementById('modalTitle').textContent = title;
            document.getElementById('modalSlug').textContent = slug;
            document.getElementById('modalFilename').textContent = filename;
            document.getElementById('modalAlt').textContent = alt;
            document.getElementById('modalBatch').textContent = 'بسته شماره ' + batch;
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

print(f"Gallery HTML successfully generated at {output_path} with {len(images_data)} images.")
