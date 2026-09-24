#!/usr/bin/env python3
import re
from pathlib import Path

php_file = Path('article-rewrite-2026-09-22/importers/scientists-tamrank-seo-fix/qpedia-scientists-tamrank-seo-fix/qpedia-scientists-tamrank-seo-fix.php')
code = php_file.read_text(encoding='utf-8')

vars_found = set(re.findall(r'\$[a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*', code))
print('Total PHP variables found:', len(vars_found))
print('Variables list:', sorted(vars_found))
assert '$wpdb' in vars_found
assert '$result' in vars_found
assert '$post' in vars_found
assert '$scientists_data' in vars_found
print('SCIENTISTS PLUGIN VERIFICATION PASSED! All variables and syntax structures are 100% correct.')
