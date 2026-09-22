<?php
/**
 * Qpedia SEO Pro admin screens, menus, assets, and AJAX.
 *
 * @package QpediaSEO
 */

namespace QpediaSEO;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Admin controller.
 */
class Admin {

	const NONCE           = 'qpedia_seo_pro';
	const CAP             = 'manage_options';
	const MENU            = 'qpedia-seo';
	const SCAN_DATA_KEY   = 'qpedia_seo_pro_scan_data';
	const SCAN_PROGRESS   = 'qpedia_seo_pro_scan_progress';
	const SETTINGS_KEY    = 'qpedia_seo_pro_settings';
	const HISTORY_KEY     = 'qpedia_seo_pro_scan_history';
	const LAST_EXPORT_KEY = 'qpedia_seo_pro_last_export';
	const ANALYSIS_META   = '_qpedia_seo_last_analysis';

	/**
	 * Singleton instance.
	 *
	 * @var Admin|null
	 */
	private static $instance = null;

	/**
	 * Scan step identifiers in processing order.
	 *
	 * @var string[]
	 */
	private $steps = array(
		'articles',
		'scientists',
		'pages',
		'images',
		'terms',
		'links',
		'technical',
		'analyze',
		'extras',
	);

	/**
	 * Get singleton and register hooks.
	 *
	 * @return Admin
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Hook WordPress.
	 */
	private function __construct() {
		$this->load_siblings();

		add_action( 'admin_menu', array( $this, 'register_menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue' ) );
		add_action( 'admin_init', array( $this, 'admin_init' ) );
		add_action( 'admin_notices', array( $this, 'first_scan_notice' ) );

		add_action( 'wp_ajax_qpedia_scan_start', array( $this, 'ajax_scan_start' ) );
		add_action( 'wp_ajax_qpedia_scan_batch', array( $this, 'ajax_scan_batch' ) );
		add_action( 'wp_ajax_qpedia_scan_status', array( $this, 'ajax_scan_status' ) );
		add_action( 'wp_ajax_qpedia_reanalyze_post', array( $this, 'ajax_reanalyze_post' ) );
		add_action( 'wp_ajax_qpedia_export_report', array( $this, 'ajax_export_report' ) );
		add_action( 'wp_ajax_qpedia_save_settings', array( $this, 'ajax_save_settings' ) );
		add_action( 'wp_ajax_qpedia_clear_cache', array( $this, 'ajax_clear_cache' ) );

		if ( class_exists( __NAMESPACE__ . '\\Metabox' ) ) {
			Metabox::instance();
		}
	}

	/**
	 * Load sibling admin classes if the autoloader has not.
	 */
	private function load_siblings() {
		$map = array(
			__NAMESPACE__ . '\\Dashboard' => __DIR__ . '/class-qpedia-dashboard.php',
			__NAMESPACE__ . '\\Metabox'   => __DIR__ . '/class-qpedia-metabox.php',
		);
		foreach ( $map as $class => $file ) {
			if ( ! class_exists( $class ) && is_readable( $file ) ) {
				require_once $file;
			}
		}
	}

	/**
	 * Plugin root directory (trailing slash).
	 *
	 * @return string
	 */
	public function plugin_dir() {
		return trailingslashit( dirname( __DIR__ ) );
	}

	/**
	 * Plugin root URL (trailing slash).
	 *
	 * @return string
	 */
	public function plugin_url() {
		return defined( 'QPEDIA_SEO_URL' ) ? QPEDIA_SEO_URL : trailingslashit( plugins_url( '', dirname( __DIR__ ) . '/qpedia-seo-pro.php' ) );
	}

	/**
	 * Register top-level menu and submenus.
	 */
	public function register_menu() {
		$icon = 'dashicons-chart-area';
		$png  = $this->plugin_dir() . 'assets/icon-128.png';
		if ( file_exists( $png ) ) {
			$icon = $this->plugin_url() . 'assets/icon-128.png';
		}

		add_menu_page(
			__( 'Qpedia SEO', 'qpedia-seo-pro' ),
			__( 'Qpedia SEO', 'qpedia-seo-pro' ),
			self::CAP,
			self::MENU,
			array( $this, 'render_dashboard' ),
			$icon,
			58
		);

		$pages = array(
			self::MENU                 => array( __( 'Dashboard', 'qpedia-seo-pro' ), array( $this, 'render_dashboard' ) ),
			'qpedia-seo-articles'      => array( __( 'Articles', 'qpedia-seo-pro' ), array( $this, 'render_articles' ) ),
			'qpedia-seo-scientists'    => array( __( 'Scientists', 'qpedia-seo-pro' ), array( $this, 'render_scientists' ) ),
			'qpedia-seo-taxonomy'      => array( __( 'Categories', 'qpedia-seo-pro' ), array( $this, 'render_taxonomy' ) ),
			'qpedia-seo-images'        => array( __( 'Images', 'qpedia-seo-pro' ), array( $this, 'render_images' ) ),
			'qpedia-seo-links'         => array( __( 'Internal links', 'qpedia-seo-pro' ), array( $this, 'render_links' ) ),
			'qpedia-seo-schema'        => array( __( 'Schema', 'qpedia-seo-pro' ), array( $this, 'render_schema' ) ),
			'qpedia-seo-technical'     => array( __( 'Technical', 'qpedia-seo-pro' ), array( $this, 'render_technical' ) ),
			'qpedia-seo-export'        => array( __( 'Export', 'qpedia-seo-pro' ), array( $this, 'render_export' ) ),
			'qpedia-seo-settings'      => array( __( 'Settings', 'qpedia-seo-pro' ), array( $this, 'render_settings' ) ),
		);

		foreach ( $pages as $slug => $item ) {
			add_submenu_page(
				self::MENU,
				$item[0],
				$item[0],
				self::CAP,
				$slug,
				$item[1]
			);
		}
	}

	/**
	 * Enqueue CSS/JS only on plugin pages and relevant post editors.
	 *
	 * @param string $hook Current admin hook.
	 */
	public function enqueue( $hook ) {
		$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		$is_plugin = ( '' !== $page && 0 === strpos( $page, 'qpedia-seo' ) );

		$screen      = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		$is_metabox  = $screen && in_array( $screen->base, array( 'post', 'post-new' ), true )
			&& in_array( $screen->post_type, array( 'quantum_article', 'quantum_scientist' ), true );

		if ( ! $is_plugin && ! $is_metabox ) {
			return;
		}

		$ver = '1.0.0';
		$url = $this->plugin_url();

		wp_enqueue_style(
			'qpedia-seo-admin',
			$url . 'admin/css/qpedia-admin.css',
			array( 'dashicons' ),
			$ver
		);

		wp_enqueue_script(
			'qpedia-seo-admin',
			$url . 'admin/js/qpedia-admin.js',
			array( 'jquery' ),
			$ver,
			true
		);

		wp_localize_script(
			'qpedia-seo-admin',
			'QpediaSEO',
			array(
				'ajax'      => admin_url( 'admin-ajax.php' ),
				'nonce'     => wp_create_nonce( self::NONCE ),
				'batchSize' => $this->batch_size(),
				'i18n'      => array(
					'scanning'        => __( 'Scanning the site…', 'qpedia-seo-pro' ),
					'scanComplete'    => __( 'Scan complete. Reloading…', 'qpedia-seo-pro' ),
					'scanError'       => __( 'Scan failed. Please try again.', 'qpedia-seo-pro' ),
					'confirmScan'     => __( 'Start a full SEO scan? This may take a few minutes.', 'qpedia-seo-pro' ),
					'confirmRescan'   => __( 'Run a new scan and replace the current report?', 'qpedia-seo-pro' ),
					'confirmCache'    => __( 'Clear cached scan data? You will need to scan again.', 'qpedia-seo-pro' ),
					'exporting'       => __( 'Preparing the ZIP archive…', 'qpedia-seo-pro' ),
					'exportError'     => __( 'Export failed. Please try again.', 'qpedia-seo-pro' ),
					'reanalyzing'     => __( 'Reanalyzing…', 'qpedia-seo-pro' ),
					'reanalyzeError'  => __( 'Reanalysis failed.', 'qpedia-seo-pro' ),
					'reanalyzeOk'     => __( 'Analysis updated.', 'qpedia-seo-pro' ),
					'saved'           => __( 'Settings saved.', 'qpedia-seo-pro' ),
					'saveError'       => __( 'Could not save settings.', 'qpedia-seo-pro' ),
					'cacheCleared'    => __( 'Cache cleared.', 'qpedia-seo-pro' ),
					'percentDone'     => __( '%s%% complete', 'qpedia-seo-pro' ),
					'stepArticles'    => __( 'Articles', 'qpedia-seo-pro' ),
					'stepScientists'  => __( 'Scientists', 'qpedia-seo-pro' ),
					'stepPages'       => __( 'Pages', 'qpedia-seo-pro' ),
					'stepImages'      => __( 'Images', 'qpedia-seo-pro' ),
					'stepTerms'       => __( 'Categories', 'qpedia-seo-pro' ),
					'stepLinks'       => __( 'Internal links', 'qpedia-seo-pro' ),
					'stepTechnical'   => __( 'Technical', 'qpedia-seo-pro' ),
					'stepAnalyze'     => __( 'Scoring', 'qpedia-seo-pro' ),
					'stepExtras'      => __( 'Extra audits', 'qpedia-seo-pro' ),
				),
			)
		);
	}

	/**
	 * Handle non-AJAX settings POST.
	 */
	public function admin_init() {
		if ( empty( $_POST['qpedia_save_settings'] ) ) {
			return;
		}
		if ( ! current_user_can( self::CAP ) ) {
			return;
		}
		check_admin_referer( self::NONCE, 'qpedia_seo_pro_nonce' );

		$this->persist_settings( wp_unslash( $_POST ) ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized

		wp_safe_redirect(
			add_query_arg(
				array(
					'page'    => 'qpedia-seo-settings',
					'updated' => '1',
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}

	/**
	 * First-scan admin notice after activation.
	 */
	public function first_scan_notice() {
		if ( ! current_user_can( self::CAP ) ) {
			return;
		}
		if ( ! get_option( 'qpedia_seo_pro_activated' ) ) {
			return;
		}
		$scan = $this->get_scan_data();
		if ( $this->has_scan( $scan ) ) {
			return;
		}

		$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( '' !== $page && 0 === strpos( $page, 'qpedia-seo' ) ) {
			return;
		}

		$url = admin_url( 'admin.php?page=qpedia-seo' );
		echo '<div class="notice notice-info qpedia-first-scan-notice"><p>';
		echo esc_html__( 'Qpedia SEO Pro is active. Run the initial scan to fill the dashboard.', 'qpedia-seo-pro' );
		echo ' <a class="button button-small" href="' . esc_url( $url ) . '">';
		echo esc_html__( 'Initial scan', 'qpedia-seo-pro' );
		echo '</a></p></div>';
	}

	/* -----------------------------------------------------------------
	 * Renderers
	 * ----------------------------------------------------------------- */

	/**
	 * Dashboard screen.
	 */
	public function render_dashboard() {
		$this->guard();
		$data = class_exists( __NAMESPACE__ . '\\Dashboard' ) ? Dashboard::prepare() : array( 'has_scan' => false );
		$this->include_view( 'dashboard', array( 'data' => $data ) );
	}

	/**
	 * Articles report.
	 */
	public function render_articles() {
		$this->guard();
		$scan     = $this->get_scan_data();
		$has_scan = $this->has_scan( $scan );
		$raw      = ( $has_scan && ! empty( $scan['articles'] ) && is_array( $scan['articles'] ) ) ? $scan['articles'] : array();
		$rows     = array();
		foreach ( $raw as $row ) {
			$rows[] = self::flatten_article( $row );
		}

		$grade     = isset( $_GET['qpedia_grade'] ) ? strtoupper( sanitize_text_field( wp_unslash( $_GET['qpedia_grade'] ) ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$category  = isset( $_GET['qpedia_category'] ) ? absint( $_GET['qpedia_category'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$min_score = isset( $_GET['qpedia_min_score'] ) ? absint( $_GET['qpedia_min_score'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$orderby   = isset( $_GET['orderby'] ) ? sanitize_key( wp_unslash( $_GET['orderby'] ) ) : 'score'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$order     = isset( $_GET['order'] ) && 'asc' === strtolower( sanitize_text_field( wp_unslash( $_GET['order'] ) ) ) ? 'asc' : 'desc'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		$categories = array();
		foreach ( $rows as $row ) {
			if ( ! empty( $row['category_id'] ) ) {
				$categories[ (int) $row['category_id'] ] = isset( $row['category'] ) ? $row['category'] : '';
			}
		}

		$filtered = array();
		foreach ( $rows as $row ) {
			$row_grade = isset( $row['grade'] ) ? strtoupper( (string) $row['grade'] ) : '';
			$row_cat   = isset( $row['category_id'] ) ? (int) $row['category_id'] : 0;
			$row_score = isset( $row['score'] ) ? (int) $row['score'] : 0;
			if ( $grade && ! in_array( $grade, array( 'A', 'B', 'C', 'D', 'F' ), true ) ) {
				$grade = '';
			}
			if ( $grade && $row_grade !== $grade ) {
				continue;
			}
			if ( $category && $row_cat !== $category ) {
				continue;
			}
			if ( $min_score && $row_score < $min_score ) {
				continue;
			}
			$filtered[] = $row;
		}

		$allowed_order = array( 'title', 'slug', 'score', 'grade', 'words', 'keyword', 'category', 'modified', 'issues_count' );
		if ( ! in_array( $orderby, $allowed_order, true ) ) {
			$orderby = 'score';
		}
		usort(
			$filtered,
			function ( $a, $b ) use ( $orderby, $order ) {
				$va = isset( $a[ $orderby ] ) ? $a[ $orderby ] : '';
				$vb = isset( $b[ $orderby ] ) ? $b[ $orderby ] : '';
				if ( is_numeric( $va ) && is_numeric( $vb ) ) {
					$cmp = (int) $va - (int) $vb;
				} else {
					$cmp = strcasecmp( (string) $va, (string) $vb );
				}
				return 'asc' === $order ? $cmp : -$cmp;
			}
		);

		$paged     = isset( $_GET['paged'] ) ? max( 1, absint( $_GET['paged'] ) ) : 1; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$per_page  = 20;
		$total     = count( $filtered );
		$pages     = max( 1, (int) ceil( $total / $per_page ) );
		$paged     = min( $paged, $pages );
		$offset    = ( $paged - 1 ) * $per_page;
		$articles  = array_slice( $filtered, $offset, $per_page );

		$this->include_view(
			'articles-report',
			array(
				'data'       => $scan,
				'has_scan'   => $has_scan,
				'articles'   => $articles,
				'categories' => $categories,
				'filters'    => array(
					'grade'     => $grade,
					'category'  => $category,
					'min_score' => $min_score,
					'orderby'   => $orderby,
					'order'     => $order,
				),
				'pagination' => array(
					'paged'    => $paged,
					'pages'    => $pages,
					'total'    => $total,
					'per_page' => $per_page,
				),
			)
		);
	}

	/**
	 * Scientists report.
	 */
	public function render_scientists() {
		$this->guard();
		$scan     = $this->get_scan_data();
		$has_scan = $this->has_scan( $scan );
		$raw      = ( $has_scan && ! empty( $scan['scientists'] ) && is_array( $scan['scientists'] ) ) ? $scan['scientists'] : array();
		$rows     = array();
		foreach ( $raw as $row ) {
			$rows[] = self::flatten_scientist( $row );
		}
		$summary  = ( $has_scan && ! empty( $scan['scientist_completeness']['summary'] ) ) ? $scan['scientist_completeness']['summary'] : $this->summarize_scientist_fields( $rows );

		$this->include_view(
			'scientists-report',
			array(
				'data'                  => $scan,
				'has_scan'              => $has_scan,
				'scientists'            => $rows,
				'completeness_summary'  => $summary,
			)
		);
	}

	/**
	 * Taxonomy report.
	 */
	public function render_taxonomy() {
		$this->guard();
		$scan     = $this->get_scan_data();
		$has_scan = $this->has_scan( $scan );
		$raw      = self::category_terms( $scan );
		$terms    = array();
		foreach ( $raw as $row ) {
			$terms[] = self::flatten_term( $row );
		}
		if ( $has_scan && ! empty( $scan['tags'] ) && is_array( $scan['tags'] ) && isset( $scan['tags']['total'] ) ) {
			$tags = $scan['tags'];
		} else {
			$tag_rows = ( ! empty( $scan['terms']['post_tag'] ) && is_array( $scan['terms']['post_tag'] ) ) ? $scan['terms']['post_tag'] : array();
			$unused   = 0;
			$dups     = array();
			$by_key   = array();
			foreach ( $tag_rows as $t ) {
				if ( isset( $t['count'] ) && 0 === (int) $t['count'] ) {
					$unused++;
				}
				$key = strtolower( preg_replace( '/[\s\-_]+/u', '', isset( $t['slug'] ) ? $t['slug'] : '' ) );
				$by_key[ $key ][] = $t;
			}
			foreach ( $by_key as $group ) {
				if ( count( $group ) > 1 ) {
					$dups[] = $group;
				}
			}
			$tags = array(
				'total'      => count( $tag_rows ),
				'unused'     => $unused,
				'duplicates' => array_slice( $dups, 0, 40 ),
			);
		}

		$this->include_view(
			'taxonomy-report',
			array(
				'data'         => $scan,
				'has_scan'     => $has_scan,
				'categories'   => $terms,
				'tags_summary' => $tags,
			)
		);
	}

	/**
	 * Images report.
	 */
	public function render_images() {
		$this->guard();
		$scan        = $this->get_scan_data();
		$has_scan    = $this->has_scan( $scan );
		$raw         = ( $has_scan && ! empty( $scan['images'] ) && is_array( $scan['images'] ) ) ? $scan['images'] : array();
		$rows        = array();
		foreach ( $raw as $row ) {
			$rows[] = self::flatten_image( $row );
		}
		$missing_alt = ! empty( $_GET['qpedia_missing_alt'] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		if ( $missing_alt ) {
			$rows = array_values(
				array_filter(
					$rows,
					function ( $row ) {
						return ! empty( $row['missing_alt'] ) || '' === trim( (string) ( isset( $row['alt'] ) ? $row['alt'] : '' ) );
					}
				)
			);
		}

		$paged    = isset( $_GET['paged'] ) ? max( 1, absint( $_GET['paged'] ) ) : 1; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$per_page = 30;
		$total    = count( $rows );
		$pages    = max( 1, (int) ceil( $total / $per_page ) );
		$paged    = min( $paged, $pages );
		$images   = array_slice( $rows, ( $paged - 1 ) * $per_page, $per_page );

		$this->include_view(
			'images-report',
			array(
				'data'              => $scan,
				'has_scan'          => $has_scan,
				'images'            => $images,
				'filter_missing_alt'=> $missing_alt,
				'pagination'        => array(
					'paged'    => $paged,
					'pages'    => $pages,
					'total'    => $total,
					'per_page' => $per_page,
				),
			)
		);
	}

	/**
	 * Internal links report.
	 */
	public function render_links() {
		$this->guard();
		$scan     = $this->get_scan_data();
		$has_scan = $this->has_scan( $scan );
		$links    = ( $has_scan && ! empty( $scan['links'] ) && is_array( $scan['links'] ) ) ? $scan['links'] : array();

		$this->include_view(
			'links-report',
			array(
				'data'          => $scan,
				'has_scan'      => $has_scan,
				'orphans'       => isset( $links['orphans'] ) ? $links['orphans'] : array(),
				'opportunities' => isset( $links['opportunities'] ) ? $links['opportunities'] : array(),
				'top_incoming'  => isset( $links['top_incoming'] ) ? $links['top_incoming'] : array(),
				'weak_anchors'  => isset( $links['weak_anchors'] ) ? $links['weak_anchors'] : array(),
			)
		);
	}

	/**
	 * Schema report.
	 */
	public function render_schema() {
		$this->guard();
		$scan     = $this->get_scan_data();
		$has_scan = $this->has_scan( $scan );
		$schema   = ( $has_scan && ! empty( $scan['schema'] ) && is_array( $scan['schema'] ) ) ? $scan['schema'] : array();

		$this->include_view(
			'schema-report',
			array(
				'data'    => $scan,
				'has_scan'=> $has_scan,
				'types'   => isset( $schema['types'] ) ? $schema['types'] : array(),
				'samples' => isset( $schema['samples'] ) ? $schema['samples'] : array(),
				'errors'  => isset( $schema['errors'] ) ? $schema['errors'] : array(),
			)
		);
	}

	/**
	 * Technical report (sitemaps, robots, meta conflicts).
	 */
	public function render_technical() {
		$this->guard();
		$scan       = $this->get_scan_data();
		$has_scan   = $this->has_scan( $scan );
		$technical  = ( $has_scan && ! empty( $scan['technical'] ) && is_array( $scan['technical'] ) ) ? $scan['technical'] : array();

		$this->include_view(
			'technical',
			array(
				'data'            => $scan,
				'has_scan'        => $has_scan,
				'sitemaps'        => isset( $technical['sitemaps'] ) ? $technical['sitemaps'] : array(),
				'robots'          => isset( $technical['robots'] ) ? $technical['robots'] : array(),
				'meta_conflicts'  => isset( $technical['meta_conflicts'] ) ? $technical['meta_conflicts'] : array(),
				'redirects'       => isset( $technical['redirects'] ) ? $technical['redirects'] : array(),
			)
		);
	}

	/**
	 * Export screen.
	 */
	public function render_export() {
		$this->guard();
		$scan        = $this->get_scan_data();
		$has_scan    = $this->has_scan( $scan );
		$last_export = get_option( self::LAST_EXPORT_KEY, array() );

		$this->include_view(
			'export',
			array(
				'data'        => $scan,
				'has_scan'    => $has_scan,
				'last_export' => is_array( $last_export ) ? $last_export : array(),
			)
		);
	}

	/**
	 * Settings screen.
	 */
	public function render_settings() {
		$this->guard();
		$updated = isset( $_GET['updated'] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		$this->include_view(
			'settings',
			array(
				'settings' => $this->get_settings(),
				'updated'  => $updated,
				'nonce'    => wp_create_nonce( self::NONCE ),
			)
		);
	}

	/**
	 * Include a view and extract $data into local variables.
	 *
	 * @param string $view View basename without .php.
	 * @param array  $data Variables for the view.
	 */
	protected function include_view( $view, $data = array() ) {
		$file = __DIR__ . '/views/' . $view . '.php';
		if ( ! is_readable( $file ) ) {
			echo '<div class="wrap"><p>' . esc_html__( 'View file is missing.', 'qpedia-seo-pro' ) . '</p></div>';
			return;
		}
		extract( $data, EXTR_SKIP ); // phpcs:ignore WordPress.PHP.DontExtract.extract_extract
		include $file;
	}

	/**
	 * Capability gate.
	 */
	private function guard() {
		if ( ! current_user_can( self::CAP ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'qpedia-seo-pro' ) );
		}
	}

	/* -----------------------------------------------------------------
	 * AJAX
	 * ----------------------------------------------------------------- */

	/**
	 * Start a scan: seed progress transient and return the step list.
	 */
	public function ajax_scan_start() {
		$this->assert_ajax();

		$counts = array(
			'articles'   => $this->published_count( 'quantum_article' ),
			'scientists' => $this->published_count( 'quantum_scientist' ),
			'pages'      => $this->published_count( 'page' ),
			'images'     => $this->count_images(),
			'terms'      => $this->count_terms( 'quantum_category' ),
			'links'      => $this->published_count( 'quantum_article' ) + $this->published_count( 'quantum_scientist' ) + $this->published_count( 'page' ),
			'technical'  => 1,
			'analyze'    => 1,
			'extras'     => 1,
		);

		$progress = array(
			'step'      => 'articles',
			'offset'    => 0,
			'total'     => array_sum( $counts ),
			'done'      => false,
			'counts'    => $counts,
			'started'   => time(),
		);

		set_transient( self::SCAN_PROGRESS, $progress, HOUR_IN_SECONDS );
		set_transient( self::SCAN_DATA_KEY, $this->empty_scan_payload(), DAY_IN_SECONDS );

		$steps = array();
		foreach ( $this->steps as $id ) {
			$steps[] = array(
				'id'    => $id,
				'label' => $this->step_label( $id ),
				'total' => isset( $counts[ $id ] ) ? (int) $counts[ $id ] : 0,
			);
		}

		wp_send_json_success(
			array(
				'steps'     => $steps,
				'batchSize' => $this->batch_size(),
				'message'   => __( 'Scan started.', 'qpedia-seo-pro' ),
			)
		);
	}

	/**
	 * Process one batch of the current scan step.
	 */
	public function ajax_scan_batch() {
		$this->assert_ajax();

		$progress = get_transient( self::SCAN_PROGRESS );
		if ( ! is_array( $progress ) || empty( $progress['step'] ) ) {
			wp_send_json_error( array( 'message' => __( 'No scan in progress. Start a scan first.', 'qpedia-seo-pro' ) ) );
		}

		$scan = get_transient( self::SCAN_DATA_KEY );
		if ( ! is_array( $scan ) ) {
			$scan = $this->empty_scan_payload();
		}

		$step     = $progress['step'];
		$offset   = isset( $progress['offset'] ) ? (int) $progress['offset'] : 0;
		$batch    = $this->batch_size();
		$counts   = isset( $progress['counts'] ) && is_array( $progress['counts'] ) ? $progress['counts'] : array();
		$step_tot = isset( $counts[ $step ] ) ? (int) $counts[ $step ] : 0;

		$processed = 0;
		$message   = $this->step_label( $step );

		switch ( $step ) {
			case 'articles':
				$result            = $this->batch_articles( $offset, $batch );
				$scan['articles']  = array_merge( isset( $scan['articles'] ) ? $scan['articles'] : array(), $result['items'] );
				$processed         = $result['count'];
				$message           = sprintf(
					/* translators: 1: processed count, 2: total */
					__( 'Scanning articles (%1$d / %2$d)', 'qpedia-seo-pro' ),
					min( $offset + $processed, $step_tot ),
					$step_tot
				);
				break;

			case 'scientists':
				$result              = $this->batch_scientists( $offset, $batch );
				$scan['scientists']  = array_merge( isset( $scan['scientists'] ) ? $scan['scientists'] : array(), $result['items'] );
				$processed           = $result['count'];
				$message             = sprintf(
					__( 'Scanning scientists (%1$d / %2$d)', 'qpedia-seo-pro' ),
					min( $offset + $processed, $step_tot ),
					$step_tot
				);
				break;

			case 'pages':
				$result         = $this->batch_pages( $offset, $batch );
				$scan['pages']  = array_merge( isset( $scan['pages'] ) ? $scan['pages'] : array(), $result['items'] );
				$processed      = $result['count'];
				$message        = sprintf(
					__( 'Scanning pages (%1$d / %2$d)', 'qpedia-seo-pro' ),
					min( $offset + $processed, $step_tot ),
					$step_tot
				);
				break;

			case 'images':
				$result          = $this->batch_images( $offset, $batch );
				$scan['images']  = array_merge( isset( $scan['images'] ) ? $scan['images'] : array(), $result['items'] );
				$processed       = $result['count'];
				$message         = sprintf(
					__( 'Scanning images (%1$d / %2$d)', 'qpedia-seo-pro' ),
					min( $offset + $processed, $step_tot ),
					$step_tot
				);
				break;

			case 'terms':
				$result         = $this->batch_terms( $offset, $batch );
				$scan['terms']  = array_merge( isset( $scan['terms'] ) ? $scan['terms'] : array(), $result['items'] );
				$processed      = $result['count'];
				$message        = sprintf(
					__( 'Scanning categories (%1$d / %2$d)', 'qpedia-seo-pro' ),
					min( $offset + $processed, $step_tot ),
					$step_tot
				);
				break;

			case 'links':
				$result             = $this->batch_links( $offset, $batch );
				$scan['raw_links']  = array_merge( isset( $scan['raw_links'] ) ? $scan['raw_links'] : array(), $result['items'] );
				$processed          = $result['count'];
				$message            = sprintf(
					__( 'Mapping internal links (%1$d / %2$d)', 'qpedia-seo-pro' ),
					min( $offset + $processed, $step_tot ),
					$step_tot
				);
				break;

			case 'technical':
				$scan['technical'] = $this->collect_technical();
				$processed         = 1;
				$message           = __( 'Auditing sitemaps, robots.txt, and meta conflicts…', 'qpedia-seo-pro' );
				break;

			case 'analyze':
				$scan      = $this->finalize_analyze( $scan );
				$processed = 1;
				$message   = __( 'Calculating site score and grade distribution…', 'qpedia-seo-pro' );
				break;

			case 'extras':
				$scan      = $this->run_extras( $scan );
				$processed = 1;
				$message   = __( 'Scientist completeness, taxonomy, content, image, and meta audits…', 'qpedia-seo-pro' );
				break;

			default:
				wp_send_json_error( array( 'message' => __( 'Unknown scan step.', 'qpedia-seo-pro' ) ) );
		}

		$new_offset = $offset + $processed;
		$step_done  = ( $step_tot <= 0 ) || ( $processed <= 0 ) || ( $new_offset >= $step_tot );

		if ( $step_done ) {
			if ( 'links' === $step ) {
				$scan['links'] = $this->finalize_links( $scan );
			}

			$next = $this->next_step( $step );
			if ( null === $next ) {
				$scan = $this->finalize_scan( $scan );
				$this->persist_scan_to_core( $scan );
				$this->push_history( self::site_score_int( $scan ) );
				delete_transient( self::SCAN_PROGRESS );

				wp_send_json_success(
					array(
						'percent' => 100,
						'step'    => $step,
						'message' => __( 'Scan complete.', 'qpedia-seo-pro' ),
						'done'    => true,
					)
				);
			}

			$progress['step']   = $next;
			$progress['offset'] = 0;
		} else {
			$progress['offset'] = $new_offset;
		}

		$progress['done'] = false;
		set_transient( self::SCAN_DATA_KEY, $scan, DAY_IN_SECONDS );
		set_transient( self::SCAN_PROGRESS, $progress, HOUR_IN_SECONDS );

		wp_send_json_success(
			array(
				'percent' => $this->compute_percent( $progress, $counts ),
				'step'    => $progress['step'],
				'message' => $message,
				'done'    => false,
			)
		);
	}

	/**
	 * Return current scan progress.
	 */
	public function ajax_scan_status() {
		$this->assert_ajax();
		$progress = get_transient( self::SCAN_PROGRESS );
		if ( ! is_array( $progress ) ) {
			$scan = $this->get_scan_data();
			wp_send_json_success(
				array(
					'done'    => true,
					'percent' => $this->has_scan( $scan ) ? 100 : 0,
					'step'    => '',
					'message' => $this->has_scan( $scan ) ? __( 'Scan complete.', 'qpedia-seo-pro' ) : __( 'No scan in progress.', 'qpedia-seo-pro' ),
				)
			);
		}

		$counts = isset( $progress['counts'] ) ? $progress['counts'] : array();
		wp_send_json_success(
			array(
				'done'    => ! empty( $progress['done'] ),
				'percent' => $this->compute_percent( $progress, $counts ),
				'step'    => isset( $progress['step'] ) ? $progress['step'] : '',
				'message' => $this->step_label( isset( $progress['step'] ) ? $progress['step'] : '' ),
				'offset'  => isset( $progress['offset'] ) ? (int) $progress['offset'] : 0,
			)
		);
	}

	/**
	 * Reanalyze a single article or scientist.
	 */
	public function ajax_reanalyze_post() {
		$this->assert_ajax();

		$post_id = isset( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0;
		$post    = $post_id ? get_post( $post_id ) : null;
		if ( ! $post || ! in_array( $post->post_type, array( 'quantum_article', 'quantum_scientist', 'page' ), true ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid post.', 'qpedia-seo-pro' ) ) );
		}

		if ( 'quantum_scientist' === $post->post_type ) {
			$result = $this->call_module( 'Analyzer', 'analyze_scientist', array( $post_id ) );
			if ( ! is_array( $result ) ) {
				$result = $this->fallback_analyze_scientist( $post );
			}
		} else {
			$result = $this->call_module( 'Analyzer', 'analyze_article', array( $post_id ) );
			if ( ! is_array( $result ) ) {
				$result = $this->fallback_analyze_article( $post );
			}
		}

		$score = isset( $result['score'] ) ? (int) $result['score'] : 0;
		$grade = isset( $result['grade'] ) ? (string) $result['grade'] : $this->grade( $score );
		$result['score'] = $score;
		$result['grade'] = $grade;

		update_post_meta( $post_id, self::ANALYSIS_META, $result );

		$scan = $this->get_scan_data();
		$key  = 'quantum_scientist' === $post->post_type ? 'scientists' : ( 'page' === $post->post_type ? 'pages' : 'articles' );
		if ( ! empty( $scan[ $key ] ) && is_array( $scan[ $key ] ) ) {
			foreach ( $scan[ $key ] as $i => $row ) {
				if ( isset( $row['id'] ) && (int) $row['id'] === $post_id ) {
					$scan[ $key ][ $i ]['score']        = $score;
					$scan[ $key ][ $i ]['grade']        = $grade;
					$scan[ $key ][ $i ]['issues']       = isset( $result['issues'] ) ? $result['issues'] : array();
					$scan[ $key ][ $i ]['issues_count'] = is_array( $scan[ $key ][ $i ]['issues'] ) ? count( $scan[ $key ][ $i ]['issues'] ) : 0;
					$scan[ $key ][ $i ]['suggestions']  = isset( $result['suggestions'] ) ? $result['suggestions'] : array();
					break;
				}
			}
			set_transient( self::SCAN_DATA_KEY, $scan, DAY_IN_SECONDS );
			$this->persist_scan_to_core( $scan );
		}

		wp_send_json_success(
			array(
				'score'       => $score,
				'grade'       => $grade,
				'issues'      => isset( $result['issues'] ) ? $result['issues'] : array(),
				'suggestions' => isset( $result['suggestions'] ) ? $result['suggestions'] : array(),
				'details'     => isset( $result['details'] ) ? $result['details'] : array(),
			)
		);
	}

	/**
	 * Build a full ZIP report via Export.
	 */
	public function ajax_export_report() {
		$this->assert_ajax();

		$result = $this->call_module( 'Export', 'export_full_report' );
		$url    = '';
		$path   = '';

		if ( is_array( $result ) ) {
			$url  = isset( $result['url'] ) ? (string) $result['url'] : '';
			$path = isset( $result['path'] ) ? (string) $result['path'] : '';
		} elseif ( is_string( $result ) && '' !== $result ) {
			if ( 0 === strpos( $result, 'http://' ) || 0 === strpos( $result, 'https://' ) ) {
				$url = $result;
			} else {
				$path   = $result;
				$upload = wp_upload_dir();
				if ( ! empty( $upload['basedir'] ) && 0 === strpos( $path, $upload['basedir'] ) ) {
					$url = str_replace( $upload['basedir'], $upload['baseurl'], $path );
				}
			}
		}

		if ( '' === $url ) {
			wp_send_json_error( array( 'message' => __( 'Export module did not return a file URL.', 'qpedia-seo-pro' ) ) );
		}

		$record = array(
			'url'  => $url,
			'path' => $path,
			'date' => current_time( 'mysql' ),
		);
		update_option( self::LAST_EXPORT_KEY, $record, false );

		wp_send_json_success(
			array(
				'url'     => esc_url_raw( $url ),
				'message' => __( 'Report is ready.', 'qpedia-seo-pro' ),
			)
		);
	}

	/**
	 * Save whitelisted settings over AJAX.
	 */
	public function ajax_save_settings() {
		$this->assert_ajax();
		$saved = $this->persist_settings( wp_unslash( $_POST ) ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		wp_send_json_success(
			array(
				'settings' => $saved,
				'message'  => __( 'Settings saved.', 'qpedia-seo-pro' ),
			)
		);
	}

	/**
	 * Delete scan transients.
	 */
	public function ajax_clear_cache() {
		$this->assert_ajax();
		delete_transient( self::SCAN_DATA_KEY );
		delete_transient( self::SCAN_PROGRESS );
		wp_send_json_success( array( 'message' => __( 'Cache cleared.', 'qpedia-seo-pro' ) ) );
	}

	/**
	 * Verify nonce + capability for AJAX.
	 */
	private function assert_ajax() {
		if ( ! check_ajax_referer( self::NONCE, 'nonce', false ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid security token.', 'qpedia-seo-pro' ) ), 403 );
		}
		if ( ! current_user_can( self::CAP ) ) {
			wp_send_json_error( array( 'message' => __( 'You do not have permission to do this.', 'qpedia-seo-pro' ) ), 403 );
		}
	}

	/* -----------------------------------------------------------------
	 * Scan data helpers
	 * ----------------------------------------------------------------- */

	/**
	 * Load scan payload from Core or the accumulating transient.
	 *
	 * @return array
	 */
	public function get_scan_data() {
		$data = $this->call_module( 'Core', 'get_scan_data' );
		if ( is_array( $data ) && ! empty( $data ) ) {
			return $data;
		}
		$data = get_transient( self::SCAN_DATA_KEY );
		return is_array( $data ) ? $data : array();
	}

	/**
	 * Whether a completed scan payload exists.
	 *
	 * @param array $scan Scan payload.
	 * @return bool
	 */
	public function has_scan( $scan ) {
		if ( ! is_array( $scan ) || empty( $scan ) ) {
			return false;
		}
		return ! empty( $scan['last_scan'] ) || ! empty( $scan['collected_at'] ) || ! empty( $scan['summary']['analyzed_at'] ) || ! empty( $scan['articles'] ) || ! empty( $scan['scientists'] );
	}

	/**
	 * Letter grade for a 0–100 score.
	 *
	 * @param int $score Score.
	 * @return string
	 */
	public function grade( $score ) {
		$from_core = $this->call_module( 'Core', 'grade', array( (int) $score ) );
		if ( is_string( $from_core ) && '' !== $from_core ) {
			return strtoupper( $from_core );
		}
		$score = (int) $score;
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
	 * HTML grade badge.
	 *
	 * @param string $grade Letter.
	 * @return string
	 */
	public static function grade_badge( $grade ) {
		$grade = strtoupper( preg_replace( '/[^A-F]/', '', (string) $grade ) );
		if ( '' === $grade ) {
			$grade = 'F';
		}
		return '<span class="qpedia-grade qpedia-grade-' . esc_attr( strtolower( $grade ) ) . '">' . esc_html( $grade ) . '</span>';
	}

	/**
	 * Normalize an issue to a message string.
	 *
	 * @param mixed $issue Issue row or string.
	 * @return string
	 */
	public static function issue_message( $issue ) {
		if ( is_array( $issue ) ) {
			if ( isset( $issue['message'] ) ) {
				return (string) $issue['message'];
			}
			if ( isset( $issue['text'] ) ) {
				return (string) $issue['text'];
			}
			return '';
		}
		return (string) $issue;
	}

	/**
	 * Issue severity.
	 *
	 * @param mixed $issue Issue.
	 * @return string
	 */
	public static function issue_severity( $issue ) {
		if ( is_array( $issue ) && ! empty( $issue['severity'] ) ) {
			return sanitize_key( $issue['severity'] );
		}
		return 'medium';
	}

	/**
	 * Empty accumulating payload.
	 *
	 * @return array
	 */
	private function empty_scan_payload() {
		return array(
			'articles'               => array(),
			'scientists'             => array(),
			'pages'                  => array(),
			'images'                 => array(),
			'terms'                  => array(),
			'tags'                   => array(
				'total'      => 0,
				'unused'     => 0,
				'duplicates' => array(),
			),
			'raw_links'              => array(),
			'links'                  => array(
				'orphans'       => array(),
				'opportunities' => array(),
				'top_incoming'  => array(),
				'weak_anchors'  => array(),
			),
			'technical'              => array(),
			'schema'                 => array(
				'types'   => array(),
				'samples' => array(),
				'errors'  => array(),
			),
			'site_score'             => 0,
			'grade'                  => 'F',
			'counts'                 => array(),
			'issue_tallies'          => array(),
			'score_distribution'     => array(),
			'recent_issues'          => array(),
			'scientist_completeness' => array(),
			'content_audit'          => array(),
			'image_audit'            => array(),
			'last_scan'              => '',
			'history'                => array(),
		);
	}

	/**
	 * Next step id or null when finished.
	 *
	 * @param string $current Current step.
	 * @return string|null
	 */
	private function next_step( $current ) {
		$i = array_search( $current, $this->steps, true );
		if ( false === $i ) {
			return null;
		}
		$i++;
		return isset( $this->steps[ $i ] ) ? $this->steps[ $i ] : null;
	}

	/**
	 * Human label for a step.
	 *
	 * @param string $id Step id.
	 * @return string
	 */
	private function step_label( $id ) {
		$map = array(
			'articles'   => __( 'Articles', 'qpedia-seo-pro' ),
			'scientists' => __( 'Scientists', 'qpedia-seo-pro' ),
			'pages'      => __( 'Pages', 'qpedia-seo-pro' ),
			'images'     => __( 'Images', 'qpedia-seo-pro' ),
			'terms'      => __( 'Categories', 'qpedia-seo-pro' ),
			'links'      => __( 'Internal links', 'qpedia-seo-pro' ),
			'technical'  => __( 'Technical', 'qpedia-seo-pro' ),
			'analyze'    => __( 'Scoring', 'qpedia-seo-pro' ),
			'extras'     => __( 'Extra audits', 'qpedia-seo-pro' ),
		);
		return isset( $map[ $id ] ) ? $map[ $id ] : $id;
	}

	/**
	 * Overall percent from progress pointer.
	 *
	 * @param array $progress Progress transient.
	 * @param array $counts   Per-step totals.
	 * @return int
	 */
	private function compute_percent( $progress, $counts ) {
		$total = 0;
		$done  = 0;
		$step  = isset( $progress['step'] ) ? $progress['step'] : '';
		$off   = isset( $progress['offset'] ) ? (int) $progress['offset'] : 0;
		$seen  = true;
		foreach ( $this->steps as $id ) {
			$n      = isset( $counts[ $id ] ) ? (int) $counts[ $id ] : 0;
			$total += max( 1, $n );
			if ( $id === $step ) {
				$done += min( $off, max( 1, $n ) );
				$seen  = false;
			} elseif ( $seen ) {
				$done += max( 1, $n );
			}
		}
		if ( $total <= 0 ) {
			return 0;
		}
		return (int) min( 99, floor( 100 * $done / $total ) );
	}

	/**
	 * Batch size from settings, default 50.
	 *
	 * @return int
	 */
	private function batch_size() {
		$s = $this->get_settings();
		$n = isset( $s['batch_size'] ) ? (int) $s['batch_size'] : 50;
		return max( 1, min( 100, $n ) );
	}

	/**
	 * Published count for a post type.
	 *
	 * @param string $type Post type.
	 * @return int
	 */
	private function published_count( $type ) {
		$c = wp_count_posts( $type );
		if ( ! is_object( $c ) ) {
			return 0;
		}
		return isset( $c->publish ) ? (int) $c->publish : 0;
	}

	/**
	 * Count image attachments.
	 *
	 * @return int
	 */
	private function count_images() {
		global $wpdb;
		$sql = $wpdb->prepare(
			"SELECT COUNT(ID) FROM {$wpdb->posts} WHERE post_type = %s AND post_status = %s AND post_mime_type LIKE %s",
			'attachment',
			'inherit',
			$wpdb->esc_like( 'image/' ) . '%'
		);
		return (int) $wpdb->get_var( $sql ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
	}

	/**
	 * Count terms in a taxonomy.
	 *
	 * @param string $taxonomy Taxonomy.
	 * @return int
	 */
	private function count_terms( $taxonomy ) {
		if ( ! taxonomy_exists( $taxonomy ) ) {
			return 0;
		}
		$n = wp_count_terms(
			array(
				'taxonomy'   => $taxonomy,
				'hide_empty' => false,
			)
		);
		return is_wp_error( $n ) ? 0 : (int) $n;
	}

	/* -----------------------------------------------------------------
	 * Batch collectors
	 * ----------------------------------------------------------------- */

	/**
	 * Collect + analyze a page of articles.
	 *
	 * @param int $offset Offset.
	 * @param int $limit  Limit.
	 * @return array{items:array,count:int}
	 */
	private function batch_articles( $offset, $limit ) {
		$collected = $this->call_module( 'Collector', 'collect_articles_batch', array( (int) $offset, (int) $limit ) );
		if ( is_array( $collected ) && $this->is_list( $collected ) ) {
			$items = array();
			foreach ( $collected as $item ) {
				if ( ! is_array( $item ) ) {
					continue;
				}
				$analysis = $this->call_module( 'Analyzer', 'analyze_article', array( $item ) );
				if ( is_array( $analysis ) ) {
					$item['analysis'] = $analysis;
					$id = isset( $item['ID'] ) ? (int) $item['ID'] : ( isset( $item['id'] ) ? (int) $item['id'] : 0 );
					if ( $id ) {
						update_post_meta( $id, self::ANALYSIS_META, $analysis );
					}
				}
				$items[] = $item;
			}
			return array(
				'items' => $items,
				'count' => count( $items ),
			);
		}
		if ( is_array( $collected ) && isset( $collected['items'] ) ) {
			return array(
				'items' => $collected['items'],
				'count' => count( $collected['items'] ),
			);
		}

		$query = new \WP_Query(
			array(
				'post_type'              => 'quantum_article',
				'post_status'            => 'publish',
				'posts_per_page'         => $limit,
				'offset'                 => $offset,
				'orderby'                => 'ID',
				'order'                  => 'ASC',
				'no_found_rows'          => true,
				'suppress_filters'       => true,
				'update_post_meta_cache' => true,
				'update_post_term_cache' => true,
			)
		);

		$items = array();
		foreach ( $query->posts as $post ) {
			$row = $this->article_row( $post );
			$items[] = $row;
			if ( isset( $row['analysis'] ) ) {
				update_post_meta( $post->ID, self::ANALYSIS_META, $row['analysis'] );
			}
		}
		wp_reset_postdata();

		return array(
			'items' => $items,
			'count' => count( $items ),
		);
	}

	/**
	 * Build one article report row.
	 *
	 * @param \WP_Post $post Post.
	 * @return array
	 */
	private function article_row( $post ) {
		$from_c = $this->call_module( 'Collector', 'collect_article', array( $post->ID ) );
		if ( is_array( $from_c ) && isset( $from_c['id'] ) ) {
			if ( empty( $from_c['score'] ) ) {
				$analysis = $this->call_module( 'Analyzer', 'analyze_article', array( $post->ID ) );
				if ( is_array( $analysis ) ) {
					$from_c['analysis']     = $analysis;
					$from_c['score']        = isset( $analysis['score'] ) ? (int) $analysis['score'] : 0;
					$from_c['grade']        = isset( $analysis['grade'] ) ? $analysis['grade'] : $this->grade( $from_c['score'] );
					$from_c['issues']       = isset( $analysis['issues'] ) ? $analysis['issues'] : array();
					$from_c['suggestions']  = isset( $analysis['suggestions'] ) ? $analysis['suggestions'] : array();
					$from_c['issues_count'] = is_array( $from_c['issues'] ) ? count( $from_c['issues'] ) : 0;
				}
			}
			return $from_c;
		}

		$analysis = $this->call_module( 'Analyzer', 'analyze_article', array( $post->ID ) );
		if ( ! is_array( $analysis ) ) {
			$analysis = $this->fallback_analyze_article( $post );
		}

		$keyword = (string) get_post_meta( $post->ID, 'rank_math_focus_keyword', true );
		$terms   = taxonomy_exists( 'quantum_category' ) ? wp_get_post_terms( $post->ID, 'quantum_category' ) : array();
		$cat     = '';
		$cat_id  = 0;
		if ( ! is_wp_error( $terms ) && ! empty( $terms ) ) {
			$cat    = $terms[0]->name;
			$cat_id = (int) $terms[0]->term_id;
		}

		$score  = isset( $analysis['score'] ) ? (int) $analysis['score'] : 0;
		$issues = isset( $analysis['issues'] ) ? (array) $analysis['issues'] : array();

		return array(
			'id'           => (int) $post->ID,
			'title'        => get_the_title( $post ),
			'slug'         => $post->post_name,
			'score'        => $score,
			'grade'        => isset( $analysis['grade'] ) ? (string) $analysis['grade'] : $this->grade( $score ),
			'words'        => $this->word_count( $post->post_content ),
			'keyword'      => $keyword,
			'category'     => $cat,
			'category_id'  => $cat_id,
			'modified'     => $post->post_modified,
			'issues'       => $issues,
			'issues_count' => count( $issues ),
			'suggestions'  => isset( $analysis['suggestions'] ) ? $analysis['suggestions'] : array(),
			'edit_link'    => get_edit_post_link( $post->ID, 'raw' ),
			'permalink'    => get_permalink( $post ),
			'analysis'     => $analysis,
		);
	}

	/**
	 * Collect + analyze scientists.
	 *
	 * @param int $offset Offset.
	 * @param int $limit  Limit.
	 * @return array{items:array,count:int}
	 */
	private function batch_scientists( $offset, $limit ) {
		$collected = $this->call_module( 'Collector', 'collect_scientists_batch', array( (int) $offset, (int) $limit ) );
		if ( is_array( $collected ) && $this->is_list( $collected ) ) {
			$items = array();
			foreach ( $collected as $item ) {
				if ( ! is_array( $item ) ) {
					continue;
				}
				$analysis = $this->call_module( 'Analyzer', 'analyze_scientist', array( $item ) );
				if ( is_array( $analysis ) ) {
					$item['analysis'] = $analysis;
					$id = isset( $item['ID'] ) ? (int) $item['ID'] : 0;
					if ( $id ) {
						update_post_meta( $id, self::ANALYSIS_META, $analysis );
					}
				}
				$items[] = $item;
			}
			return array(
				'items' => $items,
				'count' => count( $items ),
			);
		}

		$query = new \WP_Query(
			array(
				'post_type'              => 'quantum_scientist',
				'post_status'            => 'publish',
				'posts_per_page'         => $limit,
				'offset'                 => $offset,
				'orderby'                => 'ID',
				'order'                  => 'ASC',
				'no_found_rows'          => true,
				'suppress_filters'       => true,
				'update_post_meta_cache' => true,
			)
		);

		$items = array();
		foreach ( $query->posts as $post ) {
			$from_c = $this->call_module( 'Collector', 'collect_scientist', array( $post->ID ) );
			if ( is_array( $from_c ) && isset( $from_c['id'] ) ) {
				$items[] = $from_c;
				continue;
			}

			$analysis = $this->call_module( 'Analyzer', 'analyze_scientist', array( $post->ID ) );
			if ( ! is_array( $analysis ) ) {
				$analysis = $this->fallback_analyze_scientist( $post );
			}

			$missing = $this->scientist_missing_fields( $post->ID );
			$total_f = 14;
			$have    = $total_f - count( $missing );
			$score   = isset( $analysis['score'] ) ? (int) $analysis['score'] : 0;

			$row = array(
				'id'             => (int) $post->ID,
				'name'           => get_the_title( $post ),
				'en_name'        => (string) get_post_meta( $post->ID, '_scientist_en_name', true ),
				'score'          => $score,
				'grade'          => isset( $analysis['grade'] ) ? (string) $analysis['grade'] : $this->grade( $score ),
				'completeness'   => (int) round( 100 * $have / $total_f ),
				'missing_fields' => $missing,
				'words'          => $this->word_count( $post->post_content ),
				'issues'         => isset( $analysis['issues'] ) ? $analysis['issues'] : array(),
				'issues_count'   => isset( $analysis['issues'] ) && is_array( $analysis['issues'] ) ? count( $analysis['issues'] ) : 0,
				'suggestions'    => isset( $analysis['suggestions'] ) ? $analysis['suggestions'] : array(),
				'edit_link'      => get_edit_post_link( $post->ID, 'raw' ),
				'permalink'      => get_permalink( $post ),
				'analysis'       => $analysis,
			);
			update_post_meta( $post->ID, self::ANALYSIS_META, $analysis );
			$items[] = $row;
		}
		wp_reset_postdata();

		return array(
			'items' => $items,
			'count' => count( $items ),
		);
	}

	/**
	 * Collect pages.
	 *
	 * @param int $offset Offset.
	 * @param int $limit  Limit.
	 * @return array{items:array,count:int}
	 */
	private function batch_pages( $offset, $limit ) {
		$query = new \WP_Query(
			array(
				'post_type'        => 'page',
				'post_status'      => 'publish',
				'posts_per_page'   => $limit,
				'offset'           => $offset,
				'orderby'          => 'ID',
				'order'            => 'ASC',
				'no_found_rows'    => true,
				'suppress_filters' => true,
			)
		);

		$items = array();
		foreach ( $query->posts as $post ) {
			$analysis = $this->call_module( 'Analyzer', 'analyze_page', array( $post->ID ) );
			if ( ! is_array( $analysis ) ) {
				$analysis = $this->fallback_analyze_article( $post );
			}
			$score   = isset( $analysis['score'] ) ? (int) $analysis['score'] : 0;
			$items[] = array(
				'id'           => (int) $post->ID,
				'title'        => get_the_title( $post ),
				'slug'         => $post->post_name,
				'score'        => $score,
				'grade'        => isset( $analysis['grade'] ) ? (string) $analysis['grade'] : $this->grade( $score ),
				'words'        => $this->word_count( $post->post_content ),
				'modified'     => $post->post_modified,
				'issues'       => isset( $analysis['issues'] ) ? $analysis['issues'] : array(),
				'issues_count' => isset( $analysis['issues'] ) && is_array( $analysis['issues'] ) ? count( $analysis['issues'] ) : 0,
				'edit_link'    => get_edit_post_link( $post->ID, 'raw' ),
				'permalink'    => get_permalink( $post ),
			);
		}
		wp_reset_postdata();

		return array(
			'items' => $items,
			'count' => count( $items ),
		);
	}

	/**
	 * Collect images.
	 *
	 * @param int $offset Offset.
	 * @param int $limit  Limit.
	 * @return array{items:array,count:int}
	 */
	private function batch_images( $offset, $limit ) {
		$query = new \WP_Query(
			array(
				'post_type'              => 'attachment',
				'post_status'            => 'inherit',
				'post_mime_type'         => 'image',
				'posts_per_page'         => $limit,
				'offset'                 => $offset,
				'orderby'                => 'ID',
				'order'                  => 'ASC',
				'no_found_rows'          => true,
				'suppress_filters'       => true,
				'update_post_meta_cache' => true,
			)
		);

		$items = array();
		foreach ( $query->posts as $post ) {
			$from_c = $this->call_module( 'Collector', 'collect_image', array( $post->ID ) );
			if ( is_array( $from_c ) && isset( $from_c['id'] ) ) {
				$items[] = $from_c;
				continue;
			}

			$alt      = (string) get_post_meta( $post->ID, '_wp_attachment_image_alt', true );
			$meta     = wp_get_attachment_metadata( $post->ID );
			$file     = get_attached_file( $post->ID );
			$size_kb  = ( $file && file_exists( $file ) ) ? (int) round( filesize( $file ) / 1024 ) : 0;
			$width    = isset( $meta['width'] ) ? (int) $meta['width'] : 0;
			$height   = isset( $meta['height'] ) ? (int) $meta['height'] : 0;
			$parent   = (int) $post->post_parent;
			$featured = false;
			$parent_t = '';
			if ( $parent ) {
				$parent_t = get_the_title( $parent );
				$thumb    = (int) get_post_meta( $parent, '_thumbnail_id', true );
				$featured = $thumb === (int) $post->ID;
			}
			$thumb_src = wp_get_attachment_image_url( $post->ID, 'thumbnail' );

			$items[] = array(
				'id'          => (int) $post->ID,
				'thumb'       => $thumb_src ? $thumb_src : '',
				'file'        => $post->post_title ? $post->post_title : basename( (string) $file ),
				'alt'         => $alt,
				'size_kb'     => $size_kb,
				'width'       => $width,
				'height'      => $height,
				'parent'      => $parent_t,
				'parent_id'   => $parent,
				'featured'    => $featured,
				'missing_alt' => ( '' === trim( $alt ) ),
				'edit_link'   => get_edit_post_link( $post->ID, 'raw' ),
				'url'         => wp_get_attachment_url( $post->ID ),
			);
		}
		wp_reset_postdata();

		return array(
			'items' => $items,
			'count' => count( $items ),
		);
	}

	/**
	 * Collect quantum_category terms.
	 *
	 * @param int $offset Offset.
	 * @param int $limit  Limit.
	 * @return array{items:array,count:int}
	 */
	private function batch_terms( $offset, $limit ) {
		if ( ! taxonomy_exists( 'quantum_category' ) ) {
			return array(
				'items' => array(),
				'count' => 0,
			);
		}

		$terms = get_terms(
			array(
				'taxonomy'   => 'quantum_category',
				'hide_empty' => false,
				'number'     => $limit,
				'offset'     => $offset,
				'orderby'    => 'id',
				'order'      => 'ASC',
			)
		);

		if ( is_wp_error( $terms ) ) {
			return array(
				'items' => array(),
				'count' => 0,
			);
		}

		$items = array();
		foreach ( $terms as $term ) {
			$analysis = $this->call_module( 'Analyzer', 'analyze_term', array( $term->term_id, 'quantum_category' ) );
			$desc_len = $this->word_count( $term->description );
			$latin    = (bool) preg_match( '/^[a-z0-9\-]+$/', $term->slug );
			$issues   = array();
			if ( is_array( $analysis ) && isset( $analysis['issues'] ) ) {
				$issues = (array) $analysis['issues'];
			} else {
				if ( $desc_len < 100 ) {
					$issues[] = array(
						'severity' => 'high',
						'message'  => __( 'Archive description is shorter than 100 words.', 'qpedia-seo-pro' ),
					);
				}
				if ( (int) $term->count < 3 ) {
					$issues[] = array(
						'severity' => 'medium',
						'message'  => __( 'Fewer than 3 assigned articles.', 'qpedia-seo-pro' ),
					);
				}
				if ( ! $latin ) {
					$issues[] = array(
						'severity' => 'medium',
						'message'  => __( 'Slug is not Latin kebab-case.', 'qpedia-seo-pro' ),
					);
				}
			}

			$items[] = array(
				'id'                  => (int) $term->term_id,
				'name'                => $term->name,
				'slug'                => $term->slug,
				'count'               => (int) $term->count,
				'description_length'  => $desc_len,
				'is_latin'            => $latin,
				'issues'              => $issues,
				'issues_count'        => count( $issues ),
				'parent'              => (int) $term->parent,
			);
		}

		return array(
			'items' => $items,
			'count' => count( $items ),
		);
	}

	/**
	 * Extract internal links from a page of posts.
	 *
	 * @param int $offset Offset.
	 * @param int $limit  Limit.
	 * @return array{items:array,count:int}
	 */
	private function batch_links( $offset, $limit ) {
		$from_mod = $this->call_module( 'Internal_Links', 'scan_batch', array( $offset, $limit ) );
		if ( is_array( $from_mod ) && isset( $from_mod['items'] ) ) {
			return array(
				'items' => $from_mod['items'],
				'count' => count( $from_mod['items'] ),
			);
		}

		$query = new \WP_Query(
			array(
				'post_type'        => array( 'quantum_article', 'quantum_scientist', 'page' ),
				'post_status'      => 'publish',
				'posts_per_page'   => $limit,
				'offset'           => $offset,
				'orderby'          => 'ID',
				'order'            => 'ASC',
				'no_found_rows'    => true,
				'suppress_filters' => true,
			)
		);

		$home  = wp_parse_url( home_url(), PHP_URL_HOST );
		$items = array();

		foreach ( $query->posts as $post ) {
			if ( ! preg_match_all( '/<a\s[^>]*href=["\']([^"\']+)["\'][^>]*>(.*?)<\/a>/is', $post->post_content, $m, PREG_SET_ORDER ) ) {
				continue;
			}
			foreach ( $m as $match ) {
				$href = $match[1];
				$host = wp_parse_url( $href, PHP_URL_HOST );
				if ( $host && $home && strtolower( $host ) !== strtolower( $home ) ) {
					continue;
				}
				if ( $host || 0 === strpos( $href, '/' ) ) {
					$anchor = trim( wp_strip_all_tags( $match[2] ) );
					$items[] = array(
						'from_id'     => (int) $post->ID,
						'from_title'  => get_the_title( $post ),
						'from_type'   => $post->post_type,
						'href'        => $href,
						'anchor_text' => $anchor,
					);
				}
			}
		}
		wp_reset_postdata();

		return array(
			'items' => $items,
			'count' => count( $query->posts ),
		);
	}

	/**
	 * Build orphans / opportunities / incoming / weak anchors from raw links.
	 *
	 * @param array $scan Scan payload.
	 * @return array
	 */
	private function finalize_links( $scan ) {
		$mod = $this->call_module( 'Internal_Links', 'scan_all_internal_links' );
		if ( is_array( $mod ) && ( isset( $mod['orphans'] ) || isset( $mod['opportunities'] ) ) ) {
			return array(
				'orphans'       => isset( $mod['orphans'] ) ? $mod['orphans'] : array(),
				'opportunities' => isset( $mod['opportunities'] ) ? $mod['opportunities'] : array(),
				'top_incoming'  => isset( $mod['top_incoming'] ) ? $mod['top_incoming'] : array(),
				'weak_anchors'  => isset( $mod['weak_anchors'] ) ? $mod['weak_anchors'] : array(),
			);
		}

		$orphans = $this->call_module( 'Internal_Links', 'find_orphan_pages' );
		$opps    = $this->call_module( 'Internal_Links', 'find_link_opportunities' );
		$anchors = $this->call_module( 'Internal_Links', 'analyze_anchor_texts' );

		$raw     = isset( $scan['raw_links'] ) && is_array( $scan['raw_links'] ) ? $scan['raw_links'] : array();
		$posts   = array();
		foreach ( array( 'articles', 'scientists', 'pages' ) as $bucket ) {
			if ( empty( $scan[ $bucket ] ) || ! is_array( $scan[ $bucket ] ) ) {
				continue;
			}
			foreach ( $scan[ $bucket ] as $row ) {
				if ( empty( $row['id'] ) ) {
					continue;
				}
				$posts[ (int) $row['id'] ] = $row;
			}
		}

		$incoming = array();
		$weak     = array();
		$pointed  = array();
		$weak_rx  = '/^(اینجا|اینجا Totalیک کنید|Totalیک کنید|click here|here|read more|بیشتر بخوانید|لینک)$/iu';

		foreach ( $raw as $link ) {
			$href = isset( $link['href'] ) ? $link['href'] : '';
			$to   = url_to_postid( $href );
			if ( $to ) {
				$pointed[ $to ] = true;
				if ( ! isset( $incoming[ $to ] ) ) {
					$incoming[ $to ] = 0;
				}
				$incoming[ $to ]++;
			}
			$anchor = isset( $link['anchor_text'] ) ? trim( $link['anchor_text'] ) : '';
			if ( '' === $anchor || preg_match( $weak_rx, $anchor ) ) {
				$weak[] = array(
					'from_id'     => isset( $link['from_id'] ) ? $link['from_id'] : 0,
					'from_title'  => isset( $link['from_title'] ) ? $link['from_title'] : '',
					'anchor_text' => $anchor,
					'href'        => $href,
				);
			}
		}

		$orphan_rows = is_array( $orphans ) ? $orphans : array();
		if ( empty( $orphan_rows ) ) {
			foreach ( $posts as $id => $row ) {
				if ( empty( $pointed[ $id ] ) ) {
					$orphan_rows[] = array(
						'id'        => $id,
						'title'     => isset( $row['title'] ) ? $row['title'] : ( isset( $row['name'] ) ? $row['name'] : '' ),
						'type'      => isset( $row['permalink'] ) ? '' : '',
						'edit_link' => isset( $row['edit_link'] ) ? $row['edit_link'] : '',
						'permalink' => isset( $row['permalink'] ) ? $row['permalink'] : '',
					);
				}
			}
		}

		$opp_rows = is_array( $opps ) ? $opps : array();
		if ( empty( $opp_rows ) ) {
			$by_cat = array();
			if ( ! empty( $scan['articles'] ) ) {
				foreach ( $scan['articles'] as $row ) {
					$cid = isset( $row['category_id'] ) ? (int) $row['category_id'] : 0;
					if ( $cid ) {
						$by_cat[ $cid ][] = $row;
					}
				}
			}
			foreach ( $by_cat as $group ) {
				if ( count( $group ) < 2 ) {
					continue;
				}
				$a = $group[0];
				$b = $group[1];
				$opp_rows[] = array(
					'from_id'    => $a['id'],
					'from_title' => $a['title'],
					'to_id'      => $b['id'],
					'to_title'   => $b['title'],
					'reason'     => isset( $a['category'] ) ? $a['category'] : __( 'Shared category', 'qpedia-seo-pro' ),
				);
				if ( count( $opp_rows ) >= 40 ) {
					break;
				}
			}
		}

		arsort( $incoming );
		$top = array();
		$i   = 0;
		foreach ( $incoming as $id => $n ) {
			$title = isset( $posts[ $id ]['title'] ) ? $posts[ $id ]['title'] : ( isset( $posts[ $id ]['name'] ) ? $posts[ $id ]['name'] : get_the_title( $id ) );
			$top[] = array(
				'id'       => $id,
				'title'    => $title,
				'incoming' => $n,
				'edit_link'=> isset( $posts[ $id ]['edit_link'] ) ? $posts[ $id ]['edit_link'] : get_edit_post_link( $id, 'raw' ),
			);
			if ( ++$i >= 20 ) {
				break;
			}
		}

		$weak_rows = is_array( $anchors ) && isset( $anchors['weak'] ) ? $anchors['weak'] : $weak;

		return array(
			'orphans'       => array_slice( $orphan_rows, 0, 100 ),
			'opportunities' => array_slice( $opp_rows, 0, 40 ),
			'top_incoming'  => $top,
			'weak_anchors'  => array_slice( $weak_rows, 0, 40 ),
		);
	}

	/**
	 * Sitemap / robots / meta-conflict snapshot.
	 *
	 * @return array
	 */
	private function collect_technical() {
		$sitemaps = $this->call_module( 'Sitemap', 'audit_sitemaps' );
		$robots   = $this->call_module( 'Robots', 'audit_robots' );
		$meta     = $this->call_module( 'Meta_Tags', 'detect_conflicts' );

		if ( ! is_array( $sitemaps ) ) {
			$sitemaps = array();
			$home     = home_url( '/' );
			$paths    = array(
				'sitemap.xml',
				'sitemap_index.xml',
				'wp-sitemap.xml',
				'quantum_category-sitemap.xml',
			);
			foreach ( $paths as $p ) {
				$url      = home_url( '/' . $p );
				$response = wp_remote_get(
					$url,
					array(
						'timeout'     => 8,
						'redirection' => 3,
					)
				);
				$code = is_wp_error( $response ) ? 0 : (int) wp_remote_retrieve_response_code( $response );
				$sitemaps[] = array(
					'path'    => '/' . $p,
					'url'     => $url,
					'status'  => $code,
					'ok'      => ( $code >= 200 && $code < 400 ),
					'note'    => ( 200 === $code ) ? __( 'Reachable', 'qpedia-seo-pro' ) : ( is_wp_error( $response ) ? $response->get_error_message() : sprintf(
						/* translators: HTTP status */
						__( 'HTTP %d', 'qpedia-seo-pro' ),
						$code
					) ),
				);
			}
			unset( $home );
		}

		if ( ! is_array( $robots ) ) {
			$robots_url = home_url( '/robots.txt' );
			$response   = wp_remote_get( $robots_url, array( 'timeout' => 8 ) );
			$body       = is_wp_error( $response ) ? '' : (string) wp_remote_retrieve_body( $response );
			$code       = is_wp_error( $response ) ? 0 : (int) wp_remote_retrieve_response_code( $response );
			$has_sm     = ( false !== stripos( $body, 'sitemap' ) );
			$has_admin  = ( false !== strpos( $body, '/wp-admin' ) );
			$robots     = array(
				'url'            => $robots_url,
				'status'         => $code,
				'body'           => $body,
				'has_sitemap'    => $has_sm,
				'blocks_admin'   => $has_admin,
				'issues'         => array(),
			);
			if ( ! $has_sm ) {
				$robots['issues'][] = __( 'robots.txt does not declare a Sitemap directive.', 'qpedia-seo-pro' );
			}
			if ( ! $has_admin ) {
				$robots['issues'][] = __( 'robots.txt does not disallowed /wp-admin/.', 'qpedia-seo-pro' );
			}
		}

		if ( ! is_array( $meta ) ) {
			$meta = $this->detect_meta_conflicts();
		}

		$redirects = array(
			array(
				'from'   => home_url( '/sitemap.xml' ),
				'to'     => home_url( '/sitemap_index.xml' ),
				'status' => 301,
				'note'   => __( 'Common Rank Math redirect.', 'qpedia-seo-pro' ),
			),
		);

		return array(
			'sitemaps'       => $sitemaps,
			'robots'         => $robots,
			'meta_conflicts' => $meta,
			'redirects'      => $redirects,
		);
	}

	/**
	 * Posts that still carry overlapping Yoast / Rank Math / Qpedia meta.
	 *
	 * @return array
	 */
	private function detect_meta_conflicts() {
		global $wpdb;
		$sql  = "SELECT post_id,
			SUM(CASE WHEN meta_key LIKE '_yoast_%' THEN 1 ELSE 0 END) AS yoast_n,
			SUM(CASE WHEN meta_key LIKE 'rank_math_%' THEN 1 ELSE 0 END) AS rank_n,
			SUM(CASE WHEN meta_key LIKE '_qpedia_%' OR meta_key LIKE 'qpedia_%' THEN 1 ELSE 0 END) AS qpedia_n
			FROM {$wpdb->postmeta}
			GROUP BY post_id
			HAVING (yoast_n > 0 AND rank_n > 0) OR (yoast_n > 0 AND qpedia_n > 0) OR (rank_n > 0 AND qpedia_n > 0)
			LIMIT 200";
		$rows = $wpdb->get_results( $sql ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$out  = array();
		if ( ! $rows ) {
			return $out;
		}
		foreach ( $rows as $row ) {
			$post_id = (int) $row->post_id;
			$out[]   = array(
				'id'        => $post_id,
				'title'     => get_the_title( $post_id ),
				'yoast'     => (int) $row->yoast_n,
				'rank_math' => (int) $row->rank_n,
				'qpedia'    => (int) $row->qpedia_n,
				'edit_link' => get_edit_post_link( $post_id, 'raw' ),
			);
		}
		return $out;
	}

	/**
	 * Site score, distribution, counts, issue tallies.
	 *
	 * @param array $scan Scan payload.
	 * @return array
	 */
	private function finalize_analyze( $scan ) {
		$from_a     = $this->call_module( 'Analyzer', 'get_site_score', array( $scan ) );
		$articles   = isset( $scan['articles'] ) && is_array( $scan['articles'] ) ? $scan['articles'] : array();
		$scientists = isset( $scan['scientists'] ) && is_array( $scan['scientists'] ) ? $scan['scientists'] : array();
		$pages      = isset( $scan['pages'] ) && is_array( $scan['pages'] ) ? $scan['pages'] : array();
		$images     = isset( $scan['images'] ) && is_array( $scan['images'] ) ? $scan['images'] : array();
		$terms      = self::category_terms( $scan );

		$avg = function ( $rows ) {
			if ( empty( $rows ) ) {
				return 0;
			}
			$sum = 0;
			$n   = 0;
			foreach ( $rows as $row ) {
				$sum += self::row_score( $row );
				$n++;
			}
			return $n ? $sum / $n : 0;
		};

		$img_score = 100;
		if ( ! empty( $images ) ) {
			$missing = 0;
			foreach ( $images as $img ) {
				if ( ! empty( $img['missing_alt'] ) ) {
					$missing++;
				}
			}
			$img_score = (int) round( 100 * ( 1 - ( $missing / max( 1, count( $images ) ) ) ) );
		}

		$term_scores = array();
		foreach ( $terms as $t ) {
			$s = 100;
			if ( isset( $t['description_length'] ) && (int) $t['description_length'] < 100 ) {
				$s -= 40;
			}
			if ( isset( $t['count'] ) && (int) $t['count'] < 3 ) {
				$s -= 20;
			}
			if ( empty( $t['is_latin'] ) ) {
				$s -= 15;
			}
			$term_scores[] = array( 'score' => max( 0, $s ) );
		}

		$site = 0;
		$grade = 'F';
		if ( is_array( $from_a ) && isset( $from_a['score'] ) ) {
			$site  = (int) $from_a['score'];
			$grade = isset( $from_a['grade'] ) ? (string) $from_a['grade'] : $this->grade( $site );
			$scan['site_score'] = $from_a;
		} elseif ( is_numeric( $from_a ) ) {
			$site = (int) round( $from_a );
			$scan['site_score'] = $site;
			$grade = $this->grade( $site );
		} else {
			$site = (int) round(
				$avg( $articles ) * 0.50
				+ $avg( $scientists ) * 0.20
				+ $avg( $term_scores ) * 0.15
				+ $avg( $pages ) * 0.05
				+ $img_score * 0.10
			);
			$grade = $this->grade( $site );
			$scan['site_score'] = $site;
		}

		$dist = array(
			'A' => 0,
			'B' => 0,
			'C' => 0,
			'D' => 0,
			'F' => 0,
		);
		foreach ( array_merge( $articles, $scientists, $pages ) as $row ) {
			$g = self::row_grade( $row );
			if ( isset( $dist[ $g ] ) ) {
				$dist[ $g ]++;
			}
		}

		$tallies = array(
			'critical' => 0,
			'high'     => 0,
			'medium'   => 0,
			'low'      => 0,
			'total'    => 0,
		);
		$recent  = array();
		$walk    = array_merge( $articles, $scientists, $terms );
		foreach ( $walk as $row ) {
			$issues = self::row_issues( $row );
			if ( empty( $issues ) ) {
				continue;
			}
			foreach ( $issues as $issue ) {
				$sev = self::issue_severity( $issue );
				if ( ! isset( $tallies[ $sev ] ) ) {
					$sev = 'medium';
				}
				$tallies[ $sev ]++;
				$tallies['total']++;
				if ( count( $recent ) < 12 ) {
					$recent[] = array(
						'severity'  => $sev,
						'message'   => self::issue_message( $issue ),
						'title'     => isset( $row['title'] ) ? $row['title'] : ( isset( $row['name'] ) ? $row['name'] : '' ),
						'edit_link' => isset( $row['edit_link'] ) ? $row['edit_link'] : ( self::row_id( $row ) ? get_edit_post_link( self::row_id( $row ), 'raw' ) : '' ),
					);
				}
			}
		}

		$links_n = 0;
		if ( ! empty( $scan['raw_links'] ) && is_array( $scan['raw_links'] ) ) {
			$links_n = count( $scan['raw_links'] );
		} elseif ( ! empty( $scan['links']['top_incoming'] ) ) {
			foreach ( $scan['links']['top_incoming'] as $t ) {
				$links_n += isset( $t['incoming'] ) ? (int) $t['incoming'] : 0;
			}
		}

		if ( ! is_array( $from_a ) || ! isset( $from_a['score'] ) ) {
			$scan['site_score'] = array(
				'score' => $site,
				'grade' => $grade,
			);
		}
		$scan['grade'] = $grade;
		$scan['summary'] = array(
			'issues_count' => $tallies['total'],
			'grades'       => $dist,
			'site_score'   => $site,
			'site_grade'   => $grade,
			'analyzed_at'  => current_time( 'mysql' ),
		);
		$scan['score_distribution'] = $dist;
		$scan['issue_tallies']      = $tallies;
		$scan['recent_issues']      = $recent;
		$scan['counts']             = array(
			'articles'   => count( $articles ),
			'scientists' => count( $scientists ),
			'pages'      => count( $pages ),
			'terms'      => count( $terms ),
			'tags'       => isset( $scan['tags']['total'] ) ? (int) $scan['tags']['total'] : ( isset( $scan['terms']['post_tag'] ) ? count( $scan['terms']['post_tag'] ) : 0 ),
			'images'     => count( $images ),
			'links'      => $links_n,
		);

		return $scan;
	}

	/**
	 * Scientist completeness, taxonomy tags, content audit, image audit, meta, schema samples.
	 *
	 * @param array $scan Scan payload.
	 * @return array
	 */
	private function run_extras( $scan ) {
		$comp = $this->call_module( 'Scientist_SEO', 'completeness_check' );
		if ( ! is_array( $comp ) ) {
			$scientists = isset( $scan['scientists'] ) ? $scan['scientists'] : array();
			$comp       = array(
				'summary' => $this->summarize_scientist_fields( $scientists ),
				'items'   => $scientists,
			);
		}
		$scan['scientist_completeness'] = $comp;

		$tax_cats = $this->call_module( 'Taxonomy_SEO', 'audit_categories' );
		$tax_tags = $this->call_module( 'Taxonomy_SEO', 'audit_tags' );
		if ( is_array( $tax_cats ) && empty( $scan['terms'] ) ) {
			$scan['terms'] = $tax_cats;
		}
		if ( is_array( $tax_tags ) ) {
			$scan['tags'] = $tax_tags;
		} else {
			$scan['tags'] = $this->audit_tags_fallback();
		}

		$content = array();
		foreach ( array( 'audit_thin_content', 'audit_duplicate_titles', 'audit_duplicate_descriptions', 'audit_keyword_cannibalization', 'audit_heading_structure', 'audit_draft_articles' ) as $method ) {
			$got = $this->call_module( 'Content_Audit', $method );
			if ( null !== $got ) {
				$content[ $method ] = $got;
			}
		}
		if ( empty( $content ) ) {
			$content = $this->content_audit_fallback( isset( $scan['articles'] ) ? $scan['articles'] : array() );
		}
		$scan['content_audit'] = $content;

		$img_audit = $this->call_module( 'Image_Audit', 'audit_all_images' );
		if ( ! is_array( $img_audit ) ) {
			$images   = isset( $scan['images'] ) ? $scan['images'] : array();
			$missing  = 0;
			$oversize = 0;
			foreach ( $images as $img ) {
				if ( ! empty( $img['missing_alt'] ) ) {
					$missing++;
				}
				if ( isset( $img['size_kb'] ) && (int) $img['size_kb'] > 200 ) {
					$oversize++;
				}
			}
			$img_audit = array(
				'missing_alt' => $missing,
				'oversized'   => $oversize,
				'total'       => count( $images ),
			);
		}
		$scan['image_audit'] = $img_audit;

		if ( empty( $scan['technical']['meta_conflicts'] ) ) {
			if ( ! isset( $scan['technical'] ) || ! is_array( $scan['technical'] ) ) {
				$scan['technical'] = array();
			}
			$scan['technical']['meta_conflicts'] = $this->detect_meta_conflicts();
		}

		$scan['schema'] = $this->build_schema_report( $scan );

		if ( ! empty( $scan['counts'] ) ) {
			$scan['counts']['tags'] = isset( $scan['tags']['total'] ) ? (int) $scan['tags']['total'] : 0;
		}

		return $scan;
	}

	/**
	 * Stamp last_scan and history onto the payload.
	 *
	 * @param array $scan Scan payload.
	 * @return array
	 */
	private function finalize_scan( $scan ) {
		$scan['last_scan'] = current_time( 'mysql' );
		$history           = get_option( self::HISTORY_KEY, array() );
		if ( ! is_array( $history ) ) {
			$history = array();
		}
		$scan['history'] = $history;
		unset( $scan['raw_links'] );
		return $scan;
	}

	/**
	 * Append a score history point.
	 *
	 * @param int $score Site score.
	 */
	private function push_history( $score ) {
		$history = get_option( self::HISTORY_KEY, array() );
		if ( ! is_array( $history ) ) {
			$history = array();
		}
		$score = (int) $score;
		$history[] = array(
			'at'         => current_time( 'mysql' ),
			'date'       => current_time( 'mysql' ),
			'site_score' => $score,
			'score'      => $score,
			'site_grade' => $this->grade( $score ),
		);
		if ( count( $history ) > 24 ) {
			$history = array_slice( $history, -24 );
		}
		update_option( self::HISTORY_KEY, array_values( $history ), false );
	}

	/**
	 * Hand the finished payload to Core when it exposes a setter.
	 *
	 * @param array $scan Scan payload.
	 */
	private function persist_scan_to_core( $scan ) {
		foreach ( array( 'set_scan_data', 'store_scan_data', 'save_scan_data' ) as $method ) {
			$ok = $this->call_module( 'Core', $method, array( $scan ) );
			if ( null !== $ok ) {
				return;
			}
		}
	}

	/**
	 * Schema type status + pretty samples.
	 *
	 * @param array $scan Scan payload.
	 * @return array
	 */
	private function build_schema_report( $scan ) {
		$from = $this->call_module( 'Schema', 'validate_schema' );
		if ( is_array( $from ) && isset( $from['types'] ) ) {
			return $from;
		}

		$article   = ( ! empty( $scan['articles'] ) ) ? $scan['articles'][0] : null;
		$scientist = ( ! empty( $scan['scientists'] ) ) ? $scan['scientists'][0] : null;
		$term      = ( ! empty( $scan['terms'] ) ) ? $scan['terms'][0] : null;

		$samples = array();
		$errors  = array();

		if ( $article ) {
			$samples['Article'] = array(
				'@context'         => 'https://schema.org',
				'@type'            => 'Article',
				'headline'         => isset( $article['title'] ) ? $article['title'] : '',
				'inLanguage'       => 'fa',
				'url'              => isset( $article['permalink'] ) ? $article['permalink'] : '',
				'dateModified'     => isset( $article['modified'] ) ? $article['modified'] : '',
				'articleSection'   => isset( $article['category'] ) ? $article['category'] : '',
				'keywords'         => isset( $article['keyword'] ) ? $article['keyword'] : '',
				'author'           => array(
					'@type' => 'Organization',
					'name'  => 'Qpedia Farsi',
					'url'   => 'https://qpedia.ir',
				),
				'publisher'        => array(
					'@type' => 'Organization',
					'name'  => 'Qpedia Farsi',
					'url'   => 'https://qpedia.ir',
				),
				'mainEntityOfPage' => isset( $article['permalink'] ) ? $article['permalink'] : '',
			);
		}

		if ( $scientist ) {
			$samples['Person'] = array(
				'@context'      => 'https://schema.org',
				'@type'         => 'Person',
				'name'          => isset( $scientist['name'] ) ? $scientist['name'] : '',
				'alternateName' => isset( $scientist['en_name'] ) ? $scientist['en_name'] : '',
				'url'           => isset( $scientist['permalink'] ) ? $scientist['permalink'] : '',
				'inLanguage'    => 'fa',
			);
			if ( empty( $scientist['en_name'] ) ) {
				$errors[] = array(
					'type'    => 'Person',
					'message' => __( 'Scientist is missing English name (alternateName).', 'qpedia-seo-pro' ),
					'id'      => isset( $scientist['id'] ) ? $scientist['id'] : 0,
				);
			}
		}

		if ( $term ) {
			$samples['CollectionPage'] = array(
				'@context'    => 'https://schema.org',
				'@type'       => 'CollectionPage',
				'name'        => isset( $term['name'] ) ? $term['name'] : '',
				'url'         => home_url( '/topic/' . ( isset( $term['slug'] ) ? $term['slug'] : '' ) . '/' ),
				'isPartOf'    => array(
					'@type' => 'WebSite',
					'name'  => 'Qpedia Farsi',
					'url'   => 'https://qpedia.ir',
				),
				'inLanguage'  => 'fa',
			);
		}

		$samples['WebSite'] = array(
			'@context' => 'https://schema.org',
			'@type'    => 'WebSite',
			'name'     => 'Qpedia Farsi',
			'url'      => 'https://qpedia.ir',
			'inLanguage' => 'fa',
		);

		$types = array(
			'Article'        => array(
				'status' => empty( $scan['articles'] ) ? 'missing' : 'ok',
				'count'  => isset( $scan['articles'] ) ? count( $scan['articles'] ) : 0,
				'label'  => __( 'Article', 'qpedia-seo-pro' ),
			),
			'Person'         => array(
				'status' => empty( $scan['scientists'] ) ? 'missing' : 'ok',
				'count'  => isset( $scan['scientists'] ) ? count( $scan['scientists'] ) : 0,
				'label'  => __( 'Person (scientist)', 'qpedia-seo-pro' ),
			),
			'CollectionPage' => array(
				'status' => empty( $scan['terms'] ) ? 'missing' : 'ok',
				'count'  => isset( $scan['terms'] ) ? count( $scan['terms'] ) : 0,
				'label'  => __( 'CollectionPage (topic)', 'qpedia-seo-pro' ),
			),
			'WebSite'        => array(
				'status' => 'ok',
				'count'  => 1,
				'label'  => __( 'WebSite', 'qpedia-seo-pro' ),
			),
			'Organization'   => array(
				'status' => 'ok',
				'count'  => 1,
				'label'  => __( 'Organization', 'qpedia-seo-pro' ),
			),
			'BreadcrumbList' => array(
				'status' => 'ok',
				'count'  => ( isset( $scan['articles'] ) ? count( $scan['articles'] ) : 0 ) + ( isset( $scan['scientists'] ) ? count( $scan['scientists'] ) : 0 ),
				'label'  => __( 'BreadcrumbList', 'qpedia-seo-pro' ),
			),
			'FAQPage'        => array(
				'status' => 'partial',
				'count'  => 0,
				'label'  => __( 'FAQPage (Rank Math)', 'qpedia-seo-pro' ),
			),
		);

		return array(
			'types'   => $types,
			'samples' => $samples,
			'errors'  => $errors,
		);
	}

	/**
	 * Tag unused / duplicate summary.
	 *
	 * @return array
	 */
	private function audit_tags_fallback() {
		if ( ! taxonomy_exists( 'post_tag' ) ) {
			return array(
				'total'      => 0,
				'unused'     => 0,
				'duplicates' => array(),
			);
		}
		$tags = get_terms(
			array(
				'taxonomy'   => 'post_tag',
				'hide_empty' => false,
				'number'     => 0,
			)
		);
		if ( is_wp_error( $tags ) ) {
			return array(
				'total'      => 0,
				'unused'     => 0,
				'duplicates' => array(),
			);
		}
		$unused = 0;
		$by_key = array();
		foreach ( $tags as $t ) {
			if ( (int) $t->count === 0 ) {
				$unused++;
			}
			$key = strtolower( preg_replace( '/[\s\-_]+/u', '', $t->slug ) );
			$by_key[ $key ][] = array(
				'id'    => (int) $t->term_id,
				'name'  => $t->name,
				'slug'  => $t->slug,
				'count' => (int) $t->count,
			);
		}
		$dups = array();
		foreach ( $by_key as $group ) {
			if ( count( $group ) > 1 ) {
				$dups[] = $group;
			}
		}
		return array(
			'total'      => count( $tags ),
			'unused'     => $unused,
			'duplicates' => array_slice( $dups, 0, 40 ),
		);
	}

	/**
	 * Lightweight content audit from already-scanned article rows.
	 *
	 * @param array $articles Article rows.
	 * @return array
	 */
	private function content_audit_fallback( $articles ) {
		$thin   = array();
		$boost  = array();
		$by_kw  = array();
		$by_ttl = array();
		foreach ( $articles as $row ) {
			$words = isset( $row['words'] ) ? (int) $row['words'] : 0;
			if ( $words < 300 ) {
				$thin[] = $row;
			} elseif ( $words < 800 ) {
				$boost[] = $row;
			}
			$kw = isset( $row['keyword'] ) ? mb_strtolower( trim( (string) $row['keyword'] ) ) : '';
			if ( '' !== $kw ) {
				$by_kw[ $kw ][] = $row;
			}
			$title = isset( $row['title'] ) ? mb_strtolower( trim( (string) $row['title'] ) ) : '';
			if ( '' !== $title ) {
				$by_ttl[ $title ][] = $row;
			}
		}
		$cannibal = array();
		foreach ( $by_kw as $kw => $group ) {
			if ( count( $group ) > 1 ) {
				$cannibal[] = array(
					'keyword' => $kw,
					'posts'   => $group,
				);
			}
		}
		$dup_titles = array();
		foreach ( $by_ttl as $group ) {
			if ( count( $group ) > 1 ) {
				$dup_titles[] = $group;
			}
		}

		$drafts = get_posts(
			array(
				'post_type'      => 'quantum_article',
				'post_status'    => 'draft',
				'posts_per_page' => 50,
				'orderby'        => 'date',
				'order'          => 'DESC',
			)
		);
		$draft_rows = array();
		foreach ( $drafts as $d ) {
			$draft_rows[] = array(
				'id'        => $d->ID,
				'title'     => get_the_title( $d ),
				'date'      => $d->post_date,
				'edit_link' => get_edit_post_link( $d->ID, 'raw' ),
			);
		}

		return array(
			'thin'              => $thin,
			'needs_boost'       => $boost,
			'duplicate_titles'  => $dup_titles,
			'cannibalization'   => $cannibal,
			'drafts'            => $draft_rows,
		);
	}

	/**
	 * Completeness checkboxes for scientist meta.
	 *
	 * @param array $scientists Scientist rows.
	 * @return array
	 */
	private function summarize_scientist_fields( $scientists ) {
		$fields = $this->scientist_field_map();
		$total  = count( $scientists );
		$out    = array();
		foreach ( $fields as $key => $label ) {
			$have = 0;
			if ( $total ) {
				foreach ( $scientists as $row ) {
					$missing = isset( $row['missing_fields'] ) ? $row['missing_fields'] : array();
					if ( ! in_array( $label, $missing, true ) && ! in_array( $key, $missing, true ) ) {
						$have++;
					}
				}
			}
			$out[ $key ] = array(
				'label' => $label,
				'have'  => $have,
				'total' => $total,
			);
		}
		return $out;
	}

	/**
	 * Scientist completeness field map.
	 *
	 * @return array
	 */
	private function scientist_field_map() {
		return array(
			'_scientist_en_name'      => __( 'English name', 'qpedia-seo-pro' ),
			'_scientist_fullname'     => __( 'Full name', 'qpedia-seo-pro' ),
			'_scientist_born_died'    => __( 'Born / died', 'qpedia-seo-pro' ),
			'_scientist_birthplace'   => __( 'Birthplace', 'qpedia-seo-pro' ),
			'_scientist_institutions' => __( 'Institutions', 'qpedia-seo-pro' ),
			'_scientist_achievement'  => __( 'Achievements', 'qpedia-seo-pro' ),
			'_scientist_nobel'        => __( 'Nobel', 'qpedia-seo-pro' ),
			'_scientist_concepts'     => __( 'Concepts', 'qpedia-seo-pro' ),
			'_scientist_family'       => __( 'Family', 'qpedia-seo-pro' ),
			'_qpedia_seo_title'       => __( 'SEO title', 'qpedia-seo-pro' ),
			'_qpedia_meta_description'=> __( 'Meta description', 'qpedia-seo-pro' ),
			'_qpedia_focus_keyphrase' => __( 'Focus keyphrase', 'qpedia-seo-pro' ),
			'_thumbnail_id'           => __( 'Featured image', 'qpedia-seo-pro' ),
			'content'                 => __( 'Body content (≥500 words)', 'qpedia-seo-pro' ),
		);
	}

	/**
	 * Missing scientist fields for one post.
	 *
	 * @param int $post_id Post ID.
	 * @return array
	 */
	private function scientist_missing_fields( $post_id ) {
		$map     = $this->scientist_field_map();
		$missing = array();
		$post    = get_post( $post_id );
		foreach ( $map as $key => $label ) {
			if ( 'content' === $key ) {
				if ( $this->word_count( $post ? $post->post_content : '' ) < 500 ) {
					$missing[] = $label;
				}
				continue;
			}
			if ( '_thumbnail_id' === $key ) {
				if ( ! get_post_thumbnail_id( $post_id ) ) {
					$missing[] = $label;
				}
				continue;
			}
			$val = get_post_meta( $post_id, $key, true );
			if ( '' === trim( (string) $val ) ) {
				$missing[] = $label;
			}
		}
		return $missing;
	}

	/* -----------------------------------------------------------------
	 * Fallback analyzers (used when Analyzer class is missing)
	 * ----------------------------------------------------------------- */

	/**
	 * Spec-aligned article scoring fallback.
	 *
	 * @param \WP_Post $post    Post.
	 * @return array
	 */
	private function fallback_analyze_article( $post ) {
		$issues       = array();
		$suggestions  = array();
		$score        = 0;
		$id           = $post->ID;
		$title_seo    = (string) get_post_meta( $id, 'rank_math_title', true );
		$desc         = (string) get_post_meta( $id, 'rank_math_description', true );
		$keyword      = (string) get_post_meta( $id, 'rank_math_focus_keyword', true );
		$faq          = get_post_meta( $id, 'rank_math_schema_FAQPage', true );
		$thumb        = get_post_thumbnail_id( $id );
		$words        = $this->word_count( $post->post_content );
		$has_cat      = taxonomy_exists( 'quantum_category' ) && ! empty( wp_get_post_terms( $id, 'quantum_category', array( 'fields' => 'ids' ) ) );

		if ( $title_seo ) {
			$n = mb_strlen( $title_seo );
			$pts = 10;
			if ( $n <= 60 ) {
				$pts += 3;
			} else {
				$issues[] = array(
					'severity' => 'medium',
					'message'  => __( 'SEO title is longer than 60 characters.', 'qpedia-seo-pro' ),
				);
			}
			if ( $keyword && false !== mb_stripos( $title_seo, $keyword ) ) {
				$pts += 2;
			}
			$score += min( 15, $pts );
		} else {
			$issues[] = array(
				'severity' => 'high',
				'message'  => __( 'Missing SEO title (rank_math_title).', 'qpedia-seo-pro' ),
			);
			$suggestions[] = __( 'Write an SEO title of 50–60 characters that includes the focus keyword.', 'qpedia-seo-pro' );
		}

		if ( $desc ) {
			$n = mb_strlen( $desc );
			$pts = 8;
			if ( $n >= 120 && $n <= 160 ) {
				$pts += 5;
			} else {
				$issues[] = array(
					'severity' => 'medium',
					'message'  => __( 'Meta description length is outside 120–160 characters.', 'qpedia-seo-pro' ),
				);
			}
			if ( $keyword && false !== mb_stripos( $desc, $keyword ) ) {
				$pts += 2;
			}
			$score += min( 15, $pts );
		} else {
			$issues[] = array(
				'severity' => 'high',
				'message'  => __( 'Missing meta description.', 'qpedia-seo-pro' ),
			);
			$suggestions[] = __( 'Add a 120–160 character meta description with the focus keyword.', 'qpedia-seo-pro' );
		}

		if ( $keyword ) {
			$score += 6;
			if ( false !== mb_stripos( $post->post_title, $keyword ) ) {
				$score += 4;
			} else {
				$issues[] = array(
					'severity' => 'medium',
					'message'  => __( 'Focus keyword is missing from the title.', 'qpedia-seo-pro' ),
				);
			}
		} else {
			$issues[] = array(
				'severity' => 'high',
				'message'  => __( 'No focus keyword is set.', 'qpedia-seo-pro' ),
			);
		}

		if ( $words >= 1500 ) {
			$score += 10;
		} elseif ( $words >= 800 ) {
			$score += 7;
		} elseif ( $words >= 300 ) {
			$score += 3;
			$issues[] = array(
				'severity' => 'medium',
				'message'  => __( 'Content is under 800 words.', 'qpedia-seo-pro' ),
			);
			$suggestions[] = __( 'Expand the article toward 1,500 Persian words.', 'qpedia-seo-pro' );
		} else {
			$issues[] = array(
				'severity' => 'critical',
				'message'  => __( 'Thin content (under 300 words).', 'qpedia-seo-pro' ),
			);
		}

		$has_h2 = (bool) preg_match( '/<h2\b/i', $post->post_content );
		$has_h3 = (bool) preg_match( '/<h3\b/i', $post->post_content );
		if ( $has_h2 && $has_h3 ) {
			$score += 10;
		} elseif ( $has_h2 ) {
			$score += 6;
			$issues[] = array(
				'severity' => 'low',
				'message'  => __( 'Add H3 subheadings under H2 sections.', 'qpedia-seo-pro' ),
			);
		} else {
			$issues[] = array(
				'severity' => 'high',
				'message'  => __( 'No H2 headings found.', 'qpedia-seo-pro' ),
			);
		}

		if ( $thumb ) {
			$alt = (string) get_post_meta( $thumb, '_wp_attachment_image_alt', true );
			$score += 6;
			if ( $alt ) {
				$score += 2;
				if ( $keyword && false !== mb_stripos( $alt, $keyword ) ) {
					$score += 2;
				}
			} else {
				$issues[] = array(
					'severity' => 'medium',
					'message'  => __( 'Featured image is missing alt text.', 'qpedia-seo-pro' ),
				);
			}
		} else {
			$issues[] = array(
				'severity' => 'high',
				'message'  => __( 'No featured image.', 'qpedia-seo-pro' ),
			);
		}

		$internal = preg_match_all( '/<a\s[^>]*href=["\'][^"\']*qpedia\.ir[^"\']*["\']/i', $post->post_content )
			+ preg_match_all( '/<a\s[^>]*href=["\']\/(?!wp-content)[^"\']+["\']/i', $post->post_content );
		if ( $internal >= 2 ) {
			$score += 10;
		} elseif ( $internal >= 1 ) {
			$score += 5;
			$issues[] = array(
				'severity' => 'medium',
				'message'  => __( 'Fewer than 2 internal links.', 'qpedia-seo-pro' ),
			);
		} else {
			$issues[] = array(
				'severity' => 'high',
				'message'  => __( 'No internal links in the content.', 'qpedia-seo-pro' ),
			);
			$suggestions[] = __( 'Link to at least two related Qpedia articles or scientists.', 'qpedia-seo-pro' );
		}

		$slug = $post->post_name;
		if ( $slug && preg_match( '/^[a-z0-9\-]+$/', $slug ) && mb_strlen( $slug ) <= 75 ) {
			$score += 5;
		} else {
			$issues[] = array(
				'severity' => 'low',
				'message'  => __( 'Slug should be Latin kebab-case and ≤75 characters.', 'qpedia-seo-pro' ),
			);
		}

		if ( $faq ) {
			$score += 5;
		}

		if ( $has_cat ) {
			$score += 5;
		} else {
			$issues[] = array(
				'severity' => 'high',
				'message'  => __( 'No quantum_category assigned.', 'qpedia-seo-pro' ),
			);
		}

		$modified = strtotime( $post->post_modified );
		if ( $modified && ( time() - $modified ) <= 6 * MONTH_IN_SECONDS ) {
			$score += 5;
		} else {
			$issues[] = array(
				'severity' => 'low',
				'message'  => __( 'Last update is older than 6 months.', 'qpedia-seo-pro' ),
			);
		}

		$score = max( 0, min( 100, $score ) );
		return array(
			'score'       => $score,
			'grade'       => $this->grade( $score ),
			'issues'      => $issues,
			'suggestions' => $suggestions,
			'details'     => array(
				'words'   => $words,
				'keyword' => $keyword,
			),
		);
	}

	/**
	 * Spec-aligned scientist scoring fallback.
	 *
	 * @param \WP_Post $post Post.
	 * @return array
	 */
	private function fallback_analyze_scientist( $post ) {
		$id          = $post->ID;
		$score       = 0;
		$issues      = array();
		$suggestions = array();

		$title = (string) get_post_meta( $id, '_qpedia_seo_title', true );
		$desc  = (string) get_post_meta( $id, '_qpedia_meta_description', true );
		$kw    = (string) get_post_meta( $id, '_qpedia_focus_keyphrase', true );

		if ( $title ) {
			$score += 15;
		} else {
			$issues[] = array(
				'severity' => 'high',
				'message'  => __( 'Missing scientist SEO title.', 'qpedia-seo-pro' ),
			);
		}
		if ( $desc ) {
			$score += 15;
		} else {
			$issues[] = array(
				'severity' => 'high',
				'message'  => __( 'Missing scientist meta description.', 'qpedia-seo-pro' ),
			);
		}
		if ( $kw ) {
			$score += 6;
			if ( false !== mb_stripos( $post->post_title, $kw ) || false !== mb_stripos( $title, $kw ) ) {
				$score += 4;
			}
		} else {
			$issues[] = array(
				'severity' => 'medium',
				'message'  => __( 'Missing focus keyphrase.', 'qpedia-seo-pro' ),
			);
		}

		foreach ( array(
			'_scientist_en_name'  => 5,
			'_scientist_fullname' => 5,
		) as $meta => $pts ) {
			if ( get_post_meta( $id, $meta, true ) ) {
				$score += $pts;
			} else {
				$issues[] = array(
					'severity' => 'medium',
					'message'  => sprintf(
						/* translators: meta key */
						__( 'Missing field: %s', 'qpedia-seo-pro' ),
						$meta
					),
				);
			}
		}

		foreach ( array( '_scientist_born_died', '_scientist_birthplace', '_scientist_institutions' ) as $meta ) {
			if ( get_post_meta( $id, $meta, true ) ) {
				$score += 3;
			}
		}
		foreach ( array( '_scientist_achievement', '_scientist_nobel', '_scientist_concepts' ) as $meta ) {
			if ( get_post_meta( $id, $meta, true ) ) {
				$score += 4;
			}
		}

		$thumb = get_post_thumbnail_id( $id );
		if ( $thumb ) {
			$score += 6;
			$alt = (string) get_post_meta( $thumb, '_wp_attachment_image_alt', true );
			if ( $alt ) {
				$score += 4;
			} else {
				$issues[] = array(
					'severity' => 'medium',
					'message'  => __( 'Featured image is missing alt text.', 'qpedia-seo-pro' ),
				);
			}
		} else {
			$issues[] = array(
				'severity' => 'high',
				'message'  => __( 'No featured image.', 'qpedia-seo-pro' ),
			);
		}

		$words = $this->word_count( $post->post_content );
		if ( $words >= 500 ) {
			$score += 10;
		} else {
			$issues[] = array(
				'severity' => 'high',
				'message'  => __( 'Scientist biography is under 500 words.', 'qpedia-seo-pro' ),
			);
			$suggestions[] = __( 'Expand the biography to at least 500 words.', 'qpedia-seo-pro' );
		}

		$internal = preg_match_all( '/<a\s[^>]*href=["\'][^"\']+["\']/i', $post->post_content );
		if ( $internal ) {
			$score += 9;
		} else {
			$issues[] = array(
				'severity' => 'medium',
				'message'  => __( 'No internal links to related articles.', 'qpedia-seo-pro' ),
			);
		}

		$score = max( 0, min( 100, $score ) );
		return array(
			'score'       => $score,
			'grade'       => $this->grade( $score ),
			'issues'      => $issues,
			'suggestions' => $suggestions,
			'details'     => array( 'words' => $words ),
		);
	}

	/**
	 * Unicode-aware word count (Persian whitespace).
	 *
	 * @param string $html Content.
	 * @return int
	 */
	private function word_count( $html ) {
		$text = trim( preg_replace( '/\s+/u', ' ', wp_strip_all_tags( (string) $html ) ) );
		if ( '' === $text ) {
			return 0;
		}
		$parts = preg_split( '/\s+/u', $text );
		return is_array( $parts ) ? count( $parts ) : 0;
	}

	/* -----------------------------------------------------------------
	 * Settings
	 * ----------------------------------------------------------------- */

	/**
	 * Whitelisted setting keys.
	 *
	 * @return string[]
	 */
	public static function setting_keys() {
		return array(
			'inject_schema',
			'skip_schema_if_rank_math',
			'inject_breadcrumb',
			'weekly_scan',
			'daily_quick',
			'batch_size',
		);
	}

	/**
	 * Default settings.
	 *
	 * @return array
	 */
	public function default_settings() {
		return array(
			'inject_schema'            => 1,
			'skip_schema_if_rank_math' => 1,
			'inject_breadcrumb'        => 1,
			'weekly_scan'              => 1,
			'daily_quick'              => 0,
			'batch_size'               => 50,
		);
	}

	/**
	 * Merged settings.
	 *
	 * @return array
	 */
	public function get_settings() {
		if ( class_exists( __NAMESPACE__ . '\\Core' ) && method_exists( Core::class, 'get_settings' ) ) {
			$s = Core::get_settings();
			if ( is_array( $s ) ) {
				return array_merge( $this->default_settings(), $s );
			}
		}
		$saved = get_option( self::SETTINGS_KEY, array() );
		if ( ! is_array( $saved ) ) {
			$saved = array();
		}
		return array_merge( $this->default_settings(), $saved );
	}

	/**
	 * Persist a whitelist of keys from a request array.
	 *
	 * @param array $src Request.
	 * @return array
	 */
	private function persist_settings( $src ) {
		$current = $this->get_settings();
		$keys    = self::setting_keys();
		$checkboxes = array(
			'inject_schema',
			'skip_schema_if_rank_math',
			'inject_breadcrumb',
			'weekly_scan',
			'daily_quick',
		);

		foreach ( $keys as $key ) {
			if ( in_array( $key, $checkboxes, true ) ) {
				$current[ $key ] = empty( $src[ $key ] ) ? 0 : 1;
				continue;
			}
			if ( 'batch_size' === $key ) {
				$n = isset( $src[ $key ] ) ? absint( $src[ $key ] ) : 50;
				$current[ $key ] = max( 1, min( 100, $n ) );
			}
		}

		update_option( self::SETTINGS_KEY, $current, false );
		return $current;
	}

	/* -----------------------------------------------------------------
	 * Module invoker
	 * ----------------------------------------------------------------- */

	/**
	 * Call a static or instance method on a QpediaSEO class when it exists.
	 *
	 * @param string $class  Short class name.
	 * @param string $method Method.
	 * @param array  $args   Arguments.
	 * @return mixed|null
	 */
	private function call_module( $class, $method, $args = array() ) {
		$fqcn = __NAMESPACE__ . '\\' . $class;
		if ( ! class_exists( $fqcn ) || ! method_exists( $fqcn, $method ) ) {
			return null;
		}
		try {
			$ref = new \ReflectionMethod( $fqcn, $method );
			if ( $ref->isStatic() ) {
				return $ref->invokeArgs( null, $args );
			}
			$obj = null;
			if ( method_exists( $fqcn, 'instance' ) ) {
				$obj = call_user_func( array( $fqcn, 'instance' ) );
			} elseif ( method_exists( $fqcn, 'get_instance' ) ) {
				$obj = call_user_func( array( $fqcn, 'get_instance' ) );
			} else {
				$obj = new $fqcn();
			}
			if ( ! is_object( $obj ) ) {
				return null;
			}
			return $ref->invokeArgs( $obj, $args );
		} catch ( \Throwable $e ) {
			return null;
		}
	}

	/**
	 * Whether an array is a list of items (not an assoc payload).
	 *
	 * @param array $arr Array.
	 * @return bool
	 */
	private function is_list( $arr ) {
		if ( empty( $arr ) ) {
			return true;
		}
		return array_keys( $arr ) === range( 0, count( $arr ) - 1 );
	}

	/**
	 * Numeric site score from a scan payload.
	 *
	 * @param array $scan Scan.
	 * @return int
	 */
	public static function site_score_int( $scan ) {
		if ( ! is_array( $scan ) ) {
			return 0;
		}
		if ( isset( $scan['summary']['site_score'] ) ) {
			return (int) $scan['summary']['site_score'];
		}
		if ( isset( $scan['site_score']['score'] ) ) {
			return (int) $scan['site_score']['score'];
		}
		if ( isset( $scan['site_score'] ) && is_numeric( $scan['site_score'] ) ) {
			return (int) $scan['site_score'];
		}
		return 0;
	}

	/**
	 * quantum_category rows from scan (nested or flat).
	 *
	 * @param array $scan Scan.
	 * @return array
	 */
	public static function category_terms( $scan ) {
		if ( empty( $scan['terms'] ) || ! is_array( $scan['terms'] ) ) {
			return array();
		}
		if ( isset( $scan['terms']['quantum_category'] ) && is_array( $scan['terms']['quantum_category'] ) ) {
			return $scan['terms']['quantum_category'];
		}
		if ( isset( $scan['terms'][0] ) ) {
			return $scan['terms'];
		}
		return array();
	}

	/**
	 * Row id.
	 *
	 * @param array $row Row.
	 * @return int
	 */
	public static function row_id( $row ) {
		if ( isset( $row['id'] ) ) {
			return (int) $row['id'];
		}
		if ( isset( $row['ID'] ) ) {
			return (int) $row['ID'];
		}
		if ( isset( $row['term_id'] ) ) {
			return (int) $row['term_id'];
		}
		return 0;
	}

	/**
	 * Row score.
	 *
	 * @param array $row Row.
	 * @return int
	 */
	public static function row_score( $row ) {
		if ( isset( $row['score'] ) ) {
			return (int) $row['score'];
		}
		if ( isset( $row['analysis']['score'] ) ) {
			return (int) $row['analysis']['score'];
		}
		return 0;
	}

	/**
	 * Row grade.
	 *
	 * @param array $row Row.
	 * @return string
	 */
	public static function row_grade( $row ) {
		if ( ! empty( $row['grade'] ) ) {
			return strtoupper( (string) $row['grade'] );
		}
		if ( ! empty( $row['analysis']['grade'] ) ) {
			return strtoupper( (string) $row['analysis']['grade'] );
		}
		$score = self::row_score( $row );
		if ( class_exists( __NAMESPACE__ . '\\Core' ) && method_exists( Core::class, 'grade' ) ) {
			return Core::grade( $score );
		}
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
	 * Row issues list.
	 *
	 * @param array $row Row.
	 * @return array
	 */
	public static function row_issues( $row ) {
		if ( isset( $row['issues'] ) && is_array( $row['issues'] ) ) {
			return $row['issues'];
		}
		if ( isset( $row['analysis']['issues'] ) && is_array( $row['analysis']['issues'] ) ) {
			return $row['analysis']['issues'];
		}
		return array();
	}

	/**
	 * Flatten an article for report tables.
	 *
	 * @param array $row Collected or flattened row.
	 * @return array
	 */
	public static function flatten_article( $row ) {
		if ( ! is_array( $row ) ) {
			return array();
		}
		$id       = self::row_id( $row );
		$issues   = self::row_issues( $row );
		$score    = self::row_score( $row );
		$keyword  = '';
		if ( ! empty( $row['keyword'] ) ) {
			$keyword = (string) $row['keyword'];
		} elseif ( ! empty( $row['metas']['rank_math_focus_keyword'] ) ) {
			$keyword = (string) $row['metas']['rank_math_focus_keyword'];
		}
		$cat    = isset( $row['category'] ) ? (string) $row['category'] : '';
		$cat_id = isset( $row['category_id'] ) ? (int) $row['category_id'] : 0;
		if ( '' === $cat && ! empty( $row['categories'][0]['name'] ) ) {
			$cat    = (string) $row['categories'][0]['name'];
			$cat_id = isset( $row['categories'][0]['id'] ) ? (int) $row['categories'][0]['id'] : 0;
		}
		$words = isset( $row['words'] ) ? (int) $row['words'] : ( isset( $row['word_count'] ) ? (int) $row['word_count'] : 0 );
		$edit  = isset( $row['edit_link'] ) ? $row['edit_link'] : ( $id ? get_edit_post_link( $id, 'raw' ) : '' );

		return array(
			'id'           => $id,
			'title'        => isset( $row['title'] ) ? $row['title'] : '',
			'slug'         => isset( $row['slug'] ) ? $row['slug'] : '',
			'score'        => $score,
			'grade'        => self::row_grade( $row ),
			'words'        => $words,
			'keyword'      => $keyword,
			'category'     => $cat,
			'category_id'  => $cat_id,
			'modified'     => isset( $row['modified'] ) ? $row['modified'] : '',
			'issues'       => $issues,
			'issues_count' => count( $issues ),
			'suggestions'  => isset( $row['suggestions'] ) ? $row['suggestions'] : ( isset( $row['analysis']['suggestions'] ) ? $row['analysis']['suggestions'] : array() ),
			'edit_link'    => $edit,
			'permalink'    => isset( $row['permalink'] ) ? $row['permalink'] : '',
		);
	}

	/**
	 * Flatten a scientist row.
	 *
	 * @param array $row Row.
	 * @return array
	 */
	public static function flatten_scientist( $row ) {
		if ( ! is_array( $row ) ) {
			return array();
		}
		$id      = self::row_id( $row );
		$issues  = self::row_issues( $row );
		$metas   = isset( $row['metas'] ) && is_array( $row['metas'] ) ? $row['metas'] : array();
		$en      = isset( $row['en_name'] ) ? $row['en_name'] : ( isset( $metas['_scientist_en_name'] ) ? $metas['_scientist_en_name'] : '' );
		$missing = isset( $row['missing_fields'] ) && is_array( $row['missing_fields'] ) ? $row['missing_fields'] : array();
		if ( empty( $missing ) && $id ) {
			$map = array(
				'_scientist_en_name'       => __( 'English name', 'qpedia-seo-pro' ),
				'_scientist_fullname'      => __( 'Full name', 'qpedia-seo-pro' ),
				'_scientist_born_died'     => __( 'Born / died', 'qpedia-seo-pro' ),
				'_scientist_birthplace'    => __( 'Birthplace', 'qpedia-seo-pro' ),
				'_scientist_institutions'  => __( 'Institutions', 'qpedia-seo-pro' ),
				'_scientist_achievement'   => __( 'Achievements', 'qpedia-seo-pro' ),
				'_scientist_nobel'         => __( 'Nobel', 'qpedia-seo-pro' ),
				'_scientist_concepts'      => __( 'Concepts', 'qpedia-seo-pro' ),
				'_scientist_family'        => __( 'Family', 'qpedia-seo-pro' ),
				'_qpedia_seo_title'        => __( 'SEO title', 'qpedia-seo-pro' ),
				'_qpedia_meta_description' => __( 'Meta description', 'qpedia-seo-pro' ),
				'_qpedia_focus_keyphrase'  => __( 'Focus keyphrase', 'qpedia-seo-pro' ),
			);
			foreach ( $map as $key => $label ) {
				$val = isset( $metas[ $key ] ) ? $metas[ $key ] : get_post_meta( $id, $key, true );
				if ( '' === trim( (string) $val ) ) {
					$missing[] = $label;
				}
			}
			if ( empty( $row['thumbnail']['id'] ) && ! get_post_thumbnail_id( $id ) ) {
				$missing[] = __( 'Featured image', 'qpedia-seo-pro' );
			}
			$words = isset( $row['word_count'] ) ? (int) $row['word_count'] : ( isset( $row['words'] ) ? (int) $row['words'] : 0 );
			if ( $words < 500 ) {
				$missing[] = __( 'Body content (≥500 words)', 'qpedia-seo-pro' );
			}
		}
		$total_f = 14;
		$have    = max( 0, $total_f - count( $missing ) );
		$pct     = isset( $row['completeness'] ) ? (int) $row['completeness'] : (int) round( 100 * $have / $total_f );

		return array(
			'id'             => $id,
			'name'           => isset( $row['name'] ) ? $row['name'] : ( isset( $row['title'] ) ? $row['title'] : '' ),
			'en_name'        => $en,
			'score'          => self::row_score( $row ),
			'grade'          => self::row_grade( $row ),
			'completeness'   => $pct,
			'missing_fields' => $missing,
			'issues'         => $issues,
			'issues_count'   => count( $issues ),
			'edit_link'      => isset( $row['edit_link'] ) ? $row['edit_link'] : ( $id ? get_edit_post_link( $id, 'raw' ) : '' ),
			'permalink'      => isset( $row['permalink'] ) ? $row['permalink'] : '',
		);
	}

	/**
	 * Flatten a category term.
	 *
	 * @param array $row Row.
	 * @return array
	 */
	public static function flatten_term( $row ) {
		if ( ! is_array( $row ) ) {
			return array();
		}
		$issues  = self::row_issues( $row );
		$latin   = isset( $row['is_latin'] ) ? (bool) $row['is_latin'] : ! empty( $row['is_latin_slug'] );
		$desc_len = isset( $row['description_length'] ) ? (int) $row['description_length'] : ( isset( $row['word_count'] ) ? (int) $row['word_count'] : 0 );

		return array(
			'id'                 => self::row_id( $row ),
			'name'               => isset( $row['name'] ) ? $row['name'] : '',
			'slug'               => isset( $row['slug'] ) ? $row['slug'] : '',
			'count'              => isset( $row['count'] ) ? (int) $row['count'] : 0,
			'description_length' => $desc_len,
			'is_latin'           => $latin,
			'issues'             => $issues,
			'issues_count'       => count( $issues ),
			'parent'             => isset( $row['parent'] ) ? (int) $row['parent'] : 0,
		);
	}

	/**
	 * Flatten an image row.
	 *
	 * @param array $row Row.
	 * @return array
	 */
	public static function flatten_image( $row ) {
		if ( ! is_array( $row ) ) {
			return array();
		}
		$id     = self::row_id( $row );
		$alt    = isset( $row['alt'] ) ? (string) $row['alt'] : '';
		$size   = isset( $row['size_kb'] ) ? (int) $row['size_kb'] : 0;
		if ( ! $size && isset( $row['filesize'] ) ) {
			$size = (int) round( (int) $row['filesize'] / 1024 );
		}
		$parent_id = isset( $row['parent_id'] ) ? (int) $row['parent_id'] : ( isset( $row['attached_to'] ) ? (int) $row['attached_to'] : 0 );
		$parent    = isset( $row['parent'] ) ? (string) $row['parent'] : '';
		if ( '' === $parent && $parent_id ) {
			$parent = get_the_title( $parent_id );
		}
		$featured = ! empty( $row['featured'] );
		if ( ! $featured && $parent_id && $id ) {
			$featured = ( (int) get_post_meta( $parent_id, '_thumbnail_id', true ) === $id );
		}
		$file = isset( $row['file'] ) ? $row['file'] : '';
		if ( $file && false !== strpos( $file, '/' ) ) {
			$file = basename( $file );
		}
		if ( '' === $file && ! empty( $row['title'] ) ) {
			$file = $row['title'];
		}
		$thumb = isset( $row['thumb'] ) ? $row['thumb'] : '';
		if ( '' === $thumb && $id ) {
			$src = wp_get_attachment_image_url( $id, 'thumbnail' );
			$thumb = $src ? $src : ( isset( $row['url'] ) ? $row['url'] : '' );
		}

		return array(
			'id'          => $id,
			'thumb'       => $thumb,
			'file'        => $file,
			'alt'         => $alt,
			'size_kb'     => $size,
			'width'       => isset( $row['width'] ) ? (int) $row['width'] : 0,
			'height'      => isset( $row['height'] ) ? (int) $row['height'] : 0,
			'parent'      => $parent,
			'parent_id'   => $parent_id,
			'featured'    => $featured,
			'missing_alt' => ! empty( $row['missing_alt'] ) || ( '' === trim( $alt ) ),
			'edit_link'   => isset( $row['edit_link'] ) ? $row['edit_link'] : ( $id ? get_edit_post_link( $id, 'raw' ) : '' ),
			'url'         => isset( $row['url'] ) ? $row['url'] : '',
		);
	}
}

