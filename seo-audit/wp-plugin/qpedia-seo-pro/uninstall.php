<?php
/**
 * Uninstall handler for Qpedia SEO Pro.
 *
 * @package QpediaSEO
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

delete_option( 'qpedia_seo_pro_settings' );
delete_option( 'qpedia_seo_pro_last_scan' );
delete_option( 'qpedia_seo_pro_scan_history' );
delete_option( 'qpedia_seo_pro_activated' );

delete_transient( 'qpedia_seo_pro_scan_data' );
delete_transient( 'qpedia_seo_pro_scan_progress' );

global $wpdb;

$like_transient         = $wpdb->esc_like( '_transient_qpedia_seo_pro' ) . '%';
$like_transient_timeout = $wpdb->esc_like( '_transient_timeout_qpedia_seo_pro' ) . '%';
$like_site_transient    = $wpdb->esc_like( '_site_transient_qpedia_seo_pro' ) . '%';
$like_site_timeout      = $wpdb->esc_like( '_site_transient_timeout_qpedia_seo_pro' ) . '%';

$wpdb->query(
	$wpdb->prepare(
		"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s OR option_name LIKE %s OR option_name LIKE %s",
		$like_transient,
		$like_transient_timeout,
		$like_site_transient,
		$like_site_timeout
	)
);

$wpdb->query(
	$wpdb->prepare(
		"DELETE FROM {$wpdb->usermeta} WHERE meta_key = %s",
		'qpedia_seo_pro_metabox_hidden'
	)
);
