#!/usr/bin/env python3
"""Batch verification script for Incomplete Depth Third 10 Batch (119-133)"""
import json, re, os, sys
from pathlib import Path
sys.path.insert(0, str(Path(__file__).resolve().parents[0]))
from article_builder_base import count_words

ART_DIR = Path("article-rewrite-2026-09-22/articles")

BATCH_ARTICLES = [
    (119, 2228, "complementarity-principle"),
    (120, 2230, "neutrino"),
    (121, 2233, "standard-model"),
    (122, 2234, "virtual-particles"),
    (123, 2237, "epr-paradox"),
    (124, 2238, "stern-gerlach-experiment"),
    (125, 2247, "flash-memory"),
    (128, 2250, "quantum-supremacy"),
    (132, 2743, "proton-neutron-quark-structure"),
    (133, 2751, "topological-quantum-computing"),
]

all_passed = True
results = []

print("="*70)
print("BATCH VERIFICATION: Incomplete Depth Third 10 Articles")
print("="*70)

for seq, post_id, slug in BATCH_ARTICLES:
    folder = ART_DIR / f"{seq}-{slug}"
    if not folder.exists():
        print(f"[-] ERROR: Folder not found {folder}")
        all_passed = False
        continue
    
    meta_path = folder / "metadata.json"
    html_path = folder / "article.html"
    qa_path = folder / "QA.md"
    source_path = folder / "source-report.md"
    
    errors = []
    
    if not meta_path.exists(): errors.append("Missing metadata.json")
    if not html_path.exists(): errors.append("Missing article.html")
    if not qa_path.exists(): errors.append("Missing QA.md")
    if not source_path.exists(): errors.append("Missing source-report.md")
    
    if errors:
        print(f"[-] Article {seq} ({slug}): FAILED with file errors: {errors}")
        all_passed = False
        continue
        
    meta = json.loads(meta_path.read_text(encoding="utf-8"))
    html = html_path.read_text(encoding="utf-8")
    
    wc = count_words(html)
    if wc < 2200:
        errors.append(f"Word count {wc} < 2200")
        
    fk = meta.get("focus_keyword", "")
    if not fk:
        errors.append("Missing focus_keyword in meta")
    else:
        if fk not in html.split('</h1>')[0]:
            errors.append("Focus keyword not in H1")
        first_p = html.split('<p>')[1].split('</p>')[0] if '<p>' in html else ''
        if fk not in first_p:
            errors.append("Focus keyword not in first paragraph")
        if fk not in meta.get("seo_title", ""):
            errors.append("Focus keyword not in SEO title")
        if fk not in meta.get("description", ""):
            errors.append("Focus keyword not in meta description")
            
    title_len = len(meta.get("seo_title", ""))
    if title_len < 30 or title_len > 60:
        errors.append(f"SEO title length {title_len} out of bounds (30-60)")
        
    desc_len = len(meta.get("description", ""))
    if desc_len < 120 or desc_len > 155:
        errors.append(f"Meta desc length {desc_len} out of bounds (120-155)")
        
    links = re.findall(r'href="(https://qpedia\.ir/[^"/]+/?)"', html)
    if len(links) < 5:
        errors.append(f"Not enough internal links: {len(links)} < 5")
        
    for tag in ['<style', '<details', '<summary']:
        if tag in html:
            errors.append(f"Forbidden HTML tag found: {tag}")
            
    faqs = meta.get("faqs", [])
    if len(faqs) != 5:
        errors.append(f"FAQ count {len(faqs)} != 5")
        
    if '<h2>منابع و مراجع علمی</h2>' not in html and '<h2>منابع</h2>' not in html:
        errors.append("Sources section missing at end")
        
    dois = re.findall(r'10\.\d{4,9}/[-._;()/:A-Za-z0-9]+', html)
    if len(dois) < 5:
        errors.append(f"Fewer than 5 DOIs found in HTML ({len(dois)})")
        
    if errors:
        print(f"[-] Article {seq} ({slug}): FAILED ({len(errors)} errors)")
        for err in errors:
            print(f"    * {err}")
        all_passed = False
        results.append({"sequence": seq, "post_id": post_id, "slug": slug, "status": "FAILED", "errors": errors})
    else:
        print(f"[+] Article {seq} ({slug}): PASS | {wc} words | {len(links)} links | {len(faqs)} FAQs | {len(dois)} DOIs")
        results.append({"sequence": seq, "post_id": post_id, "slug": slug, "status": "PASS", "word_count": wc})

print("="*70)
print(f"OVERALL BATCH STATUS: {'PASS' if all_passed else 'FAILED'}")
print("="*70)

# Write gate json
gate_data = {
    "batch": "incomplete-depth-third-10",
    "status": "PASS" if all_passed else "FAILED",
    "importer_allowed": all_passed,
    "articles": results
}

gate_file = Path("article-rewrite-2026-09-22/batches/incomplete-depth-third-10-gate.json")
gate_file.write_text(json.dumps(gate_data, ensure_ascii=False, indent=2), encoding="utf-8")
print(f"Gate JSON saved to {gate_file}")
