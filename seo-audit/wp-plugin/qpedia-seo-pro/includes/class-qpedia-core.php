<?php
/**
 * Core singleton — bootstrap, helpers, settings and scan cache.
 *
 * @package QpediaSEO
 */

namespace QpediaSEO;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Central plugin API used by every other module.
 */
class Core {

	/**
	 * Singleton instance.
	 *
	 * @var Core|null
	 */
	private static $instance = null;

	/**
	 * Whether init() has already booted modules.
	 *
	 * @var bool
	 */
	private $booted = false;

	/**
	 * Get the singleton instance.
	 *
	 * @return Core
	 */
	public static function instance(): Core {
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
	 * Prevent cloning.
	 *
	 * @return void
	 */
	private function __clone() {}

	/**
	 * Prevent unserialization.
	 *
	 * @return void
	 */
	public function __wakeup() {
		self::$instance = $this;
	}

	/**
	 * Default option payload.
	 *
	 * @return array
	 */
	public static function get_default_settings(): array {
		return array(
			'site_url'                 => 'https://qpedia.ir',
			'site_name'                => 'Qpedia Farsi',
			'inject_schema'            => true,
			'inject_breadcrumb'        => true,
			'skip_schema_if_rank_math' => true,
			'weekly_scan'              => true,
			'daily_quick'              => true,
			'batch_size'               => 50,
		);
	}

	/**
	 * Plugin activation: seed options, schedule cron, mark activated.
	 *
	 * @return void
	 */
	public static function activate(): void {
		$defaults = self::get_default_settings();
		if ( false === get_option( 'qpedia_seo_pro_settings', false ) ) {
			add_option( 'qpedia_seo_pro_settings', $defaults, '', false );
		} else {
			$current = get_option( 'qpedia_seo_pro_settings', array() );
			if ( ! is_array( $current ) ) {
				$current = array();
			}
			update_option( 'qpedia_seo_pro_settings', wp_parse_args( $current, $defaults ), false );
		}

		if ( false === get_option( 'qpedia_seo_pro_scan_history', false ) ) {
			add_option( 'qpedia_seo_pro_scan_history', array(), '', false );
		}

		if ( class_exists( __NAMESPACE__ . '\\Cron' ) ) {
			Cron::schedule_weekly_scan();
			Cron::schedule_daily_quick();
		}

		update_option( 'qpedia_seo_pro_activated', 1, false );
	}

	/**
	 * Plugin deactivation: clear scheduled events.
	 *
	 * @return void
	 */
	public static function deactivate(): void {
		if ( class_exists( __NAMESPACE__ . '\\Cron' ) ) {
			Cron::unschedule();
		}
	}

	/**
	 * Boot every module. Missing class files are skipped so the plugin can
	 * load during incremental installs.
	 *
	 * @return void
	 */
	public function init(): void {
		if ( $this->booted ) {
			return;
		}
		$this->booted = true;

		$modules = array(
			Collector::class,
			Analyzer::class,
			Schema::class,
			Sitemap::class,
			Meta_Tags::class,
			Internal_Links::class,
			Content_Audit::class,
			Export::class,
			Scientist_SEO::class,
			Taxonomy_SEO::class,
			Image_Audit::class,
			Performance::class,
			Robots::class,
			Breadcrumb::class,
			Cron::class,
		);

		foreach ( $modules as $class ) {
			$this->boot_module( $class );
		}

		if ( is_admin() ) {
			$this->boot_module( Admin::class );
			$this->boot_module( Dashboard::class );
			$this->boot_module( Metabox::class );
		}
	}

	/**
	 * Instantiate a module and call init() when present.
	 *
	 * @param string $class Fully-qualified class name.
	 * @return void
	 */
	private function boot_module( string $class ): void {
		if ( ! class_exists( $class ) ) {
			return;
		}

		if ( method_exists( $class, 'instance' ) ) {
			$object = $class::instance();
			if ( is_object( $object ) && method_exists( $object, 'init' ) ) {
				$object->init();
			}
			return;
		}

		$object = new $class();
		if ( method_exists( $object, 'init' ) ) {
			$object->init();
		}
	}

	/**
	 * Count words in HTML using spaces and the Persian ZWNJ (U+200C).
	 *
	 * @param string $html Raw HTML or plain text.
	 * @return int
	 */
	public static function word_count( string $html ): int {
		$text = self::plain_text( $html );
		if ( '' === $text ) {
			return 0;
		}

		$parts = preg_split( '/[\s\x{200C}]+/u', $text, -1, PREG_SPLIT_NO_EMPTY );
		if ( ! is_array( $parts ) ) {
			return 0;
		}

		return count( $parts );
	}

	/**
	 * Strip tags/scripts and collapse whitespace.
	 *
	 * @param string $html Raw HTML.
	 * @return string
	 */
	public static function plain_text( string $html ): string {
		if ( '' === $html ) {
			return '';
		}

		$text = preg_replace( '/<script\b[^>]*>.*?<\/script>/is', ' ', $html );
		if ( ! is_string( $text ) ) {
			$text = $html;
		}
		$stripped = preg_replace( '/<style\b[^>]*>.*?<\/style>/is', ' ', $text );
		if ( is_string( $stripped ) ) {
			$text = $stripped;
		}

		$text = wp_strip_all_tags( $text, true );
		$text = html_entity_decode( $text, ENT_QUOTES, 'UTF-8' );
		$text = preg_replace( '/\s+/u', ' ', $text );
		if ( ! is_string( $text ) ) {
			return '';
		}

		return trim( $text );
	}

	/**
	 * Map a 0–100 score to letter grade A–F.
	 *
	 * @param int $score Numeric score.
	 * @return string
	 */
	public static function grade( int $score ): string {
		if ( $score >= 90 ) {
			return 'A';
		}
		if ( $score >= 70 ) {
			return 'B';
		}
		if ( $score >= 50 ) {
			return 'C';
		}
		if ( $score >= 30 ) {
			return 'D';
		}
		return 'F';
	}

	/**
	 * Merged plugin settings.
	 *
	 * @return array
	 */
	public static function get_settings(): array {
		$defaults = self::get_default_settings();
		$stored   = get_option( 'qpedia_seo_pro_settings', array() );
		if ( ! is_array( $stored ) ) {
			$stored = array();
		}

		$settings = wp_parse_args( $stored, $defaults );

		$settings['site_url']                 = untrailingslashit( (string) $settings['site_url'] );
		$settings['site_name']                = (string) $settings['site_name'];
		$settings['inject_schema']            = (bool) $settings['inject_schema'];
		$settings['inject_breadcrumb']        = (bool) $settings['inject_breadcrumb'];
		$settings['skip_schema_if_rank_math'] = (bool) $settings['skip_schema_if_rank_math'];
		$settings['weekly_scan']              = (bool) $settings['weekly_scan'];
		$settings['daily_quick']              = (bool) $settings['daily_quick'];
		$settings['batch_size']               = max( 1, (int) $settings['batch_size'] );

		return $settings;
	}

	/**
	 * Cached scan payload: transient first, then last_scan option.
	 *
	 * @return array|null
	 */
	public static function get_scan_data(): ?array {
		$data = get_transient( 'qpedia_seo_pro_scan_data' );
		if ( is_array( $data ) ) {
			return $data;
		}

		$data = get_option( 'qpedia_seo_pro_last_scan', null );
		return is_array( $data ) ? $data : null;
	}

	/**
	 * Persist scan payload to the 24h transient and the last_scan option.
	 *
	 * @param array $data Collected / analyzed dataset.
	 * @return void
	 */
	public static function set_scan_data( array $data ): void {
		set_transient( 'qpedia_seo_pro_scan_data', $data, DAY_IN_SECONDS );
		update_option( 'qpedia_seo_pro_last_scan', $data, false );
	}

	/**
	 * Canonical site origin without a trailing slash.
	 *
	 * @return string
	 */
	public static function home_url(): string {
		$settings = self::get_settings();
		$url      = isset( $settings['site_url'] ) ? (string) $settings['site_url'] : 'https://qpedia.ir';
		if ( '' === $url ) {
			$url = 'https://qpedia.ir';
		}
		return untrailingslashit( $url );
	}

	/**
	 * Whether Rank Math is loaded.
	 *
	 * @return bool
	 */
	public static function is_rank_math_active(): bool {
		if ( defined( 'RANK_MATH_VERSION' ) ) {
			return true;
		}
		if ( class_exists( '\\RankMath' ) || class_exists( '\\RankMath\\Helper' ) ) {
			return true;
		}
		if ( ! function_exists( 'is_plugin_active' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}
		return is_plugin_active( 'seo-by-rank-math/rank-math.php' );
	}

	/**
	 * Extract heading tags and their visible text.
	 *
	 * @param string $html Post content HTML.
	 * @return array[] List of {tag, text}.
	 */
	public static function extract_headings( string $html ): array {
		$headings = array();
		if ( '' === $html ) {
			return $headings;
		}

		if ( ! preg_match_all( '/<(h[1-6])[^>]*>(.*?)<\/\1>/is', $html, $matches, PREG_SET_ORDER ) ) {
			return $headings;
		}

		foreach ( $matches as $match ) {
			$headings[] = array(
				'tag'  => strtolower( $match[1] ),
				'text' => self::plain_text( $match[2] ),
			);
		}

		return $headings;
	}

	/**
	 * Extract internal (qpedia.ir) anchors from HTML.
	 *
	 * @param string $html Post content HTML.
	 * @return array[] List of {href, anchor, path}.
	 */
	public static function extract_internal_links( string $html ): array {
		$links = array();
		if ( '' === $html ) {
			return $links;
		}

		if ( ! preg_match_all( '/<a\s[^>]*href\s*=\s*["\']([^"\']+)["\'][^>]*>(.*?)<\/a>/is', $html, $matches, PREG_SET_ORDER ) ) {
			return $links;
		}

		foreach ( $matches as $match ) {
			$href = html_entity_decode( trim( $match[1] ), ENT_QUOTES, 'UTF-8' );
			if ( '' === $href || ! self::is_internal_url( $href ) ) {
				continue;
			}

			$links[] = array(
				'href'   => $href,
				'anchor' => self::plain_text( $match[2] ),
				'path'   => self::normalize_path( $href ),
			);
		}

		return $links;
	}

	/**
	 * Whether a URL points at qpedia.ir (or is a same-site relative path).
	 *
	 * @param string $url Raw href.
	 * @return bool
	 */
	public static function is_internal_url( string $url ): bool {
		$url = trim( $url );
		if ( '' === $url ) {
			return false;
		}

		$lower = strtolower( $url );
		if ( '#' === $url[0] || 0 === strpos( $lower, 'mailto:' ) || 0 === strpos( $lower, 'javascript:' ) || 0 === strpos( $lower, 'tel:' ) || 0 === strpos( $lower, 'data:' ) ) {
			return false;
		}

		if ( 0 === strpos( $url, '/' ) && 0 !== strpos( $url, '//' ) ) {
			return true;
		}

		if ( 0 === strpos( $url, '//' ) ) {
			$url = 'https:' . $url;
		}

		$host = wp_parse_url( $url, PHP_URL_HOST );
		if ( ! is_string( $host ) || '' === $host ) {
			return false;
		}

		$host = strtolower( $host );
		return ( 'qpedia.ir' === $host || 'www.qpedia.ir' === $host );
	}

	/**
	 * Normalize an href to a leading-slash path without query/fragment.
	 *
	 * @param string $url Raw URL or path.
	 * @return string
	 */
	public static function normalize_path( string $url ): string {
		$url = trim( $url );
		if ( '' === $url ) {
			return '/';
		}

		if ( 0 === strpos( $url, '//' ) ) {
			$url = 'https:' . $url;
		}

		$path = wp_parse_url( $url, PHP_URL_PATH );
		if ( ! is_string( $path ) || '' === $path ) {
			if ( 0 === strpos( $url, '/' ) && 0 !== strpos( $url, '//' ) ) {
				$path = $url;
			} else {
				$path = '/';
			}
		}

		$path = '/' . ltrim( $path, '/' );
		$qpos = strpos( $path, '?' );
		if ( false !== $qpos ) {
			$path = substr( $path, 0, $qpos );
		}
		$hash = strpos( $path, '#' );
		if ( false !== $hash ) {
			$path = substr( $path, 0, $hash );
		}

		if ( '/' !== $path ) {
			$path = untrailingslashit( $path );
		}

		return $path;
	}

	/**
	 * Absolute permalink for a known Qpedia content type.
	 *
	 * @param string $slug Slug.
	 * @param string $type article|scientist|topic|page.
	 * @return string
	 */
	public static function permalink_for( string $slug, string $type = 'article' ): string {
		$home = self::home_url();
		$slug = ltrim( $slug, '/' );

		if ( 'scientist' === $type ) {
			return $home . '/scientists/' . $slug . '/';
		}
		if ( 'topic' === $type ) {
			return $home . '/topic/' . $slug . '/';
		}

		return $home . '/' . $slug . '/';
	}

	/**
	 * Safe multibyte string length.
	 *
	 * @param string $text Input.
	 * @return int
	 */
	public static function length( string $text ): int {
		if ( function_exists( 'mb_strlen' ) ) {
			return (int) mb_strlen( $text, 'UTF-8' );
		}
		return strlen( $text );
	}

	/**
	 * Case-insensitive multibyte needle search.
	 *
	 * @param string $haystack Haystack.
	 * @param string $needle   Needle.
	 * @return bool
	 */
	public static function contains( string $haystack, string $needle ): bool {
		if ( '' === $needle ) {
			return false;
		}
		if ( function_exists( 'mb_stripos' ) ) {
			return false !== mb_stripos( $haystack, $needle, 0, 'UTF-8' );
		}
		return false !== stripos( $haystack, $needle );
	}
}
