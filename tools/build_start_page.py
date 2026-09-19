#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""Build the Qpedia start page (v2) from data.

Everything (markup, hrefs, Persian digits) is generated rather than hand-typed,
so that truncated digits / invented slugs / unbalanced tags cannot reach the file.
Output: fixes/pages/start-page.html   +   /tmp/start-v2-slugs.txt (for live verification)
"""
import re
import sys

OUT = "/home/user/Q-7/fixes/pages/start-page.html"
SLUGS = "/tmp/start-v2-slugs.txt"

LATIN_OK = {
    "NISQ", "IBM", "Condor", "HHL", "BB84", "GRW", "W", "Z", "AI",
}


def fa(n):
    """Persian digits, generated programmatically."""
    return "".join(chr(0x06F0 + int(c)) for c in str(n))


def lnk(slug, label):
    return '<a href="/%s/">%s</a>' % (slug, label)


def li(slug, label, note=None):
    s = lnk(slug, label)
    if note:
        s += " — " + note
    return "<li>%s</li>" % s


SEC = [
    ("مبانی: تعریف‌های دقیق", [
        ("quantum-physics-vs-quantum-mechanics", "فرق فیزیک کوانتوم و مکانیک کوانتومی", None),
        ("wave-particle-duality", "دوگانگی موج و ذره", None),
        ("wave-function", "تابع موج چه چیزی را توصیف می‌کند؟", None),
        ("born-probability", "ماکس بورن و تفسیر احتمالاتی", "مربع تابع موج چه می‌گوید"),
        ("quantum-superposition", "برهم‌نهی", "«هم‌زمانی» را از «نمی‌دانم کدام» جدا کنید"),
        ("quantum-state", "حالت کوانتومی", None),
        ("quantum-measurement", "اندازه‌گیری کوانتومی", None),
        ("observer", "«ناظر» چیست و چه چیزی نیست؟", None),
        ("heisenberg-uncertainty-principle", "اصل عدم‌قطعیت هایزنبرگ", None),
        ("quantum-spin", "اسپین", "ویژگی‌ای که قرینهٔ کلاسیک ندارد"),
        ("pauli-exclusion", "اصل طرد پائولی", "چرا دو الکترون در یک «اتاق» نمی‌مانند"),
        ("energy-levels", "سطوح انرژی", None),
        ("planck-constant", "ثابت پلانک", None),
        ("bohr-atomic-model", "مدل اتمی بور", None),
        ("ultraviolet-catastrophe", "فاجعهٔ فرابنفش", "همان‌جا که فیزیک کلاسیک شکست"),
        ("quantum-tunneling", "تونل‌زنی کوانتومی", None),
        ("quantum-number", "عدد کوانتومی", None),
        ("no-cloning-theorem", "قضیهٔ عدم‌کپی", "چرا حالت کوانتومی تکثیر نمی‌شود"),
        ("why-large-objects-dont-superpose", "چرا اجسام درشت برهم‌نهی نمی‌شوند؟", None),
        ("why-quantum-math-works", "چرا ریاضی کوانتوم کار می‌کند؟", None),
    ]),
    ("ذره‌ها و دنیای زیراتمی", [
        ("photon", "فوتون", None),
        ("electron", "الکترون", None),
        ("quark", "کوارک", None),
        ("proton-neutron-quark-structure", "ساختار پروتون و نوترون", None),
        ("neutrino", "نوترینو", None),
        ("muon-and-tau", "موئون و تاو", None),
        ("higgs-boson", "بوزون هیگز", None),
        ("gluon-w-z-bosons", "گلوئون و بوزون‌های W و Z", None),
        ("standard-model", "مدل استاندارد", "نقشهٔ فعلی، با جاهای خالی‌اش"),
        ("antimatter", "پادماده", None),
        ("pet-scan-antimatter", "پت‌اسکن", "همان پادماده، در بیمارستان"),
        ("virtual-particles", "ذره‌های مجازی", None),
        ("vacuum-fluctuations", "نوسان‌های خلأ", None),
        ("casimir-effect", "اثر کازیمیر", "فشارِ «هیچ» روی دو صفحهٔ نزدیک"),
    ]),
    ("آزمایش‌ها و پدیده‌ها", [
        ("double-slit-experiment", "آزمایش دو شکاف", "پیش از هر تفسیری، خودِ پدیده"),
        ("photoelectric-effect", "اثر فوتوالکتریک", None),
        ("einstein-photoelectric-effect", "چرا همین یک مقاله به نوبل رسید", None),
        ("stern-gerlach-experiment", "آزمایش اشترن-گرلاخ", "ساده‌ترین شکلِ کوانتوم"),
        ("epr-paradox", "پارادوکس ای پی آر", None),
        ("bell-inequality", "نابرابری بل", None),
        ("bell-experiments", "آزمایش‌های بل", None),
        ("aspect-experiment-1982", "آزمایش آسپکت در " + fa(1982), None),
        ("loophole-free-bell-test", "آزمایش‌های بدون گریز", "حلقه‌های باز بسته شدند"),
        ("wheeler-delayed-choice", "انتخاب تأخیری ویلر", "فوتون از قبل تصمیم نگرفته"),
        ("quantum-eraser", "پاک‌کن کوانتومی", "آیا آینده بر گذشته اثر می‌گذارد؟"),
        ("quantum-teleportation", "تله‌پورت کوانتومی", None),
        ("continuous-variable-teleportation", "تله‌پورت پیوسته", "موج نور، نه فقط کیوبیت"),
        ("schrodinger-cat", "گربهٔ شرودینگر", None),
        ("wigner-friend", "دوستِ وینگر", None),
        ("quantum-zeno-effect", "اثر زنون کوانتومی", None),
        ("aharonov-bohm-effect", "اثر آهارونوف-بهم", None),
        ("lamb-shift", "جابه‌جایی لامب", None),
        ("superconductivity", "ابررسانایی", None),
        ("superfluidity", "فروچگالی", None),
        ("bose-einstein-condensate", "میعاد بوز-اینشتین", None),
        ("quantum-spin-liquid", "مایع اسپینی", None),
        ("time-crystal", "کریستال زمان", "ماده‌ای که در زمان تکرار می‌شود"),
        ("stimulated-emission", "گسیل القایی", "پایه‌ای که لیزر از آن ساخته شد"),
    ]),
    ("رایانهٔ کوانتومی: از کیوبیت تا امروز", [
        ("qubit", "کیوبیت", None),
        ("qubit-types-compared", "سه جور کیوبیت", "ابررسانا، یون و فوتون"),
        ("quantum-gate", "دروازهٔ کوانتومی", None),
        ("quantum-simulation", "شبیه‌سازی کوانتومی", None),
        ("nisq-era", "دوران NISQ", "همین‌جایی که فناوری ایستاده"),
        ("quantum-supremacy", "برتری کوانتومی", None),
        ("ibm-condor-processor", "پردازندهٔ IBM Condor", "بیش از هزار کیوبیت، بدون تصحیح خطا"),
        ("willow-chip", "تراشهٔ ویلو", "گوگل دقیقاً چه چیزی را ثابت کرد؟"),
        ("quantum-error-correction", "تصحیح خطای کوانتومی", "سنگینی‌ترین بارِ مهندسیِ مسیر"),
        ("topological-quantum-computing", "کامپیوتر توپولوژیک", "گره‌ای که خطا را نمی‌بیند"),
        ("majorana-topological", "مایورانا و کیوبیت توپولوژیک", None),
        ("topological-superconductivity", "ابررسانایی توپولوژیک", None),
        ("quantum-memory", "حافظهٔ کوانتومی", None),
        ("quantum-repeater", "تکرارکنندهٔ کوانتومی", None),
        ("measurement-based-quantum-computing", "محاسبهٔ اندازه‌گیری‌محور", None),
        ("quantum-annealing-dwave", "آنیلینگ کوانتومی و دی‌وِیو", None),
        ("shor-algorithm", "الگوریتم شور", "رمزشکنیِ نمایی"),
        ("grover-algorithm", "الگوریتم گرور", "جست‌وجوی مربعی"),
        ("hhl-algorithm", "الگوریتم HHL", None),
        ("quantum-chemistry", "شیمی کوانتومی", None),
        ("quantum-chemistry-drug-discovery", "شیمی محاسباتی و کشف دارو", None),
        ("quantum-machine-learning", "یادگیری ماشین کوانتومی", None),
        ("does-ai-use-quantum", "آیا هوش مصنوعی از کوانتوم استفاده می‌کند؟", None),
        ("quantum-long-term-memory", "حافظهٔ بلندمدت در یادگیری ماشین", None),
        ("moore-law-quantum-limit", "قانون مور و سقف فیزیکی‌اش", None),
        ("q-day", "روز کیو", None),
        ("quantum-hype-bubble", "حبابِ تبلیغات کوانتومی", None),
        ("quantum-computer-reality", "واقعیت رایانهٔ کوانتومی امروز", "جمع‌بندیِ بی‌هیجان"),
    ]),
    ("رمزنگاری، امنیت و شبکه", [
        ("post-quantum-cryptography", "رمزنگاری پساکوانتومی", "کاری که امروز باید کرد"),
        ("bb84-protocol", "قرارداد BB84", "اولین شبکهٔ کلید کوانتومی"),
        ("quantum-cryptography-internet-security", "رمزنگاری کوانتومی و امنیت اینترنت", None),
        ("harvest-now-decrypt-later", "الان ضبط کن، بعداً بشکن", "جدی‌ترین ریسکِ این دوره"),
        ("bitcoin-quantum-threat", "تهدید کوانتومی برای بیت‌کوین", None),
        ("quantum-random-number-generator", "عدد تصادفی کوانتومی", "تنها تصادفِ واقعیِ موجود"),
        ("quantum-internet-satellite", "اینترنت کوانتومی ماهواره‌ای", None),
        ("quantum-radar", "رادار کوانتومی", None),
        ("quantum-sensors", "حسگرهای کوانتومی", None),
        ("quantum-navigation", "ناوبری کوانتومی", "مسیریابی بدون ماهواره"),
    ]),
    ("فناوری روزمره، با پایهٔ کوانتومی", [
        ("transistor-quantum", "ترانزیستور", "کوانتوم در هر چیپی که دارید"),
        ("how-lasers-work", "لیزر چطور کار می‌کند؟", None),
        ("fiber-optics", "فیبر نوری", None),
        ("mri-quantum", "ام‌آر‌آی", None),
        ("nuclear-spin", "اسپین هسته‌ای", "فیزیکِ پشت همان دستگاه"),
        ("flash-memory", "حافظهٔ فلش", None),
        ("atomic-clock-gps", "ساعت اتمی و جی‌پی‌اس", None),
        ("absolute-zero", "صفر مطلق", None),
        ("solar-cells-photoelectric", "سلول خورشیدی", None),
        ("quantum-dots-displays", "نقطه‌های کوانتومی در نمایشگرها", None),
        ("scanning-tunneling-microscope", "میکروسکپ تونلی روبشی", None),
        ("josephson-junction", "پیوند جوزفسون", "همان چیزی که ولت را تعریف می‌کند"),
        ("room-temperature-superconductor", "ابررسانای دمای اتاق", "ادعاها و تکرارناپذیری‌ها"),
        ("quantum-battery", "باتری کوانتومی", None),
        ("quantum-thermodynamics", "ترمودینامیک کوانتومی", None),
        ("stellar-fusion", "همجوشی ستاره‌ای", "چرا خورشید می‌درخشد"),
        ("zero-point-energy-scam", "کلاهبرداری انرژی نقطهٔ صفر", None),
    ]),
    ("زیست، مغز و آگاهی", [
        ("enzyme-quantum-tunneling", "تونل‌زنی در آنزیم‌ها", None),
        ("mitochondria-proton-tunneling", "تونل‌زنی در میتوکندری", "الکترون یا پروتون؟"),
        ("dna-repair-enzymes", "ترمیم دی‌ان‌ای", None),
        ("genetic-mutation", "جهش ژنتیکی", "اتفاقی که تونل‌زنی در آن دست دارد"),
        ("bird-quantum-compass", "قطب‌نمای کوانتومی پرندگان", None),
        ("quantum-smell", "بویایی کوانتومی", "هنوز تمام‌وکامل اثبات نشده"),
        ("schrodinger-life-equation", "شرودینگر و «حیات چیست؟»", None),
        ("is-the-brain-quantum", "آیا مغز کوانتومی است؟", None),
        ("brain-quantum-phenomena", "پدیده‌های کوانتومی در مغز", None),
        ("quantum-free-will", "آزادی اراده", None),
        ("quantum-darwinism", "داروینیسم کوانتومی", None),
    ]),
    ("تفسیرها: سرِ جدول چه می‌گذرد", [
        ("copenhagen-interpretation", "تفسیر کپنهاگی", None),
        ("bohr-complementarity", "بور و اصل تکمیل", "چرا یک چیز هم موج است هم ذره"),
        ("complementarity-principle", "اصل تکمیل", None),
        ("many-worlds-interpretation", "جهان‌های چندگانه", None),
        ("is-many-worlds-real", "آیا جهان‌های چندگانه واقعی‌اند؟", None),
        ("pilot-wave", "موج خلبان", "دنیای جبریِ دی‌بروی-بوهم"),
        ("objective-collapse", "فروپاشی عینی", None),
        ("grw-collapse", "تفسیر GRW", "فروپاشی خودبه‌خودی"),
        ("transactional-interpretation", "تفسیر تراکنشی", None),
        ("qbism", "کیوبیسم", "تابع موج، باورِ شخصیِ شماست"),
        ("quantum-realism", "رئالیسم کوانتومی", None),
        ("einstein-schrodinger-reality", "اینشتین و شرودینگر", "دو دوست که با کوانتوم در افتادند"),
        ("quantum-classical-boundary", "مرز کوانتوم و کلاسیک", None),
        ("quantum-interpretation-debate", "جنگِ تفسیرها", None),
        ("determinism-vs-probability", "جبر یا احتمال؟", None),
        ("quantum-immortality", "«جاودانگی کوانتومی»", "چه می‌گوید و چه نمی‌گوید"),
        ("quantum-time-travel", "سفر در زمان و کوانتوم", None),
        ("black-hole-information-paradox", "پارادوکس اطلاعات سیاه‌چاله", None),
        ("holographic-principle", "اصل هولوگرافیک", None),
        ("string-theory-quantum", "نظریهٔ ریسمان", None),
        ("quantum-gravity", "گرانش کوانتومی", None),
        ("quantum-fluctuations-cosmos", "نوسان‌های کوانتومی و کیهان", None),
        ("does-quantum-prove-god", "آیا کوانتوم خدا را ثابت می‌کند؟", None),
        ("everything-is-energy-claim", "ادعای «همه‌چیز انرژی است»", None),
    ]),
    ("تاریخ و آدم‌ها", [
        ("quantum-history", "تاریخ صدسالهٔ فیزیک کوانتوم", None),
        ("quantum-century-2025", "صدسالگی کوانتوم", "از ذرّهٔ پلانک تا امروز"),
        ("solvay-conference-1927", "کنفرانس " + fa(1927) + " سولوی", None),
        ("einstein-bohr-debate", "مناظرهٔ اینشتین و بور", None),
        ("heisenberg-ww2", "هایزنبرگ در جنگ جهانی", "نابغه‌ای که جنگش را دو نیم کرد"),
        ("feynman-quantum-explainer", "فاینمن و جمع‌بندی مسیرها", None),
        ("forgotten-women-quantum", "زنان فراموش‌شدهٔ کوانتوم", None),
        ("nobel-physics-2012", "نوبل " + fa(2012), None),
        ("nobel-physics-2022", "نوبل " + fa(2022), None),
        ("attosecond-nobel-2023", "نوبل " + fa(2023) + " و ثانیه‌های آتوثانیه", None),
        ("nobel-physics-2025", "نوبل " + fa(2025), None),
        ("quantum-documentaries", "مستندهای معتبر", None),
        ("quantum-physics-in-movies-ant-man", "کوانتوم در سینما", "چه‌جا درست، چه‌جا نه"),
        ("physicists-on-quantum-weirdness", "زبانِ دانشمندان دربارهٔ غریب‌بودن کوانتوم", None),
        ("quantum-learning-resources", "منابع یادگیری", None),
        ("quantum-career-future-learn", "مسیر یادگیری و آیندهٔ شغلی", None),
        ("quantum-understanding-achievement", "«فهمیدن» در کوانتوم یعنی چه؟", None),
    ]),
    ("شبه‌علم‌زدایی", [
        ("quantum-healing-debunked", "«شفای کوانتومی» چرا شبه‌علم است؟", None),
        ("crystal-healing-debunked", "درمان با کریستال", None),
        ("quantum-alternative-medicine-science", "پزشکی جایگزین و معیار علم", None),
        ("law-of-attraction-quantum", "قانون جذب و کوانتوم", "کجای استدلال می‌لنگد؟"),
        ("quantum-ai-marketing-hype", "بازاریابیِ «کوانتومی»", None),
        ("mind-quantum-reality", "ذهن و واقعیت کوانتومی", None),
        ("quantum-fivefold-mental-map", "نقشهٔ ذهن پنج‌گانه", None),
        ("entanglement-myths", "افسانه‌های درهم‌تنیدگی", None),
        ("coin-vs-dice-quantum-uncertainty", "سکه یا تاس؟", "فرق ندانستن و تعیین‌نشدن"),
        ("quantum-analogy-exercise-boundary", "مشقِ مرزِ تشبیه‌ها", "هرجا تشبیه می‌شکند"),
        ("simulation-hypothesis-quantum", "فرضیهٔ شبیه‌سازی", "جدی یا بازی فکری؟"),
        ("human-teleportation", "تله‌پورت انسان", None),
        ("is-classical-physics-wrong", "آیا فیزیک کلاسیک غلط است؟", None),
    ]),
]


def build():
    p = []
    p.append("<p>این صفحه، نقطهٔ شروعِ دانشنامهٔ کوانتوم‌پدیا است. اینجا بزرگ‌نمایی در کار نیست؛ فقط مسیرهای خواندنِ مرتب، به مقالاتی که هرکدام یک سؤال را جدی جواب داده‌اند. اگر تازه آمده‌اید، «سه قدم اول» را بردارید؛ اگر سؤالتان مشخص است، مستقیم به رده‌اش بروید.</p>")
    steps = "".join([
        li("what-is-quantum", "«کوانتوم» دقیقاً یعنی چه؟", "پایه‌ای‌ترین واژه، که کم تعریف می‌شود"),
        li("double-slit-experiment", "آزمایش دو شکاف", "پیش از هر تفسیری، خودِ پدیده را ببینید"),
        li("quantum-computer-reality", "واقعیت رایانهٔ کوانتومی امروز", "تا بدانیم حرف از چه دستگاهی است"),
    ])
    p.append('<aside class="qp-callout"><p class="qp-callout__title">سه قدم اول</p><ol class="wp-block-list">%s</ol></aside>' % steps)
    quick = "".join([
        li("quantum-entanglement-explained", "درهم‌تنیدگی", "بدون افسانه‌سازی و بدون «سریع‌تر از نور»"),
        li("decoherence", "ناهمدوسی", "دلیلِ اصلیِ شکننده‌بودن کیوبیت‌ها"),
        li("nisq-era", "دوران NISQ", "همین جایی که فناوری ایستاده"),
        li("quantum-healing-debunked", "چرا «شفای کوانتومی» کار نمی‌کند؟", "یک مثالِ کاملِ داوری"),
        li("spot-pseudoscience-one-sentence", "یک ادعای کوانتومی را در یک جمله داوری کنید", "ابزارِ کارِ روزمره"),
    ])
    p.append('<h2 class="wp-block-heading">اگر فقط ده دقیقه وقت دارید</h2><ul class="wp-block-list">%s</ul>' % quick)
    p.append('<p>عنوانِ بعضی از این مقاله‌ها تشبیه دارد: سکه، تاس، گربه، گره، دیگِ جوش. این فقط یک تشبیه است؛ خودِ همان مقاله می‌گوید کجا می‌شکند. پس به جای حفظ‌کردنِ تشبیه، جملهٔ دقیقِ مقاله را بخوانید.</p>')
    for heading, items in SEC:
        rows = "".join(li(s, lab, note) for s, lab, note in items if lab)
        p.append('<h2 class="wp-block-heading">%s</h2><ul class="wp-block-list">%s</ul>' % (heading, rows))
    browse = "".join([
        "<li>از <a href=\"/\">صفحهٔ اصلی</a> فهرست تازه‌ها و دسته‌ها در دسترس است؛ هر مقاله در پایانِ خودش نزدیک‌ترین مقاله‌های همان موضوع را نشان می‌دهد.</li>",
        "<li>دنبال مفهوم مشخصی می‌گردید؟ کادر جست‌وجو در سربرگِ همهٔ صفحه‌ها هست؛ برای نتیجهٔ دقیق‌تر، نامِ انگلیسیِ مفهوم را بنویسید.</li>",
        "<li>دربارهٔ سایت و شیوهٔ نگارش: %s. برای پرسش یا گزارش لینک شکسته: %s. حریم خصوصی: %s.</li>" % (
            lnk("about-us", "دربارهٔ ما"), lnk("contact-us", "تماس با ما"), lnk("privacy-policy", "این صفحه")),
    ])
    p.append('<h2 class="wp-block-heading">چطور در سایت بگردی</h2><ul class="wp-block-list">%s</ul>' % browse)
    faqs = [
        ("اگر هیچ پیش‌زمینه‌ای ندارم، از کجا شروع کنم؟",
         "<p>همان «سه قدم اول» در بالای همین صفحه: اول بدانید «کوانتوم» یعنی چه، بعد پدیدهٔ دو شکاف را ببینید، و در پایان بفهمید رایانهٔ کوانتومی دقیقاً چه کاری می‌کند. این سه‌تا جلوی بیشترِ سوءبرداشت‌های بعدی را می‌گیرد.</p>"),
        ("برای فهمیدن باید ریاضی بلد باشم؟",
         "<p>برای بیشترِ مقاله‌ها نه؛ آن‌ها با زبان معمولی نوشته شده‌اند. اگر خواستید عمیق‌تر بروید، %s می‌گوید ریاضی کوانتوم از کجا آمده، و %s راه تمرین را نشان می‌دهد.</p>" % (
             lnk("why-quantum-math-works", "این مقاله"), lnk("quantum-learning-resources", "فهرست منابع"))),
        ("چقدر از این فهرست را باید بخوانم؟",
         "<p>هیچ‌کدام اجباری نیست. این صفحه یک نقشه است، نه یک تکلیف؛ هر بخشش را می‌شود جدا خواند. اگر فقط کنجکاوید، همان ده دقیقهٔ اول کافی است. اگر می‌خواهید داوری‌تان دقیق‌تر شود، «شبه‌علم‌زدایی» را زودتر بخوانید.</p>"),
        ("مطلبی دربارهٔ سؤالِ من نیست؛ چه کنم؟",
         "<p>از فرمِ %s بنویسید. سؤال‌ها معیارِ انتخاب موضوع‌های بعدی‌اند؛ پس کوتاه و مشخص بپرسید: انتظار دارید جواب، دقیقاً چه چیزی را روشن کند؟</p>" % lnk("contact-us", "تماس با ما")),
    ]
    p.append('<h2 class="wp-block-heading">پرسش‌های پرتکرار</h2>%s' % "".join(
        "<details><summary><strong>%s</strong></summary>%s</details>" % (q, a_) for q, a_ in faqs))
    p.append("<blockquote><p>این صفحه دستی نگهداری می‌شود. اگر لینکی از همین صفحه به مقصد نرسید یا خطای «۴۰۴» داد، همان یک خط را برایمان بفرستید؛ اصلاحش یک دقیقه طول می‌کشد.</p></blockquote>")
    return "\n\n".join(p) + "\n"


def audit(html):
    errs = []
    for t in ("a", "p", "li", "ul", "ol", "h2", "aside", "details", "summary", "blockquote", "strong", "em"):
        o = len(re.findall(r"<%s[ >]" % t, html))
        c = len(re.findall(r"</%s>" % t, html))
        if o != c:
            errs.append("unbalanced <%s>: %d/%d" % (t, o, c))
    if re.search(r"<(style|script)", html, re.I):
        errs.append("style/script present")
    if re.search(r"<(h1|figure|form|input|iframe)", html, re.I):
        errs.append("disallowed element present")
    for m in re.finditer(r"&(?!amp;|lt;|gt;|quot;|#\d+;)", html):
        errs.append("raw & at %d near %r" % (m.start(), html[m.start() - 40:m.start() + 40]))
        break
    if "<?" in html:
        errs.append("php/opening tag marker")
    # mixed-script guard: a Latin-only token inside Persian text must be whitelisted
    for m in re.finditer(r">([^<>]+)<", html):
        for chunk in re.split(r"[\s،.()«»؛]+", m.group(1)):
            if re.search(r"[A-Za-z]", chunk):
                if re.search(r"[\u0600-\u06FF]", chunk):
                    errs.append("mixed-script token %r" % chunk)
                elif chunk.strip(".,;:!?«»()") not in LATIN_OK:
                    errs.append("unlisted Latin token %r" % chunk)
    # Persian ه is U+0647 legitimately; only these two are Arabic-only lookalikes
    for bad, name in (("\u064a", "Arabic ya U+064A"), ("\u0643", "Arabic kaf U+0643")):
        if bad in html:
            errs.append("%s present" % name)
    visible = re.sub(r"<[^>]+>", " ", html)
    for ok in LATIN_OK:          # technical terms such as BB84 may carry digits
        visible = visible.replace(ok, " ")
    if re.search(r"[0-9]", visible):
        errs.append("ASCII digits in visible text: %r" % re.findall(r".{25}[0-9].{8}", visible)[:4])
    if "\u066b" in html or "\u066a" in html:
        errs.append("Arabic decimal/percent sign")
    if "، و" in html or "، یا" in html:
        pass
    slugs = re.findall(r'href="/([a-z0-9\-]*)/"', html)
    uniq = sorted(set(slugs))
    with open(SLUGS, "w") as f:
        f.write("\n".join(uniq) + "\n")
    text = re.sub(r"<[^>]+>", " ", html)
    print("links: %d   unique slugs: %d" % (len(slugs), len(uniq)))
    print("repeated targets: %s" % (", ".join("%s×%d" % (s, slugs.count(s)) for s in uniq if slugs.count(s) > 1) or "none"))
    print("persian word-forms: %d   bytes: %d" % (len(re.findall(r"[\u0600-\u06FF]+", text)), len(html.encode())))
    return errs, uniq


html = build()
errs, slugs = audit(html)
if errs:
    print("ERRORS:")
    for e in errs:
        print("  -", e)
    sys.exit(1)
open(OUT, "w").write(html)
print("OK written:", OUT)
