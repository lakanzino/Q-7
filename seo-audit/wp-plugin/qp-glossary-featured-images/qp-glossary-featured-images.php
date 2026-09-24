<?php
/**
 * Plugin Name: Qpedia Featured Images — تصاویر شاخص جدید
 * Plugin URI:  https://qpedia.ir/
 * Description: ۵۵ تصویر شاخص تازه (WebP، نسبت ۱۶:۹، نام‌گذاری با اسلاگ انگلیسی) را جای تصویر قبلی مقاله‌ها می‌گذارد و تصویر قبلی را حذف می‌کند. اگر تصویر قبلی با مقالهٔ دیگری مشترک باشد، حذف نمی‌شود.
 * Version:     1.0.0
 * Author:      Qpedia
 * Author URI:  https://qpedia.ir/about-us/
 * License:     GPL-2.0-or-later
 * Text Domain: qp-featured-images
 * Requires PHP: 7.4
 *
 * @package QP_Featured_Images
 */

defined( 'ABSPATH' ) || exit;

define( 'QPFI_VERSION', '1.0.0' );
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
	$links[] = '<a href="' . esc_url( admin_url( 'tools.php?page=qpfi' ) ) . '">تصاویر شاخص جدید</a>';

	return $links;
}
add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), 'qpfi_action_links' );
