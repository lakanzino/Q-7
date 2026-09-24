<?php
/**
 * Plugin Name: Qpedia Glossary English Slugs — اسلاگ انگلیسی اصطلاحات
 * Plugin URI:  https://qpedia.ir/glossary/
 * Description: اسلاگ فارسی/ترانسلیریت اصطلاحات کوانتومی را با اسلاگ انگلیسی استاندارد جایگزین می‌کند، «نام و معنی انگلیسی» هر اصطلاح را ثبت می‌کند و آدرس‌های قبلی را با ۳۰۱ به آدرس تازه می‌فرستد. مستقل از قالب کار می‌کند.
 * Version:     1.0.0
 * Author:      Qpedia
 * Author URI:  https://qpedia.ir/about-us/
 * License:     GPL-2.0-or-later
 * Text Domain: qp-glossary-english-slugs
 * Requires PHP: 7.4
 *
 * @package QP_Glossary_English_Slugs
 */

defined( 'ABSPATH' ) || exit;

define( 'QPGSL_VERSION', '1.0.0' );
define( 'QPGSL_FILE', __FILE__ );
define( 'QPGSL_DIR', plugin_dir_path( __FILE__ ) );
define( 'QPGSL_URL', plugin_dir_url( __FILE__ ) );
define( 'QPGSL_OPTION', 'qpgsl_options' );
define( 'QPGSL_MAX_SLUG_HISTORY', 24 );

require_once QPGSL_DIR . 'includes/functions.php';
require_once QPGSL_DIR . 'includes/redirects.php';

if ( is_admin() ) {
	require_once QPGSL_DIR . 'includes/admin.php';
}

/**
 * فرهنگ نگاشت فارسی ← انگلیسی.
 *
 * @return array
 */
function qpgsl_dictionary() {
	static $dictionary = null;

	if ( null === $dictionary ) {
		$loaded     = require QPGSL_DIR . 'includes/dictionary.php';
		$dictionary = is_array( $loaded ) ? $loaded : array();
	}

	return apply_filters( 'qpgsl_dictionary', $dictionary );
}

/**
 * نوع‌های محتوایی که افزونه روی آن‌ها کار می‌کند.
 *
 * @return string[]
 */
function qpgsl_post_types() {
	$candidates = array( 'qp_glossary', 'quantum_term', 'quantum_glossary', 'glossary' );
	$found      = array();

	foreach ( $candidates as $candidate ) {
		if ( post_type_exists( $candidate ) ) {
			$found[] = $candidate;
		}
	}

	// اگر هیچ‌کدام ثبت نشده بود، همان نوع اصلی را نگه می‌داریم تا پیام راهنما نشان داده شود.
	if ( empty( $found ) ) {
		$found = array( 'qp_glossary' );
	}

	return apply_filters( 'qpgsl_post_types', $found );
}

/**
 * گزینه‌های افزونه.
 *
 * @return array
 */
function qpgsl_options() {
	$defaults = array(
		'batch_size'    => 200,
		'set_en_meta'   => 1,
		'save_gloss'    => 1,
		'update_latin'  => 1,
		'keep_old_301'  => 1,
	);

	$saved = get_option( QPGSL_OPTION, array() );
	if ( ! is_array( $saved ) ) {
		$saved = array();
	}

	return array_merge( $defaults, $saved );
}

/**
 * نگاشت‌های دستی کاربر (اولویت بر فرهنگ پیش‌فرض).
 *
 * @return array
 */
function qpgsl_overrides() {
	$overrides = get_option( 'qpgsl_overrides', array() );
	return is_array( $overrides ) ? $overrides : array();
}

/**
 * فعال‌سازی افزونه.
 *
 * @return void
 */
function qpgsl_activate() {
	update_option( 'qpgsl_version', QPGSL_VERSION, false );
	flush_rewrite_rules();
}
register_activation_hook( __FILE__, 'qpgsl_activate' );

/**
 * غیرفعال‌سازی افزونه.
 *
 * @return void
 */
function qpgsl_deactivate() {
	flush_rewrite_rules();
}
register_deactivation_hook( __FILE__, 'qpgsl_deactivate' );

/**
 * پیوند سریع به صفحهٔ افزونه در فهرست افزونه‌ها.
 *
 * @param array $links پیوندها.
 * @return array
 */
function qpgsl_action_links( $links ) {
	$url = admin_url( 'edit.php?post_type=qp_glossary&page=qpgsl' );

	if ( ! post_type_exists( 'qp_glossary' ) ) {
		$url = admin_url( 'tools.php?page=qpgsl' );
	}

	$links[] = '<a href="' . esc_url( $url ) . '">جایگزینی اسلاگ‌ها</a>';

	return $links;
}
add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), 'qpgsl_action_links' );
