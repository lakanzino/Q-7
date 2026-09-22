<?php
/**
 * Scheduled scans for Qpedia SEO Pro.
 *
 * @package QpediaSEO
 */

namespace QpediaSEO;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Weekly full scan and daily quick check.
 */
class Cron {

	const HOOK_WEEKLY = 'qpedia_seo_weekly';
	const HOOK_DAILY  = 'qpedia_seo_daily';

	/**
	 * History cap.
	 *
	 * @var int
	 */
	const HISTORY_LIMIT = 12;

	/**
	 * Singleton instance.
	 *
	 * @var Cron|null
	 */
	private static $instance = null;

	/**
	 * Get singleton.
	 *
	 * @return Cron
	 */
	public static function instance(): Cron {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Hidden constructor.
	 */
	private function __construct() {}

	/**
	 * Register cron hooks and the weekly interval.
	 *
	 * @return void
	 */
	public function init(): void {
		add_filter( 'cron_schedules', array( $this, 'add_schedules' ) );
		add_action( self::HOOK_WEEKLY, array( $this, 'run_weekly_scan' ) );
		add_action( self::HOOK_DAILY, array( $this, 'run_daily_quick' ) );
	}

	/**
	 * Add a weekly interval when WordPress does not provide one.
	 *
	 * @param array $schedules Existing schedules.
	 * @return array
	 */
	public function add_schedules( array $schedules ): array {
		if ( ! isset( $schedules['weekly'] ) ) {
			$schedules['weekly'] = array(
				'interval' => WEEK_IN_SECONDS,
				'display'  => __( 'Once Weekly', 'qpedia-seo-pro' ),
			);
		}
		return $schedules;
	}

	/**
	 * Schedule the weekly full scan.
	 *
	 * @return void
	 */
	public static function schedule_weekly_scan(): void {
		self::ensure_weekly_interval();

		if ( ! wp_next_scheduled( self::HOOK_WEEKLY ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'weekly', self::HOOK_WEEKLY );
		}
	}

	/**
	 * Schedule the daily quick check.
	 *
	 * @return void
	 */
	public static function schedule_daily_quick(): void {
		if ( ! wp_next_scheduled( self::HOOK_DAILY ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', self::HOOK_DAILY );
		}
	}

	/**
	 * Clear every Qpedia cron event.
	 *
	 * @return void
	 */
	public static function unschedule(): void {
		wp_clear_scheduled_hook( self::HOOK_WEEKLY );
		wp_clear_scheduled_hook( self::HOOK_DAILY );

		$weekly = wp_next_scheduled( self::HOOK_WEEKLY );
		if ( $weekly ) {
			wp_unschedule_event( $weekly, self::HOOK_WEEKLY );
		}

		$daily = wp_next_scheduled( self::HOOK_DAILY );
		if ( $daily ) {
			wp_unschedule_event( $daily, self::HOOK_DAILY );
		}
	}

	/**
	 * Full collect + analyze. Stores history (last 12) and score delta.
	 *
	 * @return void
	 */
	public function run_weekly_scan(): void {
		$settings = Core::get_settings();
		if ( empty( $settings['weekly_scan'] ) ) {
			return;
		}

		if ( function_exists( 'wp_raise_memory_limit' ) ) {
			wp_raise_memory_limit( 'admin' );
		}
		if ( function_exists( 'set_time_limit' ) ) {
			@set_time_limit( 180 );
		}

		$collected = Collector::instance()->collect_all();
		$analyzed  = Analyzer::instance()->analyze_all( $collected );

		$site_score = 0;
		$site_grade = 'F';
		if ( isset( $analyzed['site_score'] ) && is_array( $analyzed['site_score'] ) ) {
			$site_score = isset( $analyzed['site_score']['score'] ) ? (int) $analyzed['site_score']['score'] : 0;
			$site_grade = isset( $analyzed['site_score']['grade'] ) ? (string) $analyzed['site_score']['grade'] : Core::grade( $site_score );
		} elseif ( isset( $analyzed['site_score'] ) && is_numeric( $analyzed['site_score'] ) ) {
			$site_score = (int) $analyzed['site_score'];
			$site_grade = Core::grade( $site_score );
		}

		$history = get_option( 'qpedia_seo_pro_scan_history', array() );
		if ( ! is_array( $history ) ) {
			$history = array();
		}

		$previous = null;
		if ( ! empty( $history ) ) {
			$last     = $history[ count( $history ) - 1 ];
			$previous = isset( $last['site_score'] ) ? (int) $last['site_score'] : null;
		}

		$delta = ( null !== $previous ) ? ( $site_score - $previous ) : 0;
		$trend = 'same';
		if ( $delta > 0 ) {
			$trend = 'up';
		} elseif ( $delta < 0 ) {
			$trend = 'down';
		}

		$history[] = array(
			'at'              => current_time( 'mysql' ),
			'at_gmt'          => current_time( 'mysql', true ),
			'site_score'      => $site_score,
			'site_grade'      => $site_grade,
			'previous_score'  => $previous,
			'delta'           => $delta,
			'trend'           => $trend,
			'summary'         => isset( $analyzed['summary'] ) && is_array( $analyzed['summary'] ) ? $analyzed['summary'] : array(),
			'counts'          => isset( $analyzed['counts'] ) && is_array( $analyzed['counts'] ) ? $analyzed['counts'] : array(),
		);

		if ( count( $history ) > self::HISTORY_LIMIT ) {
			$history = array_slice( $history, -1 * self::HISTORY_LIMIT );
		}

		update_option( 'qpedia_seo_pro_scan_history', array_values( $history ), false );
	}

	/**
	 * Count content published in the last 24h and ping the homepage.
	 *
	 * @return void
	 */
	public function run_daily_quick(): void {
		$settings = Core::get_settings();
		if ( empty( $settings['daily_quick'] ) ) {
			return;
		}

		global $wpdb;

		$since = gmdate( 'Y-m-d H:i:s', time() - DAY_IN_SECONDS );

		$new_articles = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(ID) FROM {$wpdb->posts} WHERE post_status = %s AND post_type = %s AND post_date_gmt >= %s",
				'publish',
				'quantum_article',
				$since
			)
		);

		$new_scientists = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(ID) FROM {$wpdb->posts} WHERE post_status = %s AND post_type = %s AND post_date_gmt >= %s",
				'publish',
				'quantum_scientist',
				$since
			)
		);

		$new_pages = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(ID) FROM {$wpdb->posts} WHERE post_status = %s AND post_type = %s AND post_date_gmt >= %s",
				'publish',
				'page',
				$since
			)
		);

		$home        = trailingslashit( Core::home_url() );
		$response    = wp_remote_get(
			$home,
			array(
				'timeout'     => 8,
				'sslverify'   => true,
				'redirection' => 5,
			)
		);
		$home_error  = '';
		$home_status = 0;
		if ( is_wp_error( $response ) ) {
			$home_error  = $response->get_error_message();
			$home_status = 0;
		} else {
			$home_status = (int) wp_remote_retrieve_response_code( $response );
		}

		$payload = array(
			'at'             => current_time( 'mysql' ),
			'new_articles'   => $new_articles,
			'new_scientists' => $new_scientists,
			'new_pages'      => $new_pages,
			'new_published'  => $new_articles + $new_scientists + $new_pages,
			'home_url'       => $home,
			'home_status'    => $home_status,
			'home_ok'        => ( $home_status >= 200 && $home_status < 400 ),
			'home_is_404'    => ( 404 === $home_status ),
			'home_error'     => $home_error,
		);

		set_transient( 'qpedia_seo_pro_daily_quick', $payload, DAY_IN_SECONDS );

		$progress = get_transient( 'qpedia_seo_pro_scan_progress' );
		if ( ! is_array( $progress ) ) {
			$progress = array();
		}
		$progress['daily_quick'] = $payload;
		set_transient( 'qpedia_seo_pro_scan_progress', $progress, DAY_IN_SECONDS );
	}

	/**
	 * Register the weekly interval before scheduling on activation.
	 *
	 * @return void
	 */
	private static function ensure_weekly_interval(): void {
		add_filter( 'cron_schedules', array( self::instance(), 'add_schedules' ) );
	}
}
