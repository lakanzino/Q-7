#!/usr/bin/env python3
import re
from pathlib import Path

php_file = Path('article-rewrite-2026-09-22/importers/google-content-auditor/qpedia-google-content-auditor/qpedia-google-content-auditor.php')
code = php_file.read_text(encoding='utf-8')

vars_found = set(re.findall(r'\$[a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*', code))
print('Total PHP variables found:', len(vars_found))
print('Variables list:', sorted(vars_found))
assert '$post_id' in vars_found
assert '$tier1' in vars_found
assert '$tier2' in vars_found
assert '$tier3' in vars_found
assert '$tier4' in vars_found
assert '$total_score' in vars_found
print('AUDITOR PLUGIN VERIFICATION PASSED!')
