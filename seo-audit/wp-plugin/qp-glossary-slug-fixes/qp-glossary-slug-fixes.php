<?php
/**
 * Plugin Name: Qpedia Glossary Slug Fixes — اصلاح هدفمند اسلاگ اصطلاحات
 * Plugin URI:  https://qpedia.ir/glossary/
 * Description: فقط اصطلاح‌هایی که اسلاگ انگلیسی نادرست دارند (۵۹ مدخل) را به اسلاگ صحیح جابه‌جا می‌کند و آدرس قبلی را ۳۰۱ می‌کند. هیچ مدخل دیگری دست‌کاری نمی‌شود.
 * Version:     1.0.0
 * Author:      Qpedia
 * Author URI:  https://qpedia.ir/about-us/
 * License:     GPL-2.0-or-later
 * Text Domain: qp-glossary-slug-fixes
 * Requires PHP: 7.4
 *
 * @package QP_Glossary_Slug_Fixes
 */

defined( 'ABSPATH' ) || exit;

define( 'QPGSF_VERSION', '1.0.0' );
define( 'QPGSF_FILE', __FILE__ );
define( 'QPGSF_DIR', plugin_dir_path( __FILE__ ) );
define( 'QPGSF_URL', plugin_dir_url( __FILE__ ) );
define( 'QPGSF_LOG_OPTION', 'qpgsf_run_log' );

require_once QPGSF_DIR . 'includes/fixer.php';

if ( is_admin() ) {
	require_once QPGSF_DIR . 'includes/admin.php';
}

/**
 * نوع‌های محتوایی واژه‌نامه.
 *
 * @return string[]
 */
function qpgsf_post_types() {
	$candidates = array( 'qp_glossary', 'quantum_term', 'quantum_glossary', 'glossary' );
	$found      = array();

	foreach ( $candidates as $candidate ) {
		if ( post_type_exists( $candidate ) ) {
			$found[] = $candidate;
		}
	}

	return empty( $found ) ? array( 'qp_glossary' ) : $found;
}

/**
 * فهرست اصلاح.
 *
 * @return array
 */
function qpgsf_fix_list() {
	static $list = null;

	if ( null === $list ) {
		$loaded = require QPGSF_DIR . 'includes/fix-list.php';
		$list   = is_array( $loaded ) ? $loaded : array();
	}

	return $list;
}

/**
 * فعال‌سازی.
 *
 * @return void
 */
function qpgsf_activate() {
	update_option( 'qpgsf_version', QPGSF_VERSION, false );
}
register_activation_hook( __FILE__, 'qpgsf_activate' );

/**
 * پیوند سریع در فهرست افزونه‌ها.
 *
 * @param array $links پیوندها.
 * @return array
 */
function qpgsf_action_links( $links ) {
	$parent = post_type_exists( 'qp_glossary' ) ? 'edit.php?post_type=qp_glossary' : 'tools.php';
	$links[] = '<a href="' . esc_url( admin_url( $parent . '&page=qpgsf' ) ) . '">اجرای اصلاح اسلاگ‌ها</a>';

	return $links;
}
add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), 'qpgsf_action_links' );
