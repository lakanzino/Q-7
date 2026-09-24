<?php
/**
 * Plugin Name: Qpedia Featured Images — تصاویر شاخص جدید (بستهٔ ۱ از ۲)
 * Plugin URI:  https://qpedia.ir/
 * Description: بستهٔ ۱ از ۲ — تصاویر شاخص تازه (WebP، ۱۶:۹، نام‌گذاری با اسلاگ انگلیسی) را جای تصویر قبلی می‌گذارد، تصویر قبلی را حذف می‌کند و متن جانشین (alt) استاندارد گوگل را با کلمهٔ کلیدی هر مقاله ثبت می‌کند. اجرای مرحله‌ای (۵ مقاله در هر مرحله) تا مرورگر قطع نشود.
 * Version:     1.2.0
 * Author:      Qpedia
 * Author URI:  https://qpedia.ir/about-us/
 * License:     GPL-2.0-or-later
 * Text Domain: qp-featured-images-1
 * Requires PHP: 7.4
 *
 * @package QP_Featured_Images
 */

defined( 'ABSPATH' ) || exit;

define( 'QPFI_VERSION', '1.2.0' );

/* ── شمارهٔ این بسته ──────────────────────────────────────────
 * تصاویر به دو بستهٔ کوچک‌تر تقسیم شده‌اند تا نصب و اجرا سریع باشد.
 * در ساخت نهایی، مقدارهای زیر برای هر بسته خودکار تنظیم می‌شود.
 */
define( 'QPFI_PART', 1 );                // شمارهٔ این بسته
define( 'QPFI_PARTS_TOTAL', 2 );         // تعداد بسته‌ها
define( 'QPFI_ARTICLES_TOTAL', 55 );     // تعداد همهٔ مقاله‌های دارای تصویر تازه
define( 'QPFI_OTHER_PREFIX', 'qpfb' );   // پیشوند بستهٔ دیگر (برای تشخیص فعال بودن)
define( 'QPFI_BATCH_SIZE', 5 );          // چند مقاله در هر مرحلهٔ اجرا
define( 'QPFI_FILE', __FILE__ );
define( 'QPFI_DIR', plugin_dir_path( __FILE__ ) );
define( 'QPFI_URL', plugin_dir_url( __FILE__ ) );
define( 'QPFI_OPTION', 'qpfi_options' );
define( 'QPFI_LOG', 'qpfi_last_run' );

require_once QPFI_DIR . 'includes/replacer.php';

if ( is_admin() ) {
	require_once QPFI_DIR . 'includes/admin.php';
}

/**
 * برچسب این بسته.
 *
 * @return string
 */
function qpfi_part_label() {
	return sprintf(
		'تصاویر شاخص جدید — بستهٔ %s از %s',
		number_format_i18n( QPFI_PART ),
		number_format_i18n( QPFI_PARTS_TOTAL )
	);
}

/**
 * آیا بستهٔ دیگر فعال است؟
 *
 * @return bool
 */
function qpfi_other_part_active() {
	return function_exists( QPFI_OTHER_PREFIX . '_map' );
}

/**
 * فهرست نگاشت.
 *
 * @return array
 */
function qpfi_map() {
	static $map = null;

	if ( null === $map ) {
		$loaded = require QPFI_DIR . 'includes/map.php';
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
function qpfi_image_path( $file ) {
	return QPFI_DIR . 'includes/images/' . $file;
}

/**
 * گزینه‌ها.
 *
 * @return array
 */
function qpfi_options() {
	$defaults = array(
		'backup_old' => 1,   // نگه‌داشتن نسخهٔ پشتیبان از تصویر قبلی در uploads/qp-fi-backup/
		'set_alt'    => 1,   // ثبت alt فارسی روی تصویر تازه
	);

	$saved = get_option( QPFI_OPTION, array() );

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
function qpfi_activate() {
	update_option( 'qpfi_version', QPFI_VERSION, false );
}
register_activation_hook( __FILE__, 'qpfi_activate' );

/**
 * پیوند سریع در فهرست افزونه‌ها.
 *
 * @param array $links پیوندها.
 * @return array
 */
function qpfi_action_links( $links ) {
	$links[] = '<a href="' . esc_url( admin_url( 'tools.php?page=qpfi' ) ) . '">' . esc_html( sprintf( 'تصاویر شاخص جدید (بستهٔ %s)', number_format_i18n( QPFI_PART ) ) ) . '</a>';

	return $links;
}
add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), 'qpfi_action_links' );
