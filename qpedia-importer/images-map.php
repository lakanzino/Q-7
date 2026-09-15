<?php
/**
 * نقشهٔ تصاویر — ALT و کپشن (هایلایت آبی زیر هر تصویر)
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function qpedia_importer_images() {
	return array(
		// ---------------- qubit ----------------
		'qubit-feature' => array(
			'file'     => 'qubit/qubit-feature.webp',
			'slug'     => 'qubit',
			'featured' => true,
			'alt'      => 'سکه‌ای که روی میز می‌چرخد؛ نمادی از کیوبیت پیش از اندازه‌گیری — نه ۰ است نه ۱، در برهم‌نهی هم‌افزاینِ دو حالت',
			'caption'  => 'کیوبیت «بیت بهتر» نیست؛ پیش از اندازه‌گیری، برهم‌نهی است.',
		),
		'qubit-unit-circle' => array(
			'file'     => 'qubit/qubit-unit-circle.webp',
			'slug'     => 'qubit',
			'alt'      => 'نمودار دایره‌ی واحدِ یک کیوبیت: حالت |۰⟩ در بالا، |۱⟩ در پایین، و کیوبیت به شکل برداری در زاویه‌ای میان آن‌ها',
			'caption'  => 'کیوبیت نقطه نیست، جهت است؛ پرسیدن «بالا یا پایین؟» آن را روی یکی از دو سر می‌خواباند.',
		),
		// ---------------- decoherence ----------------
		'decoherence-feature' => array(
			'file'     => 'decoherence/decoherence-feature.webp',
			'slug'     => 'decoherence',
			'featured' => true,
			'alt'      => 'یک غبار ریز در فضای تیره که فوتون‌ها و امواج ظریف به همه‌سوی از آن پخش می‌شوند؛ نمادی از واهمدوسی — نشتِ اطلاعاتِ فاز به محیط',
			'caption'  => 'محیط، ناظرِ همیشگی است — واهمدوسی، همان «هم‌همیِ پنهانی» است که برهم‌نهی را خاموش می‌کند.',
		),
		'decoherence-rates' => array(
			'file'     => 'decoherence/decoherence-rates.webp',
			'slug'     => 'decoherence',
			'alt'      => 'مقایسهٔ نرخ واهمدوسی برای سه سیستم: الکترون در خلأ (نوار بلند)، اتم (نوار متوسط) و غبار در هوا (نوار بسیار کوتاه)',
			'caption'  => '«بزرگ‌بودن» خودش کلاسیک‌کننده نیست؛ «بیشتر با محیط در ارتباط بودن» است که واهمدوسی را آنی می‌کند.',
		),
		'decoherence-interference' => array(
			'file'     => 'decoherence/decoherence-interference.webp',
			'slug'     => 'decoherence',
			'alt'      => 'مقایسهٔ الگوی تداخل (فاز سالم) در برابر الگوی بدون تداخل (فاز واهمدوش‌شده)',
			'caption'  => 'تا وقتی فاز سالم است، تداخل می‌بینید؛ بعد از واهمدوسی، فقط «میانگین» می‌ماند.',
		),
		// ---------------- observer ----------------
		'observer-feature' => array(
			'file'     => 'observer/observer-feature.webp',
			'slug'     => 'observer',
			'featured' => true,
			'alt'      => 'آزمایش دو شکاف: ذراتی که از دو شکاف عبور می‌کنند و الگوی تداخل از نوارهای روشن و تاریک روی پرده می‌سازند',
			'caption'  => '«نگاه» در کوانتوم یک فوتون است که می‌پرد — نه یک ذهن که می‌بیند.',
		),
		'observer-slits-compare' => array(
			'file'     => 'observer/observer-slits-compare.webp',
			'slug'     => 'observer',
			'alt'      => 'مقایسهٔ نتیجهٔ آزمایش دو شکاف: بدون اندازه‌گیری (الگوی تداخل) در برابر با اندازه‌گیری (دو نوار ساده بدون تداخل)',
			'caption'  => '«ثبت» خود، الگو را تغییر می‌دهد — حتی اگر کسی نتایج را نخواند.',
		),
		'observer-von-neumann-chain' => array(
			'file'     => 'observer/observer-von-neumann-chain.webp',
			'slug'     => 'observer',
			'alt'      => 'زنجیرهٔ اندازه‌گیری وون‌نومان: از اسپین تا مغز، با یک «برش» خط‌چین که می‌تواند بین هر دو حلقه قرار بگیرد',
			'caption'  => 'جای «برش» را هر جا که بکشید، پیش‌بینی‌ها عوض نمی‌شوند — اما پرسش «چرا یک جواب» باز می‌ماند.',
		),
		// ---------------- quantum-state ----------------
		'quantum-state-feature' => array(
			'file'     => 'quantum-state/quantum-state-feature.webp',
			'slug'     => 'quantum-state',
			'featured' => true,
			'alt'      => 'ابرِ احتمالِ یک الکترون دور هسته؛ نمادی از «حالت کوانتومی» — توصیف کاملی که نه یک نقطه است نه یک مدار',
			'caption'  => 'حالت کوانتومی «پروندهٔ کامل» سامانه است — نه یک نقطه در فضا.',
		),
		'quantum-state-compare' => array(
			'file'     => 'quantum-state/quantum-state-compare.webp',
			'slug'     => 'quantum-state',
			'alt'      => 'مقایسهٔ توصیف حالت: شیء کلاسیک (توپ + بردار سرعت) در برابر الکترون (ابرِ احتمال = حالت کوانتومی)',
			'caption'  => 'پرسش «کجاست؟» برای توپ کافی است؛ برای الکترون، «حالت» لازم است.',
		),
		// ---------------- wave-function ----------------
		'wave-function-feature' => array(
			'file'     => 'wave-function/wave-function-feature.webp',
			'slug'     => 'wave-function',
			'featured' => true,
			'alt'      => 'ابرِ احتمالِ یک الکترون دور هسته همراه با موج؛ نمادی از تابع موج — نقشه‌ای از جایی که الکترون «ممکن است» یافت شود',
			'caption'  => 'تابع موج «نقشهٔ احتمال» است — نه مداری که الکترون رویش بچرخد.',
		),
		'wavefunction-born' => array(
			'file'     => 'wave-function/wavefunction-born.webp',
			'slug'     => 'wave-function',
			'alt'      => 'مقایسهٔ تابع موج ψ (با بخش‌های مثبت و منفی) در برابر «احتمال» |ψ|² (همهٔ آن مثبت) — قاعدهٔ بورن',
			'caption'  => 'دامنه می‌تواند منفی باشد؛ «احتمال» نه. همین «به توان دو»، کل ماجراست.',
		),
		'wavefunction-cancel' => array(
			'file'     => 'wave-function/wavefunction-cancel.webp',
			'slug'     => 'wave-function',
			'alt'      => 'دو دامنهٔ هم‌اندازه با علامت مخالف که روی هم صفر می‌شوند؛ توضیح ریاضیِ «نوار تاریک» آزمایش دو شکاف',
			'caption'  => '«باز کردن» یک راه اضافی، «رسیدن» را ناممکن کرد — تداخل، قلب کوانتوم است.',
		),
	);
}
