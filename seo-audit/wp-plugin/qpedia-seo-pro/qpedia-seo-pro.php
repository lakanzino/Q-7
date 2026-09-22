<?php
/**
 * Plugin Name: Qpedia SEO Pro
 * Plugin URI: https://qpedia.ir
 * Description: Dedicated technical SEO plugin for Quantum Pedia Farsi (qpedia.ir)
 * Version: 1.0.0
 * Author: Qpedia
 * Author URI: https://qpedia.ir
 * Text Domain: qpedia-seo-pro
 * Domain Path: /languages
 * Requires at least: 6.0
 * Requires PHP: 7.4
 *
 * @package QpediaSEO
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'QPEDIA_SEO_VERSION', '1.0.0' );
define( 'QPEDIA_SEO_FILE', __FILE__ );
define( 'QPEDIA_SEO_DIR', plugin_dir_path( __FILE__ ) );
define( 'QPEDIA_SEO_URL', plugin_dir_url( __FILE__ ) );

/**
 * PSR-4-compatible autoloader for the QpediaSEO namespace.
 *
 * Class names map to class-qpedia-{kebab}.php. Underscores become hyphens.
 * Admin, Dashboard and Metabox live in admin/; everything else in includes/.
 *
 * @param string $class Fully-qualified class name.
 * @return void
 */
spl_autoload_register(
	static function ( $class ) {
		$prefix = 'QpediaSEO\\';
		if ( 0 !== strpos( $class, $prefix ) ) {
			return;
		}

		$relative = substr( $class, strlen( $prefix ) );
		if ( false !== strpos( $relative, '\\' ) || '' === $relative ) {
			return;
		}

		$admin_classes = array( 'Admin', 'Dashboard', 'Metabox' );
		$dir           = in_array( $relative, $admin_classes, true ) ? 'admin' : 'includes';
		$file          = 'class-qpedia-' . str_replace( '_', '-', strtolower( $relative ) ) . '.php';
		$path          = QPEDIA_SEO_DIR . $dir . '/' . $file;

		if ( is_readable( $path ) ) {
			require_once $path;
		}
	}
);

register_activation_hook( QPEDIA_SEO_FILE, array( 'QpediaSEO\\Core', 'activate' ) );
register_deactivation_hook( QPEDIA_SEO_FILE, array( 'QpediaSEO\\Core', 'deactivate' ) );

/**
 * Load translations and boot the plugin core.
 *
 * @return void
 */
function qpedia_seo_pro_boot() {
	load_plugin_textdomain(
		'qpedia-seo-pro',
		false,
		dirname( plugin_basename( QPEDIA_SEO_FILE ) ) . '/languages'
	);

	\QpediaSEO\Core::instance()->init();
}
add_action( 'plugins_loaded', 'qpedia_seo_pro_boot' );
