#!/usr/bin/env python3
"""ساخت فایل دادهٔ نهایی واژه‌نامهٔ ۲۰۰ اصطلاحی با تعریف، مثال، کاربرد، لینک داخلی و اسکیما."""
import csv, html, json, re, sys
from pathlib import Path

sys.path.insert(0, str(Path(__file__).resolve().parent))
from glossary_data_1 import RENAMES, SLUG_MAP           # noqa: E402
from glossary_data_2 import APPLICATION, DEFAULT_CATEGORY, EXAMPLES_1, SLUG_MAP_2  # noqa: E402
from glossary_data_3 import EXAMPLES_2                  # noqa: E402
from glossary_data_4 import DEFINITIONS, MISCONCEPTION  # noqa: E402

ROOT = Path(__file__).resolve().parents[1]
BASE = ROOT / 'article-rewrite-2026-09-22'
SRC = BASE / 'importers/glossary-200-replacement/qpedia-glossary-200-replacement'
OLD_TERMS = json.loads((BASE / 'importers/glossary-100/qpedia-glossary-100-importer/terms.json').read_text())
CURRENT = json.loads((SRC / 'terms.json').read_text()) if (SRC / 'terms.json').exists() else []

TAX = list(csv.DictReader(open(BASE / 'taxonomy/articles-001-184-simple-category-map.csv', encoding='utf-8-sig')))
ART = {r['slug']: r for r in TAX}
BY_CATEGORY = {}
for r in TAX:
    BY_CATEGORY.setdefault(r['simple_category'], []).append(r)

SLUGS = dict(SLUG_MAP)
SLUGS.update(SLUG_MAP_2)
EXAMPLES = dict(EXAMPLES_1)
EXAMPLES.update(EXAMPLES_2)

ALIASES = {
    'تله‌پورت کوانتومی': ['تلپورت کوانتومی', 'تله پورت کوانتومی'],
    'کوانتوم‌واشینگ': ['کوانتوم واشینگ', 'کوانتوم‌واش‌ینگ'],
    'پروتکل BB84': ['بی‌بی۸۴', 'BB84'],
    'گیت CNOT': ['CNOT', 'گیت کنترل‌نات'],
    'گیت هادامارد': ['هادامارد'],
    'حد کلاسیک': ['حد کلاسیکی'],
    'اصل طرد پاولی': ['اصل پاولی', 'طرد پاولی'],
    'قضیه عدم‌کپی کوانتومی': ['قضیه عدم کپی', 'عدم‌کپی'],
    'دوست ویگنر': ['همراه ویگنر'],
    'کیوبیت': ['کیوبیت‌ها'],
    'حس مغناطیسی': ['مغناطیس‌یابی زیستی'],
    'اثر هال کوانتومی': ['هال کوانتومی'],
    'توموگرافی کوانتومی': ['توموگرافی حالت'],
    'معیارسنجی تصادفی': ['بنچمارک تصادفی'],
    'کد سطحی': ['کد سطوح', 'surface code'],
    'کره بلاخ': ['کرهٔ بلاخ'],
    'افت‌وخیز کوانتومی': ['نوسان کوانتومی'],
    'فرمیون': ['فرمیون‌ها'],
    'بوزون': ['بوزون‌ها'],
    'گرافن': ['گرافین'],
    'چاه کوانتومی': ['چاه‌های کوانتومی'],
}

DEFS = {x['term']: x['definition'] for x in OLD_TERMS}
# تعریف اصطلاح‌های تازه از نسخهٔ پیشین همین بسته
for x in CURRENT:
    DEFS.setdefault(x['term'], x['definition'])

def term_list():
    if CURRENT and len(CURRENT) == 200:
        slots = [x['term'] for x in CURRENT]
    else:
        slots = [x['term'] for x in OLD_TERMS]
    out = []
    for t in slots:
        new = RENAMES.get(t)
        out.append(new[0] if new else t)
    return out

def definition_of(term):
    if term in DEFINITIONS:
        return DEFINITIONS[term].rstrip(' .') + '.'
    for old, (new, definition) in RENAMES.items():
        if new == term:
            return definition.rstrip(' .') + '.'
    d = DEFS.get(term)
    if not d:
        raise SystemExit('تعریف پیدا نشد: ' + term)
    return d.rstrip(' .') + '.'

TITLE_SUFFIXES = [
    ' چیست؟',
    ' چیست؟ تعریف ساده',
    ' چیست؟ تعریف ساده و کاربرد',
    ' چیست؟ تعریف ساده، مثال و کاربرد',
    ' چیست؟ تعریف روان، مثال و کاربرد',
    ' چیست؟ تعریف ساده، مثال و کاربردهای مهم',
    ' چیست؟ تعریف روان، مثال روزمره و کاربردهای واقعی',
    ' چیست؟ معنی ساده، مثال روزمره و کاربرد در کوانتوم',
    ' چیست؟ تعریف دقیق و ساده، مثال روزمره و کاربردهای علمی',
    ' چیست؟ تعریف دقیق و ساده، مثال روزمره، کاربرد و ارتباط با مفاهیم دیگر',
    ' چیست؟ تعریف روان و دقیق، مثال روزمره، کاربرد واقعی و ارتباط آن با مفاهیم کوانتومی',
]

def seo_title(term):
    fits = [(abs(55 - len(term + s)), term + s) for s in TITLE_SUFFIXES if 50 <= len(term + s) <= 60]
    if not fits:
        return None
    fits.sort(key=lambda x: (x[0], len(x[1])))
    return fits[0][1]

META_TAILS = [
    ' مثال آن را ببینید.',
    ' کاربرد آن را بخوانید.',
    ' تعریف کامل آن در واژه‌نامه آمده است.',
    ' مثال روزمره و مرز تمثیل را ببینید.',
    ' مثال و کاربرد ساده را در واژه‌نامهٔ Qpedia بخوانید.',
    ' تعریف روان و کاربرد آن را در واژه‌نامهٔ Qpedia بخوانید.',
    ' تعریف کامل‌تر، مثال روزمره و کاربرد آن را در واژه‌نامهٔ کوانتوم Qpedia بخوانید.',
    ' مثال روزمره، کاربرد و ارتباط آن با مفاهیم دیگر را در واژه‌نامهٔ کوانتوم Qpedia بخوانید.',
]

def meta_description(term, definition):
    head = re.sub(r'\s+', ' ', f'{term} چیست؟ {definition}').strip()
    if len(head) > 150:
        head = head[:150].rsplit(' ', 1)[0].rstrip(' ،؛:.') + '.'
    core = head
    if len(core) < 120:
        need = 120 - len(core)
        options = [t for t in META_TAILS if need <= len(t) <= 155 - len(core)]
        if options:
            core = core.rstrip('.') + '.' + min(options, key=len)
    if len(core) > 155:
        core = core[:152].rsplit(' ', 1)[0].rstrip(' ،؛:.') + '.'
    if not core.endswith('.'):
        core += '.'
    return core

def related_articles(primary_slug):
    primary = ART[primary_slug]
    cat = primary['simple_category']
    siblings = [r for r in BY_CATEGORY.get(cat, []) if r['slug'] != primary_slug]
    picked = siblings[:2]
    if len(picked) < 2:
        extra = [r for r in TAX if r['slug'] != primary_slug and r not in picked][: 2 - len(picked)]
        picked += extra
    return picked

def build_content(term, definition, slug, example):
    a = ART[slug]
    analogy, boundary = example
    cat = a['simple_category']
    app = APPLICATION.get(cat, APPLICATION[DEFAULT_CATEGORY])
    mistake = MISCONCEPTION.get(cat, MISCONCEPTION['مفاهیم پایه']).format(term=html.escape(term))
    links = [a] + related_articles(slug)
    link_html = '، '.join(f'<a href="{x["url"]}">{html.escape(x["revised_title"])}</a>' for x in links)
    return (
        f'<p><strong>{html.escape(term)}</strong> {html.escape(definition)}</p>'
        f'<h2>تعریف ساده</h2>'
        f'<p>{html.escape(term)} یکی از اصطلاح‌های کلیدی است که در دستهٔ «{html.escape(cat)}» معنا پیدا می‌کند. '
        f'برای فهم دقیق آن باید به یاد داشته باشیم که هر اصطلاح کوانتومی به یک نتیجهٔ اندازه‌گیری‌شده یا یک رابطهٔ ریاضی آزمون‌پذیر اشاره دارد، '
        f'نه به یک برداشت شخصی یا تبلیغاتی.</p>'
        f'<h2>مثال روزمره و مرز تمثیل</h2>'
        f'<p>برای تصور اولیه می‌توان گفت {html.escape(term)} {html.escape(analogy)}. '
        f'این تمثیل فقط برای آشنایی ذهن با موضوع است؛ مرز شکست آن این است که {html.escape(boundary)}.</p>'
        f'<h2>کاربرد</h2>'
        f'<p>{app}. در همین چارچوب، {html.escape(term)} برای خواندن درست مقاله‌های مرتبط و سنجش ادعاهای روزمره به کار می‌آید. '
        f'کاربرد عملی آن همیشه به شرایط آزمایش، مقیاس سامانه و دقت اندازه‌گیری وابسته است.</p>'
        f'<h2>اشتباه رایج</h2>'
        f'<p>{mistake}</p>'
        f'<h2>ارتباط با مفاهیم دیگر</h2>'
        f'<p>{html.escape(term)} بدون پیوند با مفاهیم مجاورش کامل فهمیده نمی‌شود. '
        f'برای مطالعهٔ بیشتر این مقاله‌ها را ببینید: {link_html}. '
        f'فهرست کامل اصطلاح‌ها نیز در <a href="{html.escape("https://qpedia.ir/glossary/")}">واژه‌نامهٔ کوانتوم Qpedia</a> در دسترس است.</p>'
    )

def build():
    terms = term_list()
    assert len(terms) == 200, len(terms)
    assert len(set(terms)) == 200, 'اصطلاح تکراری'
    items = []
    for i, term in enumerate(terms, 1):
        if term not in SLUGS:
            raise SystemExit('نقشهٔ مقاله پیدا نشد: ' + term)
        if term not in EXAMPLES:
            raise SystemExit('مثال پیدا نشد: ' + term)
        slug = SLUGS[term]
        definition = definition_of(term)
        seo = seo_title(term)
        if not seo:
            raise SystemExit('عنوان متا در محدودهٔ ۵۰ تا ۶۰ کاراکتر جا نشد: ' + term)
        content = build_content(term, definition, slug, EXAMPLES[term])
        desc = meta_description(term, definition)
        items.append({
            'sequence': i,
            'term': term,
            'definition': definition,
            'focus_keyword': term,
            'seo_title': seo,
            'meta_description': desc,
            'related_url': ART[slug]['url'],
            'related_title': ART[slug]['revised_title'],
            'category': ART[slug]['simple_category'],
            'aliases': ALIASES.get(term, []),
            'content': content,
            'schema': {
                '@context': 'https://schema.org',
                '@type': 'DefinedTerm',
                'name': term,
                'description': definition,
                'inDefinedTermSet': 'https://qpedia.ir/glossary/',
            },
        })
    SRC.mkdir(parents=True, exist_ok=True)
    (SRC / 'terms.json').write_text(json.dumps(items, ensure_ascii=False, indent=2) + '\n')
    print('terms', len(items))
    return items

if __name__ == '__main__':
    build()
