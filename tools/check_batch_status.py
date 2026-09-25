#!/usr/bin/env python3
import sys
sys.path.insert(0, "tools")
from article_builder_base import count_words, ART_DIR
import re, json
from pathlib import Path

batch = [119, 120, 121, 122, 123, 124, 125, 128, 132, 133]
for seq in batch:
    folders = list(ART_DIR.glob(f"{seq}-*"))
    if not folders:
        print(f"{seq}: folder not found")
        continue
    f = folders[0]
    html_f = f / "article.html"
    if not html_f.exists():
        print(f"{seq}: article.html not found")
        continue
    html = html_f.read_text(encoding="utf-8")
    wc = count_words(html)
    dois = re.findall(r'10\.\d{4,9}/[^\s<"\']+', html)
    print(f"{seq} ({f.name}): {wc} words, {len(dois)} DOIs")
    for d in dois:
        print(f"   DOI: {d}")
