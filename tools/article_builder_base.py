#!/usr/bin/env python3
"""Base validation and build tools for Qpedia article rewrites."""
import json, os, re
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
ART_DIR = ROOT / 'article-rewrite-2026-09-22' / 'articles'

def count_words(text):
    clean = re.sub(r'<[^>]+>', ' ', text)
    clean = re.sub(r'[^\w\s\u0600-\u06FF]', ' ', clean)
    return len(clean.split())

def validate_article(folder, meta, html):
    problems = []
    wc = count_words(html)
    if wc < 2200:
        problems.append(f"Word count too low: {wc} words")
    
    # Check focus keyword in H1, SEO title, Description, First P
    fk = meta.get('focus_keyword', '')
    if not fk:
        problems.append("Focus keyword missing")
    else:
        if fk not in html.split('</h1>')[0]:
            problems.append(f"Focus keyword '{fk}' not in H1")
        first_p = html.split('<p>')[1].split('</p>')[0] if '<p>' in html else ''
        if fk not in first_p:
            problems.append(f"Focus keyword '{fk}' not in first paragraph")
        if fk not in meta.get('seo_title', ''):
            problems.append(f"Focus keyword '{fk}' not in SEO title")
        if fk not in meta.get('description', ''):
            problems.append(f"Focus keyword '{fk}' not in description")
    
    # SEO title length
    title_len = len(meta.get('seo_title', ''))
    if title_len < 30 or title_len > 60:
        problems.append(f"SEO title length out of bounds: {title_len} (expected 30-60)")
        
    # Meta description length
    desc_len = len(meta.get('description', ''))
    if desc_len < 120 or desc_len > 155:
        problems.append(f"Meta description length out of bounds: {desc_len} (expected 120-155)")
        
    # Internal links
    links = re.findall(r'href="(https://qpedia\.ir/[^"/]+/?)"', html)
    if len(links) < 5:
        problems.append(f"Not enough internal links: {len(links)} < 5")
        
    # Forbidden tags
    if any(tag in html for tag in ['<style', '<details', '<summary']):
        problems.append("Forbidden HTML tags found (style/details/summary)")
        
    # FAQ check
    if len(meta.get('faqs', [])) != 5:
        problems.append(f"Expected 5 FAQs in meta, got {len(meta.get('faqs', []))}")
        
    # Sources at end
    if '<h2>منابع و مراجع علمی</h2>' not in html and '<h2>منابع</h2>' not in html:
        problems.append("Sources section missing at end")
        
    dois = re.findall(r'10\.\d{4,9}/[^\s<"\']+', html)
    if len(dois) < 5:
        problems.append(f"Fewer than 5 DOIs found in HTML: {len(dois)} < 5")

    # Automatically save metadata.json and article.html to folder
    folder = Path(folder)
    folder.mkdir(parents=True, exist_ok=True)
    (folder / 'metadata.json').write_text(json.dumps(meta, ensure_ascii=False, indent=2), encoding='utf-8')
    (folder / 'article.html').write_text(html.strip(), encoding='utf-8')
        
    return problems, wc

print("Validation framework ready.")
