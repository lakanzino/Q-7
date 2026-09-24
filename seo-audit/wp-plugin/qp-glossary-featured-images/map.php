<?php
/**
 * نگاشت مقاله ← تصویر شاخص تازه.
 *
 * هر ردیف:
 *   slug  → اسلاگ مقاله (نام فایل تصویر هم همین است)
 *   file  → نام فایل WebP داخل includes/images/
 *   title → عنوان مقاله (برای نام تصویر در کتابخانهٔ رسانه)
 *   en    → نام انگلیسی اصطلاح
 *   kw    → کلمهٔ کلیدی کانونی مقاله (از Rank Math)
 *   alt   → متن جانشین طبق استاندارد گوگل: توصیفی، طبیعی، زیر ۱۲۵ نویسه، شامل کلمهٔ کلیدی
 *
 * فقط همین ۵۵ مقاله دست می‌خورند.
 *
 * @package QP_Featured_Images
 */

defined( 'ABSPATH' ) || exit;

return array(
	array( 'slug' => 'absolute-zero', 'file' => 'absolute-zero.webp', 'title' => 'صفر مطلق چیست؟', 'en' => 'Absolute Zero', 'kw' => 'صفر مطلق', 'alt' => 'صفر مطلق؛ تصویر مفهومی سردترین دمای ممکن با کرهٔ نورانی آبی و مدارهای یخی' ),
	array( 'slug' => 'aharonov-bohm-effect', 'file' => 'aharonov-bohm-effect.webp', 'title' => 'اثر آهارونوف-بوهم؛ فاز بدون میدان محلی', 'en' => 'Aharonov-Bohm Effect', 'kw' => 'اثر آهارونوف بوهم', 'alt' => 'اثر آهارونوف بوهم؛ الگوی تداخل الکترون در حضور شار مغناطیسی محصور' ),
	array( 'slug' => 'alpha-decay', 'file' => 'alpha-decay.webp', 'title' => 'واپاشی آلفا چیست؟', 'en' => 'Alpha Decay', 'kw' => 'واپاشی آلفا', 'alt' => 'واپاشی آلفا؛ هستهٔ اتمی صورتی که ذرهٔ آلفا گسیل می‌کند' ),
	array( 'slug' => 'bell-inequality', 'file' => 'bell-inequality.webp', 'title' => 'نامساوی بل', 'en' => 'Bell\'s Inequality', 'kw' => 'نامساوی بل', 'alt' => 'نامساوی بل؛ نمودار همبستگی کوانتومی در مرز واقع‌گرایی موضعی' ),
	array( 'slug' => 'bohr-atomic-model', 'file' => 'bohr-atomic-model.webp', 'title' => 'مدل اتمی بور', 'en' => 'Bohr Atomic Model', 'kw' => 'مدل اتمی بور', 'alt' => 'مدل اتمی بور؛ الکترون روی مدارهای مجاز گرد هستهٔ اتم' ),
	array( 'slug' => 'bohr-complementarity', 'file' => 'bohr-complementarity.webp', 'title' => 'نیلز بور و اصل تکمیل؛ چرا یک چیز هم موج است هم ذره؟', 'en' => 'Bohr and Complementarity', 'kw' => 'اصل مکملیت بور', 'alt' => 'اصل مکملیت بور؛ نمایش هم‌زمان رفتار موجی و ذره‌ای یک ذره' ),
	array( 'slug' => 'born-probability', 'file' => 'born-probability.webp', 'title' => 'ماکس بورن و تفسیر احتمالاتی؛ مربع تابع موج چه می گوید؟', 'en' => 'Born Probability Rule', 'kw' => 'قاعده بورن', 'alt' => 'قاعده بورن؛ توزیع احتمال کوانتومی و مربع تابع موج' ),
	array( 'slug' => 'casimir-effect', 'file' => 'casimir-effect.webp', 'title' => 'اثر کازیمیر', 'en' => 'Casimir Effect', 'kw' => 'اثر کازیمیر', 'alt' => 'اثر کازیمیر؛ جاذبهٔ دو صفحهٔ موازی ناشی از افت‌وخیز خلأ' ),
	array( 'slug' => 'coherence', 'file' => 'coherence.webp', 'title' => 'همدوسی چیست؟', 'en' => 'Coherence', 'kw' => 'همدوسی', 'alt' => 'همدوسی؛ رابطهٔ فاز پایدار میان اجزای یک حالت کوانتومی' ),
	array( 'slug' => 'coin-vs-dice-quantum-uncertainty', 'file' => 'coin-vs-dice-quantum-uncertainty.webp', 'title' => 'تمثیل تاس در مقابل تمثیل سکه: کدام برای عدم قطعیت بهتر است؟', 'en' => 'Coin vs Dice Analogy', 'kw' => 'تمثیل سکه و تاس کوانتوم', 'alt' => 'تمثیل سکه و تاس کوانتوم؛ مقایسهٔ تصادف کلاسیک با عدم قطعیت کوانتومی' ),
	array( 'slug' => 'complementarity-principle', 'file' => 'complementarity-principle.webp', 'title' => 'اصل مکملیت چیست؟', 'en' => 'Complementarity Principle', 'kw' => 'اصل مکملیت', 'alt' => 'اصل مکملیت؛ دو توصیف مکمل از یک سامانهٔ کوانتومی' ),
	array( 'slug' => 'copenhagen-interpretation', 'file' => 'copenhagen-interpretation.webp', 'title' => 'تفسیر کپنهاگی', 'en' => 'Copenhagen Interpretation', 'kw' => 'تفسیر کپنهاگی', 'alt' => 'تفسیر کپنهاگی؛ فروپاشی تابع موج و نقش اندازه‌گیری در نتیجه' ),
	array( 'slug' => 'cosmic-inflation', 'file' => 'cosmic-inflation.webp', 'title' => 'تورم کیهانی؛ جهش آغازین فضا و بذر ساختارهای جهان', 'en' => 'Cosmic Inflation', 'kw' => 'تورم کیهانی', 'alt' => 'تورم کیهانی؛ انبساط شتابان جهان آغازین و بذر ساختارهای کیهانی' ),
	array( 'slug' => 'cosmic-microwave-background', 'file' => 'cosmic-microwave-background.webp', 'title' => 'تابش زمینهٔ کیهانی؛ قدیمی‌ترین نوری که می‌توانیم ببینیم', 'en' => 'Cosmic Microwave Background', 'kw' => 'تابش زمینه کیهانی', 'alt' => 'تابش زمینه کیهانی؛ نقشهٔ قدیمی‌ترین نور جهان با دمای حدود ۲٫۷ کلوین' ),
	array( 'slug' => 'decoherence', 'file' => 'decoherence.webp', 'title' => 'واهمدوسی (Decoherence)؛ چرا گربه‌ای را نمی‌بینیم که هم‌زمان زنده و مرده باشد؟', 'en' => 'Decoherence', 'kw' => 'واهمدوسی', 'alt' => 'واهمدوسی؛ از دست رفتن همدوسی و پخش اطلاعات فاز در محیط' ),
	array( 'slug' => 'determinism-vs-probability', 'file' => 'determinism-vs-probability.webp', 'title' => 'تفاوت جبرگرایی کلاسیک و احتمال کوانتومی', 'en' => 'Determinism vs Probability', 'kw' => 'جبرگرایی و احتمال کوانتومی', 'alt' => 'جبرگرایی و احتمال کوانتومی؛ تفاوت مسیر قطعی با توزیع احتمالاتی' ),
	array( 'slug' => 'does-quantum-prove-god', 'file' => 'does-quantum-prove-god.webp', 'title' => 'آیا کوانتوم ثابت می کند خدا وجود دارد یا ندارد؟', 'en' => 'Does Quantum Prove God?', 'kw' => 'کوانتوم و خدا', 'alt' => 'کوانتوم و خدا؛ پرسش مرز علم و باور در تفسیر مکانیک کوانتومی' ),
	array( 'slug' => 'double-slit-experiment', 'file' => 'double-slit-experiment.webp', 'title' => 'آزمایش دو شکاف؛ معروف‌ترین آزمایش فیزیک، که هنوز هم درک کاملش سخت است', 'en' => 'Double-Slit Experiment', 'kw' => 'آزمایش دو شکاف', 'alt' => 'آزمایش دو شکاف؛ الگوی تداخل الکترون پس از عبور از دو شکاف' ),
	array( 'slug' => 'energy-levels', 'file' => 'energy-levels.webp', 'title' => 'ترازهای انرژی و کوانتش', 'en' => 'Energy Levels and Quantization', 'kw' => 'ترازهای انرژی', 'alt' => 'ترازهای انرژی؛ سطوح گسستهٔ انرژی و پدیدهٔ کوانتش' ),
	array( 'slug' => 'entanglement-myths', 'file' => 'entanglement-myths.webp', 'title' => 'آیا درهم تنیدگی یعنی اطلاعات سریع تر از نور منتقل می شود؟', 'en' => 'Entanglement Myths', 'kw' => 'درهم‌تنیدگی', 'alt' => 'درهم‌تنیدگی؛ دو سامانهٔ به‌هم‌پیوسته و مرز انتقال اطلاعات' ),
	array( 'slug' => 'entanglement-quantum-computers', 'file' => 'entanglement-quantum-computers.webp', 'title' => 'درهم‌تنیدگی در کامپیوترهای کوانتومی امروزی', 'en' => 'Entanglement in Quantum Computers', 'kw' => 'درهم‌تنیدگی در کامپیوتر کوانتومی', 'alt' => 'درهم‌تنیدگی در کامپیوتر کوانتومی؛ اتصال کیوبیت‌ها در مدار کوانتومی' ),
	array( 'slug' => 'heisenberg-uncertainty-principle', 'file' => 'heisenberg-uncertainty-principle.webp', 'title' => 'اصل عدم قطعیت هایزنبرگ به زبان ساده', 'en' => 'Heisenberg\'s Uncertainty Principle', 'kw' => 'اصل عدم قطعیت', 'alt' => 'اصل عدم قطعیت هایزنبرگ؛ محدودیت اندازه‌گیری هم‌زمان مکان و تکانه' ),
	array( 'slug' => 'holographic-principle', 'file' => 'holographic-principle.webp', 'title' => 'اصل هولوگرافیک؛ آیا جهان ما یک هولوگرام است؟', 'en' => 'Holographic Principle', 'kw' => 'اصل هولوگرافیک', 'alt' => 'اصل هولوگرافیک؛ سطح دوبعدی که اطلاعات حجم سه‌بعدی را رمزگذاری می‌کند' ),
	array( 'slug' => 'is-classical-physics-wrong', 'file' => 'is-classical-physics-wrong.webp', 'title' => 'آیا فیزیک کلاسیک اشتباه بود؟ نه، محدود بود', 'en' => 'Was Classical Physics Wrong?', 'kw' => 'آیا فیزیک کلاسیک اشتباه بود', 'alt' => 'آیا فیزیک کلاسیک اشتباه بود؛ مرز کاربرد فیزیک نیوتنی و کوانتومی' ),
	array( 'slug' => 'many-worlds-interpretation', 'file' => 'many-worlds-interpretation.webp', 'title' => 'تفسیر جهان‌های موازی', 'en' => 'Many-Worlds Interpretation', 'kw' => 'جهان‌های موازی', 'alt' => 'جهان‌های موازی؛ شاخه‌شدن واقعیت در تفسیر چندجهانی' ),
	array( 'slug' => 'no-cloning-theorem', 'file' => 'no-cloning-theorem.webp', 'title' => 'قضیهٔ عدم‌کپی چیست؟', 'en' => 'No-Cloning Theorem', 'kw' => 'قضیه عدم کپی', 'alt' => 'قضیه عدم کپی؛ ناممکن بودن نسخه‌برداری دقیق از حالت کوانتومی ناشناخته' ),
	array( 'slug' => 'observer', 'file' => 'observer.webp', 'title' => 'ناظر در کوانتوم چیست؟', 'en' => 'Observer in Quantum Mechanics', 'kw' => 'ناظر کوانتومی', 'alt' => 'ناظر کوانتومی؛ نقش اندازه‌گیری و مشاهده در فروپاشی تابع موج' ),
	array( 'slug' => 'pauli-exclusion-principle', 'file' => 'pauli-exclusion-principle.webp', 'title' => 'اصل طرد پاولی دقیقاً چه می‌گوید؟ به زبان ساده', 'en' => 'Pauli Exclusion Principle', 'kw' => 'اصل طرد پاولی', 'alt' => 'اصل طرد پاولی؛ دو فرمیون که نمی‌توانند حالت کوانتومی یکسان بگیرند' ),
	array( 'slug' => 'planck-constant', 'file' => 'planck-constant.webp', 'title' => 'ثابت پلانک؛ کوچک‌ترین واحد جهان', 'en' => 'Planck\'s Constant', 'kw' => 'ثابت پلانک', 'alt' => 'ثابت پلانک؛ کوچک‌ترین بستهٔ انرژی و نقطهٔ آغاز فیزیک کوانتومی' ),
	array( 'slug' => 'quantum-bounce', 'file' => 'quantum-bounce.webp', 'title' => 'جهش کوانتومی کیهان؛ آیا پیش از مهبانگ مرحله‌ای دیگر وجود داشت؟', 'en' => 'Quantum Bounce', 'kw' => 'جهش کوانتومی', 'alt' => 'جهش کوانتومی؛ گذر جهان از انقباض پیشین به انبساط تازه' ),
	array( 'slug' => 'quantum-culture', 'file' => 'quantum-culture.webp', 'title' => 'فرهنگ کوانتومی؛ وقتی زبان فیزیک وارد جامعه می‌شود', 'en' => 'Quantum Culture', 'kw' => 'فرهنگ کوانتومی', 'alt' => 'فرهنگ کوانتومی؛ ورود واژه‌های کوانتوم به هنر، سینما و تبلیغات' ),
	array( 'slug' => 'quantum-darwinism', 'file' => 'quantum-darwinism.webp', 'title' => 'داروینیسم کوانتومی؛ چرا همه یک واقعیت می بینیم؟', 'en' => 'Quantum Darwinism', 'kw' => 'داروینیسم کوانتومی', 'alt' => 'داروینیسم کوانتومی؛ چرا همه یک واقعیت مشترک می‌بینیم' ),
	array( 'slug' => 'quantum-entanglement-explained', 'file' => 'quantum-entanglement-explained.webp', 'title' => 'درهم‌تنیدگی کوانتومی؛ «اثر شبح‌وار» که واقعی است، اما پیام نمی‌فرستد', 'en' => 'Quantum Entanglement', 'kw' => 'درهم‌تنیدگی کوانتومی', 'alt' => 'درهم‌تنیدگی کوانتومی؛ دو ذرهٔ به‌هم‌وابسته با همبستگی قوی‌تر از کلاسیک' ),
	array( 'slug' => 'quantum-free-will', 'file' => 'quantum-free-will.webp', 'title' => 'کوانتوم و ارادهٔ آزاد؛ آیا آینده از قبل نوشته شده؟', 'en' => 'Quantum and Free Will', 'kw' => 'کوانتوم و اراده آزاد', 'alt' => 'کوانتوم و اراده آزاد؛ نقش تصادف کوانتومی در انتخاب انسانی' ),
	array( 'slug' => 'quantum-measurement', 'file' => 'quantum-measurement.webp', 'title' => 'اندازه‌گیری و فروپاشی در کوانتوم؛ بزرگ‌ترین درزِ مکانیک کوانتومی', 'en' => 'Measurement and Collapse', 'kw' => 'اندازه‌گیری کوانتومی', 'alt' => 'اندازه‌گیری کوانتومی؛ فروپاشی تابع موج و مسئلهٔ اندازه‌گیری' ),
	array( 'slug' => 'quantum-number', 'file' => 'quantum-number.webp', 'title' => 'عدد کوانتومی چیست؟', 'en' => 'Quantum Numbers', 'kw' => 'عدد کوانتومی', 'alt' => 'عدد کوانتومی؛ برچسب‌های گسسته برای توصیف حالت الکترون در اتم' ),
	array( 'slug' => 'quantum-physics-vs-quantum-mechanics', 'file' => 'quantum-physics-vs-quantum-mechanics.webp', 'title' => 'تفاوت فیزیک کوانتوم و مکانیک کوانتومی چیست؟', 'en' => 'Quantum Physics vs Quantum Mechanics', 'kw' => 'تفاوت فیزیک کوانتوم و مکانیک کوانتومی', 'alt' => 'تفاوت فیزیک کوانتوم و مکانیک کوانتومی؛ نظریه در برابر چارچوب ریاضی' ),
	array( 'slug' => 'quantum-probability', 'file' => 'quantum-probability.webp', 'title' => 'احتمال کوانتومی؛ چرا جمع دامنه‌ها با جمع احتمال‌ها فرق دارد؟', 'en' => 'Quantum Probability', 'kw' => 'احتمال کوانتومی', 'alt' => 'احتمال کوانتومی؛ جمع دامنه‌ها در برابر جمع احتمال‌ها' ),
	array( 'slug' => 'quantum-random-number-generator', 'file' => 'quantum-random-number-generator.webp', 'title' => 'عدد تصادفی کوانتومی؛ تنها تصادف واقعی جهان', 'en' => 'Quantum Random Number Generator', 'kw' => 'عدد تصادفی کوانتومی', 'alt' => 'عدد تصادفی کوانتومی؛ تولید تصادف واقعی از اندازه‌گیری یک کیوبیت' ),
	array( 'slug' => 'quantum-spin', 'file' => 'quantum-spin.webp', 'title' => 'اسپین؛ چرخشی که چرخش نیست', 'en' => 'Spin', 'kw' => 'اسپین کوانتومی', 'alt' => 'اسپین کوانتومی؛ ویژگی ذاتی ذره که چرخش کلاسیکی نیست' ),
	array( 'slug' => 'quantum-state', 'file' => 'quantum-state.webp', 'title' => 'حالت کوانتومی چیست؟ چرا «الکترون کجاست؟» پرسش اشتباه است؟', 'en' => 'Quantum State', 'kw' => 'حالت کوانتومی', 'alt' => 'حالت کوانتومی؛ کرهٔ بلاخ با دو حالت پایه و برهم‌نهی آن‌ها' ),
	array( 'slug' => 'quantum-superposition', 'file' => 'quantum-superposition.webp', 'title' => 'برهم‌نهی کوانتومی؛ چرا سکهٔ چرخان، الکترون نیست؟', 'en' => 'Quantum Superposition', 'kw' => 'برهم‌نهی کوانتومی', 'alt' => 'برهم‌نهی کوانتومی؛ هم‌زمانی چند امکان تا لحظهٔ اندازه‌گیری' ),
	array( 'slug' => 'quantum-time-travel', 'file' => 'quantum-time-travel.webp', 'title' => 'آیا سفر در زمان با فیزیک کوانتوم ممکن است؟', 'en' => 'Quantum Time Travel', 'kw' => 'سفر در زمان کوانتومی', 'alt' => 'سفر در زمان کوانتومی؛ بررسی امکان‌پذیری آن در فیزیک نظری' ),
	array( 'slug' => 'quantum-tunneling', 'file' => 'quantum-tunneling.webp', 'title' => 'تونل‌زنی کوانتومی؛ پدیده‌ای که بدونش خورشید نمی‌تابید', 'en' => 'Quantum Tunneling', 'kw' => 'تونل‌زنی کوانتومی', 'alt' => 'تونل‌زنی کوانتومی؛ عبور ذره از سدی که در فیزیک کلاسیک ناممکن است' ),
	array( 'slug' => 'quantum-winter', 'file' => 'quantum-winter.webp', 'title' => 'زمستان کوانتومی؛ اگر سرمایه و اعتماد از فناوری عقب‌نشینی کنند', 'en' => 'Quantum Winter', 'kw' => 'زمستان کوانتومی', 'alt' => 'زمستان کوانتومی؛ عقب‌نشینی سرمایه و اعتماد از فناوری کوانتومی' ),
	array( 'slug' => 'quantum-zeno-effect', 'file' => 'quantum-zeno-effect.webp', 'title' => 'اثر زنون کوانتومی', 'en' => 'Quantum Zeno Effect', 'kw' => 'اثر زنون کوانتومی', 'alt' => 'اثر زنون کوانتومی؛ توقف تحول سامانه با اندازه‌گیری‌های پیوسته' ),
	array( 'slug' => 'superposition-explained', 'file' => 'superposition-explained.webp', 'title' => 'برهم نهی چیست؟ وقتی یک ذره «هم این است هم آن»', 'en' => 'Superposition Explained', 'kw' => 'برهم نهی', 'alt' => 'برهم‌نهی؛ ذره در ترکیب هم‌زمان چند حالت کوانتومی' ),
	array( 'slug' => 'ultraviolet-catastrophe', 'file' => 'ultraviolet-catastrophe.webp', 'title' => 'فاجعهٔ فرابنفش', 'en' => 'Ultraviolet Catastrophe', 'kw' => 'فاجعه فرابنفش', 'alt' => 'فاجعه فرابنفش؛ ناسازگاری نظریهٔ کلاسیک در تابش جسم سیاه' ),
	array( 'slug' => 'vacuum-fluctuations', 'file' => 'vacuum-fluctuations.webp', 'title' => 'نوسانات خلأ', 'en' => 'Vacuum Fluctuations', 'kw' => 'نوسانات خلأ', 'alt' => 'نوسانات خلأ؛ افت‌وخیز انرژی در پایین‌ترین حالت میدان کوانتومی' ),
	array( 'slug' => 'virtual-particles', 'file' => 'virtual-particles.webp', 'title' => 'ذرات مجازی چیستند؟', 'en' => 'Virtual Particles', 'kw' => 'ذرات مجازی', 'alt' => 'ذرات مجازی؛ جفت‌های افت‌وخیز کوتاه‌عمر در خلأ کوانتومی' ),
	array( 'slug' => 'wave-function', 'file' => 'wave-function.webp', 'title' => 'تابع موج چیست؟ کامل‌ترین نقشهٔ یک ذره — ولی خودش واقعیت نیست', 'en' => 'Wave Function', 'kw' => 'تابع موج', 'alt' => 'تابع موج؛ دامنهٔ احتمال و کامل‌ترین نقشهٔ حالت یک ذره' ),
	array( 'slug' => 'wave-particle-duality', 'file' => 'wave-particle-duality.webp', 'title' => 'دوگانگی موج و ذره', 'en' => 'Wave-Particle Duality', 'kw' => 'دوگانگی موج و ذره', 'alt' => 'دوگانگی موج و ذره؛ یک الکترون با دو رفتار موجی و ذره‌ای' ),
	array( 'slug' => 'what-is-quantum', 'file' => 'what-is-quantum.webp', 'title' => 'کوانتوم یعنی چه؟', 'en' => 'What Is Quantum?', 'kw' => 'کوانتوم چیست', 'alt' => 'کوانتوم چیست؛ اتم نورانی و موج، نماد کوانتش انرژی در فیزیک' ),
	array( 'slug' => 'wigner-friend', 'file' => 'wigner-friend.webp', 'title' => 'پارادوکس دوست ویگنر؛ آیا واقعیت برای همه یکی است؟', 'en' => 'Wigner\'s Friend', 'kw' => 'دوست ویگنر', 'alt' => 'دوست ویگنر؛ دو ناظر با نتیجهٔ متفاوت از یک اندازه‌گیری واحد' ),
	array( 'slug' => 'wormhole', 'file' => 'wormhole.webp', 'title' => 'کرم‌چاله؛ میان‌بُر فضا–زمان میان ریاضیات و واقعیت', 'en' => 'Wormhole', 'kw' => 'کرم‌چاله', 'alt' => 'کرم‌چاله؛ اتصال فرضی دو ناحیهٔ دور فضازمان در نسبیت عام' ),
);
