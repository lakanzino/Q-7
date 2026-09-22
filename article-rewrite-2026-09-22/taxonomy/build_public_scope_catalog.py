"""Build the general-audience catalogue from the audited 310-item inventory."""
import csv
from collections import Counter, defaultdict
from pathlib import Path

HERE=Path(__file__).parent
source=list(csv.DictReader((HERE/'articles-001-310-taxonomy.csv').open(encoding='utf-8-sig')))

# Existing exact duplicate mergers remain excluded. Additional exclusions below were
# reviewed by title and scope, not selected merely because they sound advanced.
duplicate={114,136,282}
scientist_section={36,37,38,66,112,115,286,288}
# Requires a technical treatment (formalism, implementation detail, or specialist
# condensed-matter/optics context) to preserve the scientific point of the title.
technical={
 103,106,107,108,109,116,117,140,168,170,173,175,176,179,181,184,
 202,203,204,205,206,208,214,215,216,219,225,226,227,228,229,230,
 231,232,233,234,237,239,240,243,245,247,248,249,250,255,256,257,
 264,265,266,267,268,269,273,275,276,277,296,298,299,300,310
}
# Too narrow for a stand-alone introductory article; useful points should be folded
# into the canonical general article shown here.
merge_into={
 43:25, 57:63, 92:29, 93:30, 94:159, 99:24, 100:262, 101:127,
 105:224, 119:262, 122:212, 123:46, 162:46, 166:124, 167:11,
 169:124, 177:178, 180:26, 182:305, 183:18, 185:121, 186:131,
 187:160, 196:127, 207:284, 209:212, 210:30, 217:121, 218:121,
 222:125, 223:224, 235:291, 236:291, 241:149, 242:149, 244:238,
 246:53, 251:252, 253:252, 254:127, 258:127, 259:127, 260:127,
 261:262, 270:284, 271:305, 272:145, 274:73, 278:46, 279:63,
 280:63, 297:63
}
assert not (duplicate & scientist_section or duplicate & technical or duplicate & set(merge_into))
assert not (scientist_section & technical or scientist_section & set(merge_into))
assert not (technical & set(merge_into))

# Revised, broader subcategories. Every surviving subcategory must contain >=3 articles.
remap={
 'nuclear-physics-decay':('ذرات، هسته و واکنش‌های بنیادی','particles-nuclei-reactions'),
 'elementary-particles-standard-model':('ذرات، هسته و واکنش‌های بنیادی','particles-nuclei-reactions'),
 'photon-detection-optical-measurement':('نور، فوتون و اندازه‌گیری نوری','light-photons-optical-measurement'),
 'light-photons-light-matter':('نور، فوتون و اندازه‌گیری نوری','light-photons-optical-measurement'),
 'black-holes-horizons-information':('گرانش، فضا–زمان و سیاه‌چاله‌ها','gravity-spacetime-black-holes'),
 'quantum-gravity-spacetime':('گرانش، فضا–زمان و سیاه‌چاله‌ها','gravity-spacetime-black-holes'),
 'quantum-machine-learning-foundations':('هوش مصنوعی کوانتومی؛ مبانی و محدودیت‌ها','quantum-ai-foundations-limitations'),
 'quantum-kernels-feature-maps':('هوش مصنوعی کوانتومی؛ مبانی و محدودیت‌ها','quantum-ai-foundations-limitations'),
 'quantum-neural-generative-models':('هوش مصنوعی کوانتومی؛ مبانی و محدودیت‌ها','quantum-ai-foundations-limitations'),
 'quantum-reinforcement-intelligent-optimization':('هوش مصنوعی کوانتومی؛ مبانی و محدودیت‌ها','quantum-ai-foundations-limitations'),
 'quantum-ai-applications-limitations-claims':('هوش مصنوعی کوانتومی؛ مبانی و محدودیت‌ها','quantum-ai-foundations-limitations'),
 'quantum-sensing-metrology':('سنجش، زمان‌سنجی و ناوبری','quantum-sensing-time-navigation'),
 'atomic-clocks-navigation':('سنجش، زمان‌سنجی و ناوبری','quantum-sensing-time-navigation'),
 'quantum-radar-lidar-optical-sensing':('سنجش، زمان‌سنجی و ناوبری','quantum-sensing-time-navigation'),
 'quantum-medical-pseudoscience':('پزشکی و ارزیابی ادعاهای درمانی','quantum-medicine-claims'),
 'quantum-medicine-imaging-diagnostics':('پزشکی و ارزیابی ادعاهای درمانی','quantum-medicine-claims'),
 'quantum-many-body-thermalization':('ترمودینامیک و سامانه‌های کوانتومی','quantum-thermodynamics-systems'),
 'quantum-thermodynamics-entropy-machines':('ترمودینامیک و سامانه‌های کوانتومی','quantum-thermodynamics-systems'),
 'quantum-scientists-biographies-awards':('جایزه‌ها و دستاوردهای علمی','quantum-awards-scientific-achievements'),
 'quantum-light-sources-states':('منابع نور و فوتونیک کوانتومی','quantum-light-photonics'),
 'lasers-nonlinear-optics':('منابع نور و فوتونیک کوانتومی','quantum-light-photonics'),
}

result=[]
for r in source:
 n=int(r['sequence'])
 if n in duplicate:
  decision='ادغام تکراری'; target=int(r['canonical_sequence']); reason=r['audit_note']
 elif n in scientist_section:
  decision='انتقال به بخش دانشمندان'; target=''; reason='زندگی‌نامه، دیدگاه شخصی یا مناظره دانشمندان در بخش مستقل دانشمندان سایت پوشش داده می‌شود.'
 elif n in technical:
  decision='حذف از برنامه عمومی'; target=''; reason='حفظ ارزش علمی این موضوع به پیش‌زمینه تخصصی، صورت‌بندی ریاضی یا جزئیات فنی فراتر از هدف عمومی سایت نیاز دارد.'
 elif n in merge_into:
  decision='ادغام موضوعی'; target=merge_into[n]; reason=f'برای جلوگیری از مقاله بسیار محدود، نکات مفید در مقاله عمومی شماره {target} ادغام می‌شود.'
 else:
  decision='حفظ'; target=''; reason='قابل ارائه دقیق و عمومی بدون وابستگی به فرمول‌بندی تخصصی.'
 sub=r['subcategory']; slug=r['subcategory_slug']
 if decision=='حفظ' and slug in remap: sub,slug=remap[slug]
 result.append({**r,'public_scope_decision':decision,'merge_target':target,'public_scope_reason':reason,
                'public_subcategory':sub if decision=='حفظ' else '',
                'public_subcategory_slug':slug if decision=='حفظ' else ''})

kept=[r for r in result if r['public_scope_decision']=='حفظ']
kept_numbers={int(r['sequence']) for r in kept}
assert set(merge_into.values()) <= kept_numbers, sorted(set(merge_into.values())-kept_numbers)
assert {2,30,126} <= kept_numbers
counts=Counter(r['public_subcategory_slug'] for r in kept)
# Any residual underfilled category is folded into the closest broad category manually.
# Iterate because the remap below is based on actual post-audit counts.
small_merge={
 'light-photons-optical-measurement':('نور، لیزر و فوتونیک کوانتومی','quantum-light-lasers-photonics'),
 'quantum-light-photonics':('نور، لیزر و فوتونیک کوانتومی','quantum-light-lasers-photonics'),
 'quantum-brain-neuroscience':('شیمی، زیست‌شناسی، مغز و کوانتوم','quantum-chemistry-biology-brain'),
 'quantum-biology':('شیمی، زیست‌شناسی، مغز و کوانتوم','quantum-chemistry-biology-brain'),
 'quantum-chemistry-molecular-structure':('شیمی، زیست‌شناسی، مغز و کوانتوم','quantum-chemistry-biology-brain'),
 'quantum-thermodynamics-systems':('اثرها، انرژی و پدیده‌های کوانتومی','quantum-effects-energy-phenomena'),
 'quantum-effects-phenomena':('اثرها، انرژی و پدیده‌های کوانتومی','quantum-effects-energy-phenomena'),
 'topological-quantum-materials':('ابررسانایی و مواد کوانتومی','superconductivity-quantum-materials'),
 'superconductivity-josephson':('ابررسانایی و مواد کوانتومی','superconductivity-quantum-materials'),
 'quantum-cryptography-qkd':('رمزنگاری و امنیت در عصر کوانتوم','quantum-cryptography-security'),
 'post-quantum-security':('رمزنگاری و امنیت در عصر کوانتوم','quantum-cryptography-security'),
 'quantum-trust-cryptographic-protocols':('رمزنگاری و امنیت در عصر کوانتوم','quantum-cryptography-security'),
 'quantum-networks-repeaters-internet':('ارتباط، تله‌پورت و شبکه کوانتومی','quantum-communication-teleportation-networks'),
 'quantum-communication-teleportation':('ارتباط، تله‌پورت و شبکه کوانتومی','quantum-communication-teleportation-networks'),
 'quantum-paradoxes-thought-experiments':('تفسیر، واقعیت و مسئله ناظر','quantum-interpretation-reality-observer'),
 'reality-causality-observer':('تفسیر، واقعیت و مسئله ناظر','quantum-interpretation-reality-observer'),
 'quantum-interpretations':('تفسیر، واقعیت و مسئله ناظر','quantum-interpretation-reality-observer'),
 'quantum-states-wave-function':('حالت، تابع موج و مفاهیم پایه','quantum-state-wave-function-basics'),
 'basic-quantum-concepts':('حالت، تابع موج و مفاهیم پایه','quantum-state-wave-function-basics'),
 'photon-detection-optical-measurement':('نور، فوتون و اندازه‌گیری نوری','light-photons-optical-measurement'),
 'quantum-light-sources-states':('منابع نور و فوتونیک کوانتومی','quantum-light-photonics'),
 'quantum-fields-fundamental-forces':('ذرات، میدان‌ها، هسته و نیروها','particles-fields-nuclei-forces'),
 'quarks-hadrons-strong-force':('ذرات، میدان‌ها، هسته و نیروها','particles-fields-nuclei-forces'),
 'particles-nuclei-reactions':('ذرات، میدان‌ها، هسته و نیروها','particles-fields-nuclei-forces'),
 'quantum-algorithms':('رایانش، الگوریتم‌ها و محدودیت‌های کوانتومی','quantum-computing-algorithms-limitations'),
 'qubits-gates-circuits':('رایانش، الگوریتم‌ها و محدودیت‌های کوانتومی','quantum-computing-algorithms-limitations'),
 'quantum-software-programming-simulation':('سخت‌افزار، نرم‌افزار و محدودیت‌ها','quantum-computing-systems-limitations'),
 'quantum-computing-hardware':('سخت‌افزار، نرم‌افزار و محدودیت‌ها','quantum-computing-systems-limitations'),
 'quantum-error-control-limitations':('سخت‌افزار، نرم‌افزار و محدودیت‌ها','quantum-computing-systems-limitations'),
 'quantum-medical-pseudoscience':('پزشکی و ارزیابی ادعاهای درمانی','quantum-medicine-claims'),
 'quantum-medicine-imaging-diagnostics':('پزشکی و ارزیابی ادعاهای درمانی','quantum-medicine-claims'),
}
for r in kept:
 s=r['public_subcategory_slug']
 if s in small_merge:
  r['public_subcategory'],r['public_subcategory_slug']=small_merge[s]
 elif counts[s]<3:
  raise AssertionError(f'No editorial merge for underfilled subcategory {s}: {counts[s]}')
counts=Counter(r['public_subcategory_slug'] for r in kept)
assert min(counts.values())>=3, sorted(counts.items(),key=lambda x:x[1])

cols=list(result[0].keys())
with (HERE/'articles-public-scope-audit.csv').open('w',newline='',encoding='utf-8-sig') as f:
 w=csv.DictWriter(f,fieldnames=cols); w.writeheader(); w.writerows(result)

groups=defaultdict(list)
for r in kept: groups[(r['mother_category'],r['main_category'],r['public_subcategory'],r['public_subcategory_slug'])].append(r)
md=['# فهرست عمومی پالایش‌شده مقالات Qpedia','',
'این فهرست برای آموزش عمومی تنظیم شده است. مقاله‌های نیازمند صورت‌بندی تخصصی حذف، زندگی‌نامه‌ها به بخش مستقل دانشمندان منتقل و موضوعات بسیار محدود در مقاله‌های عمومی‌تر ادغام شده‌اند.','']
last_mother=last_main=None
for (mother,main,sub,slug),items in groups.items():
 if mother!=last_mother: md += [f'## {mother}','']; last_mother=mother; last_main=None
 if main!=last_main: md += [f'### {main}','']; last_main=main
 md += [f'#### {sub} — `{slug}` ({len(items)} مقاله)','', '| شماره | Post ID | عنوان نهایی |','|---:|---:|---|']
 for r in items: md.append(f"| {r['sequence']} | {r['post_id']} | {r['revised_title'].replace('|','—')} |")
 md.append('')
md += ['## خروجی ممیزی','', '| شماره | عنوان | تصمیم | مقصد ادغام | دلیل |','|---:|---|---|---:|---|']
for r in result:
 if r['public_scope_decision']!='حفظ':
  md.append(f"| {r['sequence']} | {r['revised_title'].replace('|','—')} | {r['public_scope_decision']} | {r['merge_target'] or '—'} | {r['public_scope_reason']} |")

summary=Counter(r['public_scope_decision'] for r in result)
mx=max(counts.values()); mn=min(counts.values())
md += ['','## آمار','',f'- ورودی: **{len(result)}**',f'- مقاله مستقل باقی‌مانده: **{len(kept)}**']
for k,v in summary.items(): md.append(f'- {k}: **{v}**')
md += [f'- تعداد زیردسته‌های عمومی نهایی: **{len(counts)}**',f'- بیشترین جمعیت زیردسته: **{mx}**',f'- کمترین جمعیت زیردسته: **{mn}**','', '### پرجمعیت‌ترین زیردسته‌ها','']
names={r['public_subcategory_slug']:r['public_subcategory'] for r in kept}
for s,c in counts.items():
 if c==mx: md.append(f'- {names[s]} — `{s}`: **{c}**')
md += ['','### کم‌جمعیت‌ترین زیردسته‌ها','']
for s,c in counts.items():
 if c==mn: md.append(f'- {names[s]} — `{s}`: **{c}**')
(HERE/'articles-public-scope-by-taxonomy.md').write_text('\n'.join(md)+'\n',encoding='utf-8')
print(dict(summary), 'kept',len(kept),'subcategories',len(counts),'min',mn,'max',mx)
