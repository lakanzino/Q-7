#!/usr/bin/env python3
"""یافتن مقالهٔ مرتبط برای هر اصطلاح واژه‌نامه بر پایهٔ جدول قطعی taxonomy."""
import csv, json, re, sys
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
BASE = ROOT / 'article-rewrite-2026-09-22'

def norm(s):
    s = re.sub(r'[\u200c\s\u0640]+', '', s or '')
    s = re.sub(r'[؟?!،؛:«»()\-\u2013\u2014\.]', '', s)
    return s.lower()

def toks(s):
    return set(re.findall(r'[\u0600-\u06ff]{3,}|[a-zA-Z]{3,}', (s or '').lower()))

TAX = list(csv.DictReader(open(BASE / 'taxonomy/articles-001-184-simple-category-map.csv', encoding='utf-8-sig')))
ART = []
for r in TAX:
    title = r['revised_title'].strip()
    base_title = re.sub(r'\s*[؟?].*$', '', title).replace(' چیست', '').strip()
    ART.append({'slug': r['slug'], 'title': title, 'base': base_title, 'url': r['url'],
                'nt': norm(title), 'nb': norm(base_title), 'tokens': toks(title + ' ' + r['slug'].replace('-', ' '))})

OVERRIDE = {
    'مکانیک کوانتومی': 'what-is-quantum',
    'فیزیک کوانتومی': 'what-is-quantum',
    'کوانتوم': 'what-is-quantum',
    'کیوبیت': 'qubit',
    'درهم‌تنیدگی کوانتومی': 'quantum-entanglement-explained',
    'برهم‌نهی کوانتومی': 'quantum-superposition',
    'دوگانگی موج و ذره': 'wave-particle-duality',
    'تابع موج': 'wave-function',
    'اندازه‌گیری کوانتومی': 'quantum-measurement',
    'اسپین': 'quantum-spin',
    'ترازهای انرژی': 'energy-levels',
    'واهمدوسی': 'decoherence',
    'ثابت پلانک': 'planck-constant',
    'آزمایش دو شکاف': 'double-slit-experiment',
    'تونل‌زنی کوانتومی': 'quantum-tunneling',
    'اثر فوتوالکتریک': 'photoelectric-effect',
    'اثر زنون کوانتومی': 'quantum-zeno-effect',
    'نوسانات خلأ': 'vacuum-fluctuations',
    'اثر کازیمیر': 'casimir-effect',
    'ابررسانایی': 'superconductivity',
    'ابرشارگی': 'superfluidity',
    'فاجعه فرابنفش': 'ultraviolet-catastrophe',
    'مدل اتمی بور': 'bohr-atomic-model',
    'اصل عدم قطعیت': 'uncertainty-principle',
    'گربه شرودینگر': 'schrodinger-cat',
    'فوتون': 'photon',
    'الکترون': 'electron',
    'اصل طرد پاولی': 'pauli-exclusion-principle',
    'آیا فیزیک کلاسیک غلط است': 'is-classical-physics-wrong',
    'آیا هوش مصنوعی از کوانتوم استفاده می‌کند': 'does-ai-use-quantum',
    'گیت CNOT': 'quantum-gate',
    'گیت هادامارد': 'quantum-gate',
    'گیت کوانتومی': 'quantum-gate',
    'کوانتوم‌واشینگ': 'quantum-ai-marketing-hype',
    'الگوریتم کوانتومی': 'quantum-algorithm',
    'الگوریتم شور': 'shor-algorithm',
    'الگوریتم گروور': 'grover-algorithm',
    'تصحیح خطای کوانتومی': 'quantum-error-correction',
    'رمزنگاری کوانتومی': 'quantum-cryptography',
    'توزیع کلید کوانتومی': 'quantum-key-distribution',
    'شبیه‌سازی کوانتومی': 'quantum-simulation',
    'ساعت اتمی': 'atomic-clock',
    'هسته اتمی': 'atomic-nucleus',
    'پیوند کووالانسی': 'covalent-bond',
    'اثر تونل مغناطیسی': 'magnetic-tunnel-junction',
    'لیزر': 'laser',
    'میکروسکوپ الکترونی': 'electron-microscope',
    'پیوند هیدروژنی': 'hydrogen-bond',
    'ابررسانای دمای بالا': 'high-temperature-superconductor',
    'محاسبات کوانتومی': 'quantum-computing',
    'رایانه کوانتومی': 'quantum-computer-reality',
    'کوانتوم‌دات': 'quantum-dot',
    'نقطه کوانتومی': 'quantum-dot',
    'طیف‌سنجی': 'spectroscopy',
    'نیمه‌رسانا': 'semiconductor',
    'دیود نورگسیل': 'led',
    'صفر مطلق': 'absolute-zero',
    'ماده تاریک': 'dark-matter',
    'انرژی تاریک': 'dark-energy',
    'مهبانگ': 'big-bang',
    'سیاه‌چاله': 'black-hole',
    'تابش هاوکینگ': 'hawking-radiation',
    'اصل هولوگرافیک': 'holographic-principle',
    'گرانش کوانتومی': 'quantum-gravity',
    'نظریه ریسمان': 'string-theory-quantum',
    'کریستال زمان': 'time-crystal',
    'داروینیسم کوانتومی': 'quantum-darwinism',
    'مرز کوانتوم و کلاسیک': 'quantum-classical-boundary',
    'شفای کوانتومی': 'quantum-healing-debunked',
    'کریستال‌درمانی': 'crystal-healing-debunked',
    'هوش مصنوعی کوانتومی': 'quantum-ai-marketing-hype',
    'یادگیری ماشین کوانتومی': 'quantum-machine-learning',
    'شیمی کوانتومی': 'quantum-chemistry',
    'اپتیک کوانتومی': 'quantum-optics',
    'الکترودینامیک کوانتومی': 'quantum-electrodynamics',
    'موسیقی کوانتومی': 'quantum-music',
    'اینترنت کوانتومی': 'quantum-internet',
    'شبکه کوانتومی': 'quantum-network',
    'تله‌پورت کوانتومی': 'quantum-teleportation',
    'نامساوی بل': 'bell-inequality',
    'آزمایش آسپه': 'aspect-experiment-1982',
    'کنفرانس سولوی': 'solvay-conference-1927',
    'نظریه متغیرهای پنهان': 'hidden-variables',
    'بوزون هیگز': 'higgs-boson',
    ' accumulator': 'what-is-quantum',
}

def pick(term):
    if term in OVERRIDE:
        slug = OVERRIDE[term]
        for a in ART:
            if a['slug'] == slug:
                return a, 'override'
    nt = norm(term)
    best, score, why = None, -1, ''
    for a in ART:
        sc = 0
        if nt == a['nb'] or nt == a['nt']:
            sc = 400
        elif nt in a['nb'] or nt in a['nt']:
            sc = 300 - min(len(a['nt']), 90) // 2
        else:
            overlap = len(toks(term) & a['tokens'])
            if overlap:
                sc = overlap * 40 - min(len(a['nt']), 90) // 4
        if sc > score:
            best, score, why = a, sc, 'fuzzy' if sc else 'none'
    return best, why

if __name__ == '__main__':
    items = json.loads((BASE / 'importers/glossary-200-replacement/qpedia-glossary-200-replacement/terms.json').read_text())
    out = []
    for x in items:
        a, why = pick(x['term'])
        out.append({'sequence': x['sequence'], 'term': x['term'], 'slug': a['slug'], 'title': a['title'], 'url': a['url'], 'how': why})
    start = int(sys.argv[1]) if len(sys.argv) > 1 else 0
    end = int(sys.argv[2]) if len(sys.argv) > 2 else len(out)
    for r in out[start:end]:
        print(f"{r['sequence']:>3} | {r['term']} | {r['slug']} | {r['how']}")
    print('total', len(out), 'no-match', sum(1 for r in out if r['how'] == 'none'))
