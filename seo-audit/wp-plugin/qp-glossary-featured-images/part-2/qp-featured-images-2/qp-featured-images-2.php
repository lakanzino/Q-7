<?php
/**
 * Plugin Name: Qpedia Featured Images — بستهٔ ۲ از ۲ (تصاویر شاخص جدید)
 * Plugin URI:  https://qpedia.ir/
 * Description: بستهٔ ۲ از ۲ — تصاویر شاخص ۲۸ مقاله را جای تصویر قبلی می‌گذارد، تصویر قبلی را حذف می‌کند و متن جانشین (alt) استاندارد گوگل را با کلمهٔ کلیدی هر مقاله ثبت می‌کند. فایل‌ها سبک (WebP، ۱۶:۹) هستند و اجرا مرحله‌ای است تا نصب و اجرا سریع باشد.
 * Version:     1.2.0
 * Author:      Qpedia
 * Author URI:  https://qpedia.ir/about-us/
 * License:     GPL-2.0-or-later
 * Text Domain: qp-featured-images-2
 * Requires PHP: 7.4
 *
 * @package QP_Featured_Images_Part_2
 */

defined( 'ABSPATH' ) || exit;

define( 'QPFB_VERSION', '1.2.0' );

/* ── شمارهٔ این بسته ──────────────────────────────────────────
 * تصاویر به دو بستهٔ کوچک‌تر تقسیم شده‌اند تا نصب و اجرا سریع باشد.
 * در ساخت نهایی، مقدارهای زیر برای هر بسته خودکار تنظیم می‌شود.
 */
define( 'QPFB_PART', 2 );                // شمارهٔ این بسته
define( 'QPFB_PARTS_TOTAL', 2 );         // تعداد بسته‌ها
define( 'QPFB_ARTICLES_TOTAL', 55 );     // تعداد همهٔ مقاله‌های دارای تصویر تازه
define( 'QPFB_OTHER_PREFIX', 'qpfa' );   // پیشوند بستهٔ دیگر (برای تشخیص فعال بودن)
define( 'QPFB_BATCH_SIZE', 5 );          // چند مقاله در هر مرحلهٔ اجرا
define( 'QPFB_FILE', __FILE__ );
define( 'QPFB_DIR', plugin_dir_path( __FILE__ ) );
define( 'QPFB_URL', plugin_dir_url( __FILE__ ) );
define( 'QPFB_OPTION', 'qpfb_options' );
define( 'QPFB_LOG', 'qpfb_last_run' );

require_once QPFB_DIR . 'includes/replacer.php';

if ( is_admin() ) {
	require_once QPFB_DIR . 'includes/admin.php';
}

/**
 * برچسب این بسته.
 *
 * @return string
 */
function qpfb_part_label() {
	return sprintf(
		'تصاویر شاخص جدید — بستهٔ %s از %s',
		number_format_i18n( QPFB_PART ),
		number_format_i18n( QPFB_PARTS_TOTAL )
	);
}

/**
 * آیا بستهٔ دیگر فعال است؟
 *
 * @return bool
 */
function qpfb_other_part_active() {
	return function_exists( QPFB_OTHER_PREFIX . '_map' );
}

/**
 * فهرست نگاشت.
 *
 * @return array
 */
function qpfb_map() {
	static $map = null;

	if ( null === $map ) {
		$loaded = require QPFB_DIR . 'includes/map.php';
		$map    = is_array( $loaded ) ? $loaded : array();
	}

	return $map;
}

/**
 * مسیر فایل تصویر داخل افزونه.
 *
 * @param string $file نام فایل.
 * @return string
 */
function qpfb_image_path( $file ) {
	return QPFB_DIR . 'includes/images/' . $file;
}

/**
 * گزینه‌ها.
 *
 * @return array
 */
function qpfb_options() {
	$defaults = array(
		'backup_old' => 1,   // نگه‌داشتن نسخهٔ پشتیبان از تصویر قبلی در uploads/qp-fi-backup/
		'set_alt'    => 1,   // ثبت alt فارسی روی تصویر تازه
	);

	$saved = get_option( QPFB_OPTION, array() );

	if ( ! is_array( $saved ) ) {
		$saved = array();
	}

	return array_merge( $defaults, $saved );
}

/**
 * فعال‌سازی.
 *
 * @return void
 */
function qpfb_activate() {
	update_option( 'qpfb_version', QPFB_VERSION, false );
}
register_activation_hook( __FILE__, 'qpfb_activate' );

/**
 * پیوند سریع در فهرست افزونه‌ها.
 *
 * @param array $links پیوندها.
 * @return array
 */
function qpfb_action_links( $links ) {
	$links[] = '<a href="' . esc_url( admin_url( 'tools.php?page=qpfb' ) ) . '">' . esc_html( sprintf( 'تصاویر شاخص جدید (بستهٔ %s)', number_format_i18n( QPFB_PART ) ) ) . '</a>';

	return $links;
}
add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), 'qpfb_action_links' );
