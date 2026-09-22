import csv, re
from pathlib import Path

ROOT=Path(__file__).resolve().parents[1]
rows=list(csv.DictReader((ROOT/'articles-001-310.csv').open(encoding='utf-8-sig')))
for row in rows:
 row['sequence']=row.pop('number')
# Audited duplicate mergers. Canonical article remains in the catalogue.
removed={
 114:('ادغام تکراری',126,'موضوع و دامنه با «اصل طرد پاولی دقیقاً چه می‌گوید؟» یکسان است.'),
 136:('ادغام تکراری',2,'موضوع و دامنه با مقاله «برهم‌نهی کوانتومی» یکسان است.'),
 282:('ادغام تکراری',30,'موضوع و دامنه با «آیا مغز کوانتومی است؟» یکسان است.'),
}

subs={
'basic-quantum-concepts':('دانش بنیادی کوانتوم','مبانی فیزیک کوانتومی','مفاهیم و کمیت‌های پایه'),
'quantum-states-wave-function':('دانش بنیادی کوانتوم','مبانی فیزیک کوانتومی','حالت کوانتومی و تابع موج'),
'measurement-probability-uncertainty':('دانش بنیادی کوانتوم','مبانی فیزیک کوانتومی','اندازه‌گیری، احتمال و عدم‌قطعیت'),
'superposition-entanglement-coherence':('دانش بنیادی کوانتوم','مبانی فیزیک کوانتومی','برهم‌نهی، درهم‌تنیدگی و همدوسی'),
'quantum-effects-phenomena':('دانش بنیادی کوانتوم','مبانی فیزیک کوانتومی','اثرها و پدیده‌های کوانتومی'),
'elementary-particles-standard-model':('دانش بنیادی کوانتوم','ذرات، میدان‌ها و فیزیک هسته‌ای','ذرات بنیادی و مدل استاندارد'),
'quarks-hadrons-strong-force':('دانش بنیادی کوانتوم','ذرات، میدان‌ها و فیزیک هسته‌ای','کوارک‌ها، هادرون‌ها و نیروی قوی'),
'quantum-fields-fundamental-forces':('دانش بنیادی کوانتوم','ذرات، میدان‌ها و فیزیک هسته‌ای','میدان‌های کوانتومی و نیروهای بنیادی'),
'nuclear-physics-decay':('دانش بنیادی کوانتوم','ذرات، میدان‌ها و فیزیک هسته‌ای','هسته، واپاشی و واکنش‌های هسته‌ای'),
'antimatter-dark-matter-hypothetical-particles':('دانش بنیادی کوانتوم','ذرات، میدان‌ها و فیزیک هسته‌ای','پادماده، ماده تاریک و ذرات فرضی'),
'quantum-interpretations':('دانش بنیادی کوانتوم','تفسیرها، فلسفه و مرزهای واقعیت','تفسیرهای مکانیک کوانتومی'),
'quantum-paradoxes-thought-experiments':('دانش بنیادی کوانتوم','تفسیرها، فلسفه و مرزهای واقعیت','پارادوکس‌ها و آزمایش‌های فکری'),
'reality-causality-observer':('دانش بنیادی کوانتوم','تفسیرها، فلسفه و مرزهای واقعیت','واقعیت، علیت و مسئله ناظر'),
'time-free-will-consciousness':('دانش بنیادی کوانتوم','تفسیرها، فلسفه و مرزهای واقعیت','زمان، اختیار و آگاهی'),
'multiverse-reality-hypotheses':('دانش بنیادی کوانتوم','تفسیرها، فلسفه و مرزهای واقعیت','چندجهانی و فرضیه‌های واقعیت'),
'superconductivity-josephson':('جهان کوانتومی و ساختار ماده','ماده چگال و مواد کوانتومی','ابررسانایی و پیوند جوزفسون'),
'superfluidity-condensates-quantum-gases':('جهان کوانتومی و ساختار ماده','ماده چگال و مواد کوانتومی','ابرشارگی، چگالش و گازهای کوانتومی'),
'topological-quantum-materials':('جهان کوانتومی و ساختار ماده','ماده چگال و مواد کوانتومی','مواد و فازهای توپولوژیک'),
'quantum-magnetism-spin-systems':('جهان کوانتومی و ساختار ماده','ماده چگال و مواد کوانتومی','مغناطیس و سامانه‌های اسپینی'),
'quasiparticles-collective-excitations':('جهان کوانتومی و ساختار ماده','ماده چگال و مواد کوانتومی','شبه‌ذرات و برانگیختگی‌های جمعی'),
'light-photons-light-matter':('جهان کوانتومی و ساختار ماده','اپتیک کوانتومی و فوتونیک','نور، فوتون و برهم‌کنش نور و ماده'),
'lasers-nonlinear-optics':('جهان کوانتومی و ساختار ماده','اپتیک کوانتومی و فوتونیک','لیزرها و اپتیک غیرخطی'),
'quantum-light-sources-states':('جهان کوانتومی و ساختار ماده','اپتیک کوانتومی و فوتونیک','منابع و حالت‌های نور کوانتومی'),
'photon-detection-optical-measurement':('جهان کوانتومی و ساختار ماده','اپتیک کوانتومی و فوتونیک','آشکارسازی و اندازه‌گیری نور'),
'quantum-imaging-microscopy-lithography':('جهان کوانتومی و ساختار ماده','اپتیک کوانتومی و فوتونیک','تصویربرداری، میکروسکوپی و لیتوگرافی'),
'quantum-gravity-spacetime':('جهان کوانتومی و ساختار ماده','کیهان، گرانش و ترمودینامیک کوانتومی','گرانش کوانتومی و فضا–زمان'),
'black-holes-horizons-information':('جهان کوانتومی و ساختار ماده','کیهان، گرانش و ترمودینامیک کوانتومی','سیاه‌چاله‌ها، افق و اطلاعات'),
'quantum-cosmology-early-universe':('جهان کوانتومی و ساختار ماده','کیهان، گرانش و ترمودینامیک کوانتومی','کیهان‌شناسی و جهان آغازین'),
'quantum-thermodynamics-entropy-machines':('جهان کوانتومی و ساختار ماده','کیهان، گرانش و ترمودینامیک کوانتومی','ترمودینامیک، آنتروپی و ماشین‌های کوانتومی'),
'quantum-many-body-thermalization':('جهان کوانتومی و ساختار ماده','کیهان، گرانش و ترمودینامیک کوانتومی','سامانه‌های چندجسمی و گرمایی‌شدن'),
'qubits-gates-circuits':('اطلاعات و فناوری کوانتومی','رایانش کوانتومی','کیوبیت، گیت و مدار کوانتومی'),
'quantum-algorithms':('اطلاعات و فناوری کوانتومی','رایانش کوانتومی','الگوریتم‌های کوانتومی'),
'quantum-computing-hardware':('اطلاعات و فناوری کوانتومی','رایانش کوانتومی','سخت‌افزار و معماری رایانه کوانتومی'),
'quantum-error-control-limitations':('اطلاعات و فناوری کوانتومی','رایانش کوانتومی','تصحیح خطا، کنترل و محدودیت‌ها'),
'quantum-software-programming-simulation':('اطلاعات و فناوری کوانتومی','رایانش کوانتومی','نرم‌افزار، برنامه‌نویسی و شبیه‌سازی'),
'quantum-machine-learning-foundations':('اطلاعات و فناوری کوانتومی','هوش مصنوعی و یادگیری ماشین کوانتومی','مبانی یادگیری ماشین کوانتومی'),
'quantum-kernels-feature-maps':('اطلاعات و فناوری کوانتومی','هوش مصنوعی و یادگیری ماشین کوانتومی','هسته‌ها و نگاشت ویژگی کوانتومی'),
'quantum-neural-generative-models':('اطلاعات و فناوری کوانتومی','هوش مصنوعی و یادگیری ماشین کوانتومی','شبکه‌های عصبی و مدل‌های مولد کوانتومی'),
'quantum-reinforcement-intelligent-optimization':('اطلاعات و فناوری کوانتومی','هوش مصنوعی و یادگیری ماشین کوانتومی','یادگیری تقویتی و بهینه‌سازی هوشمند'),
'quantum-ai-applications-limitations-claims':('اطلاعات و فناوری کوانتومی','هوش مصنوعی و یادگیری ماشین کوانتومی','کاربردها، محدودیت‌ها و ادعاهای هوش مصنوعی'),
'quantum-communication-teleportation':('اطلاعات و فناوری کوانتومی','ارتباطات، شبکه و امنیت کوانتومی','ارتباط و تله‌پورت کوانتومی'),
'quantum-cryptography-qkd':('اطلاعات و فناوری کوانتومی','ارتباطات، شبکه و امنیت کوانتومی','رمزنگاری و توزیع کلید کوانتومی'),
'quantum-networks-repeaters-internet':('اطلاعات و فناوری کوانتومی','ارتباطات، شبکه و امنیت کوانتومی','شبکه، ریپیتر و اینترنت کوانتومی'),
'post-quantum-security':('اطلاعات و فناوری کوانتومی','ارتباطات، شبکه و امنیت کوانتومی','امنیت و رمزنگاری پساکوانتومی'),
'quantum-trust-cryptographic-protocols':('اطلاعات و فناوری کوانتومی','ارتباطات، شبکه و امنیت کوانتومی','پروتکل‌های اعتماد و کاربردهای رمزنگاری'),
'quantum-sensing-metrology':('کوانتوم در علم، فناوری و جامعه','اندازه‌گیری، حسگرها و فناوری‌های کوانتومی','سنجش و مترولوژی کوانتومی'),
'atomic-clocks-navigation':('کوانتوم در علم، فناوری و جامعه','اندازه‌گیری، حسگرها و فناوری‌های کوانتومی','زمان‌سنجی، ساعت اتمی و ناوبری'),
'quantum-radar-lidar-optical-sensing':('کوانتوم در علم، فناوری و جامعه','اندازه‌گیری، حسگرها و فناوری‌های کوانتومی','رادار، لیدار و سنجش نوری'),
'quantum-electronics-everyday-technology':('کوانتوم در علم، فناوری و جامعه','اندازه‌گیری، حسگرها و فناوری‌های کوانتومی','الکترونیک و فناوری‌های روزمره'),
'quantum-laboratory-infrastructure':('کوانتوم در علم، فناوری و جامعه','اندازه‌گیری، حسگرها و فناوری‌های کوانتومی','تجهیزات و زیرساخت آزمایشگاهی'),
'quantum-chemistry-molecular-structure':('کوانتوم در علم، فناوری و جامعه','شیمی، زیست‌شناسی و پزشکی کوانتومی','شیمی کوانتومی و ساختار مولکولی'),
'quantum-biology':('کوانتوم در علم، فناوری و جامعه','شیمی، زیست‌شناسی و پزشکی کوانتومی','زیست‌شناسی کوانتومی'),
'quantum-brain-neuroscience':('کوانتوم در علم، فناوری و جامعه','شیمی، زیست‌شناسی و پزشکی کوانتومی','مغز، آگاهی و علوم اعصاب'),
'quantum-medicine-imaging-diagnostics':('کوانتوم در علم، فناوری و جامعه','شیمی، زیست‌شناسی و پزشکی کوانتومی','پزشکی، تصویربرداری و تشخیص'),
'quantum-medical-pseudoscience':('کوانتوم در علم، فناوری و جامعه','شیمی، زیست‌شناسی و پزشکی کوانتومی','ادعاهای درمانی و شبه‌علم پزشکی'),
'quantum-historical-experiments-events':('کوانتوم در علم، فناوری و جامعه','تاریخ، آموزش و جامعه کوانتومی','آزمایش‌ها و رویدادهای تاریخی'),
'quantum-scientists-biographies-awards':('کوانتوم در علم، فناوری و جامعه','تاریخ، آموزش و جامعه کوانتومی','دانشمندان، زندگی‌نامه‌ها و جایزه‌ها'),
'quantum-learning-resources':('کوانتوم در علم، فناوری و جامعه','تاریخ، آموزش و جامعه کوانتومی','آموزش، منابع و مسیر یادگیری'),
'scientific-literacy-pseudoscience':('کوانتوم در علم، فناوری و جامعه','تاریخ، آموزش و جامعه کوانتومی','سواد علمی و تشخیص شبه‌علم'),
'quantum-culture-media-industry-future':('کوانتوم در علم، فناوری و جامعه','تاریخ، آموزش و جامعه کوانتومی','فرهنگ، رسانه، صنعت و آینده کوانتوم'),
}

# Explicit editorial placement. Every retained sequence must occur exactly once.
groups={
'basic-quantum-concepts':[1,3,6,7,9,19,20,41,47,130,135,138,141,145,147,292,301],
'quantum-states-wave-function':[4,113,202,203],
'measurement-probability-uncertainty':[5,14,80,142,146,216,219,220],
'superposition-entanglement-coherence':[2,8,11,21,40,77,97,98,129,137,139,151,152,214,215,281,285,302],
'quantum-effects-phenomena':[10,12,15,16,42,104,105,121,140,150,185,217,218,223,249,307],
'elementary-particles-standard-model':[90,91,124,125,148,149,222,293,294],
'quarks-hadrons-strong-force':[160,187,225],
'quantum-fields-fundamental-forces':[43,224,226],
'nuclear-physics-decay':[306],
'antimatter-dark-matter-hypothetical-particles':[56,188,189,241,295],
'quantum-interpretations':[22,107,108,109,116,117,131,158],
'quantum-paradoxes-thought-experiments':[87,121,185,215,217,218],
'reality-causality-observer':[48,66,115,128,133],
'time-free-will-consciousness':[49,69,88,186],
'multiverse-reality-hypotheses':[23,35,44,89],
'superconductivity-josephson':[17,71,81,85,106],
'superfluidity-condensates-quantum-gases':[18,53,181,182,183,305],
'topological-quantum-materials':[62,238,239,240,242,243,244,245,246],
'quantum-magnetism-spin-systems':[179,267,268,269],
'quasiparticles-collective-excitations':[180,184,273,274,275,276,277,278],
'light-photons-light-matter':[13,73,171,247,248,250],
'lasers-nonlinear-optics':[25,168,204,205],
'quantum-light-sources-states':[166,167,170],
'photon-detection-optical-measurement':[169,206],
'quantum-imaging-microscopy-lithography':[164,172,251,252,253],
'quantum-gravity-spacetime':[50,52,193,201,221],
'black-holes-horizons-information':[64],
'quantum-cosmology-early-universe':[58,190,191,192,194,195],
'quantum-thermodynamics-entropy-machines':[63,207,208,279,280,296,297],
'quantum-many-body-thermalization':[298,299,300],
'qubits-gates-circuits':[24,99,102,214,308],
'quantum-algorithms':[103,157,227,228,229,230,264,265,266,303],
'quantum-computing-hardware':[55,74,84,120,156,161,163,176,177,178,291],
'quantum-error-control-limitations':[72,78,200,237,304],
'quantum-software-programming-simulation':[235,236,263,309],
'quantum-machine-learning-foundations':[60],
'quantum-kernels-feature-maps':[231],
'quantum-neural-generative-models':[232,233],
'quantum-reinforcement-intelligent-optimization':[234],
'quantum-ai-applications-limitations-claims':[111,132],
'quantum-communication-teleportation':[34,65,174,310],
'quantum-cryptography-qkd':[101,127,173,255,256,257],
'quantum-networks-repeaters-internet':[59,100,119,261,262],
'post-quantum-security':[45,75,76],
'quantum-trust-cryptographic-protocols':[175,196,254,258,259,260],
'quantum-sensing-metrology':[284],
'atomic-clocks-navigation':[27,86],
'quantum-radar-lidar-optical-sensing':[61,165],
'quantum-electronics-everyday-technology':[26,57,153,154,155,289],
'quantum-laboratory-infrastructure':[270,271,272],
'quantum-chemistry-molecular-structure':[46,162],
'quantum-biology':[29,92,94,122,123,159,209,212,213],
'quantum-brain-neuroscience':[30,93,210],
'quantum-medicine-imaging-diagnostics':[31,32,56,162,211],
'quantum-medical-pseudoscience':[51,110],
'quantum-historical-experiments-events':[36,39,40,54,77,79,95,96,97,98,118,129,151,152,288],
'quantum-scientists-biographies-awards':[37,38,54,71,95,96,112,113,114,115,126,139,286,288],
'quantum-learning-resources':[67,134,143,144,283,287,290],
'scientific-literacy-pseudoscience':[28,33,51,70,82,83,110,128],
'quantum-culture-media-industry-future':[57,59,61,68,72,73,74,75,76,78,79,81,83,89,111,134,197,198,199,200,201],
}
# A few articles naturally touch multiple fields. Resolve duplicates with an explicit primary-category policy:
# the last occurrence in this editorial priority list wins only where listed below.
primary_override={
40:'quantum-historical-experiments-events',54:'quantum-scientists-biographies-awards',56:'quantum-medicine-imaging-diagnostics',
57:'quantum-electronics-everyday-technology',59:'quantum-networks-repeaters-internet',61:'quantum-radar-lidar-optical-sensing',
71:'quantum-scientists-biographies-awards',72:'quantum-culture-media-industry-future',73:'quantum-electronics-everyday-technology',
74:'quantum-computing-hardware',75:'post-quantum-security',76:'post-quantum-security',77:'quantum-historical-experiments-events',
78:'quantum-culture-media-industry-future',79:'quantum-historical-experiments-events',81:'superconductivity-josephson',
83:'scientific-literacy-pseudoscience',89:'multiverse-reality-hypotheses',95:'quantum-scientists-biographies-awards',
96:'quantum-scientists-biographies-awards',97:'quantum-historical-experiments-events',98:'quantum-historical-experiments-events',
110:'quantum-medical-pseudoscience',111:'quantum-ai-applications-limitations-claims',113:'quantum-states-wave-function',
114:'quantum-scientists-biographies-awards',115:'reality-causality-observer',121:'quantum-paradoxes-thought-experiments',
126:'basic-quantum-concepts',128:'scientific-literacy-pseudoscience',129:'quantum-historical-experiments-events',
134:'quantum-learning-resources',139:'basic-quantum-concepts',151:'quantum-historical-experiments-events',
152:'quantum-historical-experiments-events',162:'quantum-chemistry-molecular-structure',185:'quantum-paradoxes-thought-experiments',
201:'quantum-gravity-spacetime',214:'superposition-entanglement-coherence',
}
assignment={}
for slug,nums in groups.items():
 for n in nums:
  if n not in assignment: assignment[n]=slug
for n,slug in primary_override.items(): assignment[n]=slug
for n in removed: assignment.pop(n,None)
expected=set(range(1,311))-set(removed)
missing=sorted(expected-set(assignment)); extra=sorted(set(assignment)-expected)
assert not missing, f'Missing: {missing}'
assert not extra, f'Extra: {extra}'
assert set(assignment.values())<=set(subs)

# Scientifically safer title corrections. These do not invent a new subject.
corrections={
9:'ثابت پلانک چیست؟ مقیاس بنیادی کوانتومی، نه کوچک‌ترین واحد جهان',
29:'تونل‌زنی کوانتومی در آنزیم‌ها؛ چه شواهدی داریم؟',
54:'نوبل فیزیک ۲۰۲۳؛ پالس‌های آتوثانیه‌ای و حرکت الکترون‌ها',
57:'باتری کوانتومی چیست؟ ظرفیت نظری و محدودیت‌های واقعی',
80:'مولد عدد تصادفی کوانتومی؛ آیا تصادف بنیادی قابل استفاده است؟',
99:'سه گونه کیوبیت؛ ابررسانا، یون به‌دام‌افتاده و فوتون',
121:'گربه شرودینگر؛ آزمایش فکری درباره اندازه‌گیری کوانتومی',
129:'آزمایش‌های بل؛ شواهد تجربی علیه واقع‌گرایی موضعی',
156:'مزیت کوانتومی چیست؟ تفاوت آن با برتری مطلق رایانه‌ها',
164:'روشن‌سازی کوانتومی؛ تشخیص هدف در محیط پرنویز',
267:'فرمیون سنگین؛ شبه‌ذره‌ای با جرم مؤثر بسیار بزرگ',
270:'اسکویید؛ حسگر بسیار حساس شار مغناطیسی',
}

out=[]
for r in rows:
 n=int(r['sequence'])
 status='حذف/ادغام' if n in removed else ('اصلاح عنوان' if n in corrections else 'حفظ')
 slug=assignment.get(n,'')
 mother=main=sub=''
 if slug: mother,main,sub=subs[slug]
 out.append({**r,'audit_status':status,'revised_title':corrections.get(n,r['title']),
             'mother_category':mother,'main_category':main,'subcategory':sub,'subcategory_slug':slug,
             'canonical_sequence':removed.get(n,('', '', ''))[1] if n in removed else '',
             'audit_note':removed.get(n,('', '', ''))[2] if n in removed else ('اصلاح علمی عنوان؛ موضوع مقاله حفظ می‌شود.' if n in corrections else '')})

cols=['sequence','post_id','status','title','slug','url','post_date','audit_status','revised_title','mother_category','main_category','subcategory','subcategory_slug','canonical_sequence','audit_note']
with (Path(__file__).parent/'articles-001-310-taxonomy.csv').open('w',newline='',encoding='utf-8-sig') as f:
 w=csv.DictWriter(f,fieldnames=cols); w.writeheader(); w.writerows(out)

active=[x for x in out if x['audit_status']!='حذف/ادغام']
from collections import Counter
counts=Counter(x['subcategory_slug'] for x in active)
md=['# جای‌گذاری و ممیزی ۳۱۰ مقاله در درخت دسته‌بندی','',
'این فهرست بر اساس موضوع اصلی هر مقاله تنظیم شده است. هر مقالهٔ باقی‌مانده فقط یک مسیر اصلی دارد. عناوین ادغامی در انتهای سند آمده‌اند.','']
for mother in dict.fromkeys(v[0] for v in subs.values()):
 md += [f'## {mother}','']
 for main in dict.fromkeys(v[1] for v in subs.values() if v[0]==mother):
  md += [f'### {main}','']
  for slug,(mo,ma,sub) in subs.items():
   if mo!=mother or ma!=main: continue
   items=[x for x in active if x['subcategory_slug']==slug]
   md += [f'#### {sub} — `{slug}` ({len(items)} مقاله)','', '| شماره | Post ID | عنوان نهایی | وضعیت ممیزی |','|---:|---:|---|---|']
   for x in items:
    md.append(f"| {x['sequence']} | {x['post_id']} | {x['revised_title'].replace('|','—')} | {x['audit_status']} |")
   if not items: md.append('| — | — | بدون مقاله | — |')
   md.append('')
md += ['## مقاله‌های ادغام‌شده و خارج‌شده از فهرست مستقل','', '| شماره | عنوان | ادغام با | دلیل |','|---:|---|---:|---|']
for x in out:
 if x['audit_status']=='حذف/ادغام': md.append(f"| {x['sequence']} | {x['title']} | {x['canonical_sequence']} | {x['audit_note']} |")
md += ['','## آمار نهایی','',f'- کل ورودی: **{len(out)}** مقاله',f'- ادغام/حذف از فهرست مستقل: **{len(removed)}** مقاله',f'- باقی‌مانده: **{len(active)}** مقاله',f'- عنوان‌های اصلاح‌شده: **{len(corrections)}** عنوان','']
mx=max(counts.values()); mn=min(counts.values())
md += [f'- بیشترین جمعیت زیردسته: **{mx} مقاله**',f'- کمترین جمعیت زیردسته: **{mn} مقاله**','', '### زیردسته‌های دارای بیشترین مقاله','']
for s,c in counts.items():
 if c==mx: md.append(f'- {subs[s][2]} — `{s}`: **{c}**')
md += ['','### زیردسته‌های دارای کمترین مقاله','']
for s,c in counts.items():
 if c==mn: md.append(f'- {subs[s][2]} — `{s}`: **{c}**')
(Path(__file__).parent/'articles-001-310-by-taxonomy.md').write_text('\n'.join(md)+'\n',encoding='utf-8')
print(f'input={len(out)} removed={len(removed)} active={len(active)} corrected={len(corrections)} max={mx} min={mn}')
