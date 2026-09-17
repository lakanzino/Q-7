<?php
/**
 * Quantum Pedia Child — functions (v1.6.1)
 *
 * پوستهٔ فرزند سبک و تمیز: فقط ساختارهای ضروری سایت.
 *
 * امکانات:
 * ۱. بارگذاری ترجمه، استایل‌ها و اسکریپت پوستهٔ فرزند
 * ۲. ثبت نوع‌های محتوایی quantum_article و quantum_scientist + طبقه‌بندی quantum_category
 * ۳. لینک تخت مقاله‌ها (/slug/) + رفع باگ ۴۰۴ برگه‌ها
 * ۴. متای سئو و OpenGraph + اسکیمای ScholarlyArticle + نقشهٔ سایت /quantum-sitemap.xml
 * ۵. وصلهٔ سایت‌مپ بومی وردپرس + کوئری‌های اصلی + نرمال‌سازی جست‌وجوی فارسی
 * ۶. شورت‌کدها و بلوک‌های صفحهٔ اصلی (num/note + بلوک دانشمندان)
 * ۷. سئوی فنی: canonical سراسری + ریدایرکت sitemap.xml + قفل تاکسونومی‌های قدیمی
 *
 * تغییرات 1.6.1 نسبت به قبل:
 * - canonical یکتا برای همهٔ صفحات (خانه، دسته‌ها، آرشیو دانشمندان و...)
 * - ریدایرکت ۳۰۱ مسیرهای sitemap.xml و sitemap_index.xml به wp-sitemap.xml
 * - معرفی نقشهٔ سایت در robots.txt
 * - حذف ریدایرکت قدیمی /glossary/ (تا ساخت برگهٔ واقعی، ۴۰۴ صادقانه)
 * - قفل تاکسونومی‌های قدیمی scientist_field و article_domain
 *
 * @package Quantum_Pedia_Child
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'QPEDIA_CHILD_VERSION', '2026.09.17' );

/* ── ۱. راه‌اندازی: بارگذاری ترجمه (رفع خطای Doing it Wrong) ── */
function qpedia_child_setup() {
	load_child_theme_textdomain( 'quantum-pedia-child', get_stylesheet_directory() . '/languages' );
}
add_action( 'after_setup_theme', 'qpedia_child_setup' );

/* ── ۲. استایل‌ها و اسکریپت پوستهٔ فرزند (تک‌تابع، بدون ثبت تکراری) ── */
function qpedia_child_enqueue_assets() {
	wp_enqueue_style(
		'qpedia-child-style',
		get_stylesheet_uri(),
		array(),
		wp_get_theme()->get( 'Version' )
	);

	$styles = array(
		'qpedia-layouts'       => array( 'file' => 'assets/css/qpedia-layouts.css', 'deps' => array( 'qpedia-child-style' ) ),
		'qpedia-child-custom'  => array( 'file' => 'assets/css/custom.css', 'deps' => array( 'qpedia-layouts' ) ),
		'qpedia-guide'         => array( 'file' => 'assets/css/qpedia-guide.css', 'deps' => array( 'qpedia-child-custom' ) ),
	);

	foreach ( $styles as $handle => $style ) {
		$path = get_stylesheet_directory() . '/' . $style['file'];
		if ( file_exists( $path ) ) {
			wp_enqueue_style(
				$handle,
				get_stylesheet_directory_uri() . '/' . $style['file'],
				$style['deps'],
				filemtime( $path )
			);
		}
	}

	$custom_js = get_stylesheet_directory() . '/assets/js/custom.js';
	if ( file_exists( $custom_js ) ) {
		wp_enqueue_script(
			'qpedia-child-custom',
			get_stylesheet_directory_uri() . '/assets/js/custom.js',
			array(),
			filemtime( $custom_js ),
			true
		);
	}
}
add_action( 'wp_enqueue_scripts', 'qpedia_child_enqueue_assets', 20 );

/* ── ۳. پاک‌سازی سبک head + بستن XML-RPC ── */
remove_action( 'wp_head', 'wp_generator' );
remove_action( 'wp_head', 'rsd_link' );
remove_action( 'wp_head', 'wlwmanifest_link' );
remove_action( 'wp_head', 'wp_shortlink_wp_head', 10 );

add_filter( 'xmlrpc_enabled', '__return_false' );

/* ── ۴. ثبت ساختارهای اصلی محتوا ── */
function qpedia_child_register_content_types() {
	register_post_type(
		'quantum_article',
		array(
			'labels' => array(
				'name'          => 'مقالات کوانتوم',
				'singular_name' => 'مقالهٔ کوانتوم',
				'add_new_item'  => 'افزودن مقالهٔ جدید',
				'edit_item'     => 'ویرایش مقاله',
			),
			'public'             => true,
			'publicly_queryable' => true,
			'show_ui'            => true,
			'show_in_rest'       => true,
			'menu_icon'          => 'dashicons-media-document',
			'supports'           => array( 'title', 'editor', 'excerpt', 'thumbnail', 'author' ),
			'has_archive'        => false,
			'rewrite'            => false,
			'query_var'          => 'quantum_article',
		)
	);

	register_post_type(
		'quantum_scientist',
		array(
			'labels' => array(
				'name'          => 'دانشمندان کوانتوم',
				'singular_name' => 'دانشمند کوانتوم',
				'add_new_item'  => 'افزودن دانشمند جدید',
				'edit_item'     => 'ویرایش دانشمند',
			),
			'public'             => true,
			'publicly_queryable' => true,
			'show_ui'            => true,
			'show_in_rest'       => true,
			'menu_icon'          => 'dashicons-groups',
			'supports'           => array( 'title', 'editor', 'excerpt', 'thumbnail', 'author' ),
			'has_archive'        => 'scientists',
			'rewrite'            => array(
				'slug'       => 'scientists',
				'with_front' => false,
			),
		)
	);

	register_taxonomy(
		'quantum_category',
		array( 'quantum_article' ),
		array(
			'labels' => array(
				'name'              => 'دسته‌بندی کوانتوم',
				'singular_name'     => 'دسته',
				'search_items'      => 'جست‌وجوی دسته',
				'all_items'         => 'همهٔ دسته‌ها',
				'parent_item'       => 'دستهٔ مادر',
				'parent_item_colon' => 'دستهٔ مادر:',
				'edit_item'         => 'ویرایش دسته',
				'update_item'       => 'به‌روزرسانی دسته',
				'add_new_item'      => 'افزودن دستهٔ جدید',
				'new_item_name'     => 'نام دستهٔ جدید',
				'menu_name'         => 'دسته‌بندی کوانتوم',
			),
			'hierarchical'      => true,
			'public'            => true,
			'show_ui'           => true,
			'show_admin_column' => true,
			'show_in_rest'      => true,
			'show_in_nav_menus' => true,
			'rewrite'           => array(
				'slug'         => 'topic',
				'with_front'   => false,
				'hierarchical' => false,
			),
		)
	);
}
add_action( 'init', 'qpedia_child_register_content_types', 5 );

/* ── ۵. rewrite مقاله‌های تخت: /slug/ ── */
function qpedia_child_rewrite_rules() {
	// این rule بالاتر از rule عمومی page می‌نشیند تا مقاله‌ها 404 نشوند؛
	// هم‌زمان مسیرهای رزروشده مستثنا می‌شوند.
	add_rewrite_rule(
		'^(?!scientists/?$)(?!topic/)(?!wp-admin/?$)(?!wp-json/?$)(?!feed/?$)(?!page/)(?!search/?$)(?!robots\\.txt$)(?!favicon\\.ico$)(?!xmlrpc\\.php$)(?!wp-login\\.php$)(?!sitemap.*\\.xml$)([a-z0-9][a-z0-9\\-]{2,})/?$',
		'index.php?quantum_article=$matches[1]',
		'top'
	);
}
add_action( 'init', 'qpedia_child_rewrite_rules', 20 );

/* ── ۶. جلوگیری از بلعیده‌شدن مسیرهای سیستمی + رفع باگ ۴۰۴ برگه‌ها ── */
function qpedia_child_filter_article_request( $query_vars ) {
	if ( empty( $query_vars['quantum_article'] ) ) {
		return $query_vars;
	}

	$slug = sanitize_title_for_query( $query_vars['quantum_article'] );

	// برگه‌های ثابت سایت — همیشه «برگه»‌اند، نه مقاله.
	$known_pages = array(
		'start',
		'about-us',
		'contact-us',
		'privacy-policy',
	);

	if ( in_array( $slug, $known_pages, true ) ) {
		unset( $query_vars['quantum_article'] );
		$query_vars['pagename'] = $slug;
		return $query_vars;
	}

	$reserved = array(
		'glossary',
		'scientists',
		'topic',
		'page',
		'feed',
		'author',
		'category',
		'tag',
		'search',
		'wp-json',
		'wp-admin',
		'wp-login',
		'xmlrpc',
		'robots',
		'favicon',
		'sitemap',
	);

	// مسیر رزرو‌شده → بگذار WordPress خودش تصمیم بگیرد.
	if ( in_array( $slug, $reserved, true ) ) {
		unset( $query_vars['quantum_article'] );
		return $query_vars;
	}

	// اگر مقالهٔ کوانتومی با این اسلاگ هست → همین درست است.
	$article = get_page_by_path( $slug, OBJECT, 'quantum_article' );
	if ( $article instanceof WP_Post ) {
		return $query_vars;
	}

	// مقاله نبود؛ ببین «برگه» با این اسلاگ هست؟
	$page = get_page_by_path( $slug, OBJECT, 'page' );
	if ( $page instanceof WP_Post ) {
		unset( $query_vars['quantum_article'] );
		$query_vars['pagename'] = $slug;
		return $query_vars;
	}

	// نه مقاله بود نه برگه → بگذار طبیعی 404 شود.
	unset( $query_vars['quantum_article'] );
	return $query_vars;
}
add_filter( 'request', 'qpedia_child_filter_article_request' );

/* ── ۷. لینک درست مقاله‌های تخت ── */
function qpedia_child_article_permalink( $post_link, $post ) {
	if ( isset( $post->post_type ) && 'quantum_article' === $post->post_type ) {
		return home_url( '/' . $post->post_name . '/' );
	}

	return $post_link;
}
add_filter( 'post_type_link', 'qpedia_child_article_permalink', 10, 2 );

/* ── ۸. یک‌بار flush پس از به‌روزرسانی قالب ── */
function qpedia_child_maybe_flush_rewrites() {
	$version = 'qpedia-1.2.0';
	if ( get_option( 'qpedia_child_rewrite_version' ) !== $version ) {
		flush_rewrite_rules();
		update_option( 'qpedia_child_rewrite_version', $version, false );
	}
}
add_action( 'init', 'qpedia_child_maybe_flush_rewrites', 99 );

/* ── ۹. ریدایرکت قدیمی /glossary/ حذف شد (سئو 1405/06): تا ساخته شدن برگهٔ واقعی، 404 صادقانه برمی‌گردد ── */

/* ── ۱۰. تنظیم کوئری‌های اصلی ── */
function qpedia_child_main_queries( $query ) {
	if ( is_admin() || ! $query->is_main_query() ) {
		return;
	}

	// اگر خانه روی «آخرین نوشته‌ها» باشد، مقالات کوانتوم را نشان بده.
	if ( $query->is_home() ) {
		$query->set( 'post_type', array( 'quantum_article' ) );
		$query->set( 'posts_per_page', 12 );
		$query->set( 'ignore_sticky_posts', true );
		return;
	}

	// جست‌وجو: مقاله + دانشمند + برگه.
	if ( $query->is_search() ) {
		$query->set( 'post_type', array( 'quantum_article', 'quantum_scientist', 'page' ) );
		$query->set( 'posts_per_page', 12 );
		return;
	}

	// آرشیو دانشمندان.
	if ( $query->is_post_type_archive( 'quantum_scientist' ) ) {
		$query->set( 'posts_per_page', 24 );
		$query->set( 'ignore_sticky_posts', true );
		return;
	}

	// آرشیو دستهٔ کوانتومی.
	if ( $query->is_tax( 'quantum_category' ) ) {
		$query->set( 'post_type', array( 'quantum_article' ) );
		$query->set( 'posts_per_page', 24 );
	}
}
add_action( 'pre_get_posts', 'qpedia_child_main_queries' );

/* ── ۱۱. نرمال‌سازی جست‌وجوی فارسی ── */
function qpedia_child_normalize_search_query( $query ) {
	if ( is_search() ) {
		$query = str_replace( array( 'ي', 'ك', '‌' ), array( 'ی', 'ک', ' ' ), $query );
	}
	return $query;
}
add_filter( 'get_search_query', 'qpedia_child_normalize_search_query' );

/* ── ۱۲. وصلهٔ سایت‌مپ بومی وردپرس ── */
if ( ! function_exists( 'qpedia_is_native_sitemap_request' ) ) {
	function qpedia_is_native_sitemap_request() {
		$request_uri = isset( $_SERVER['REQUEST_URI'] ) ? wp_unslash( $_SERVER['REQUEST_URI'] ) : '';
		$path        = wp_parse_url( $request_uri, PHP_URL_PATH );
		$path        = is_string( $path ) ? trim( $path, '/' ) : '';

		if ( '' === $path ) {
			return false;
		}

		return (bool) preg_match( '/^wp-sitemap(?:-[a-z0-9_-]+)*(?:-\\d+)?\\.xml$/i', $path );
	}
}

if ( ! function_exists( 'qpedia_native_sitemap_pre_handle_404' ) ) {
	function qpedia_native_sitemap_pre_handle_404( $preempt, $wp_query ) {
		if ( is_admin() || ! qpedia_is_native_sitemap_request() ) {
			return $preempt;
		}

		if ( is_object( $wp_query ) && isset( $wp_query->is_404 ) ) {
			$wp_query->is_404 = false;
		}

		status_header( 200 );
		return true;
	}
}
add_filter( 'pre_handle_404', 'qpedia_native_sitemap_pre_handle_404', 10, 2 );

if ( ! function_exists( 'qpedia_filter_sitemap_provider' ) ) {
	function qpedia_filter_sitemap_provider( $provider, $name ) {
		if ( 'users' === $name ) {
			return false;
		}

		return $provider;
	}
}
add_filter( 'wp_sitemaps_add_provider', 'qpedia_filter_sitemap_provider', 10, 2 );

if ( ! function_exists( 'qpedia_filter_sitemap_taxonomies' ) ) {
	function qpedia_filter_sitemap_taxonomies( $taxonomies ) {
		unset( $taxonomies['article_domain'] );
		unset( $taxonomies['scientist_field'] );

		return $taxonomies;
	}
}
add_filter( 'wp_sitemaps_taxonomies', 'qpedia_filter_sitemap_taxonomies' );

/* ── ۱۳. پیدا کردن آدرس برگه از بین چند اسلاگ محتمل ── */
function qpedia_child_find_page_url( $candidate_slugs, $fallback_path = '/' ) {
	if ( empty( $candidate_slugs ) || ! is_array( $candidate_slugs ) ) {
		return home_url( $fallback_path );
	}

	foreach ( $candidate_slugs as $slug ) {
		$page = get_page_by_path( $slug );
		if ( $page instanceof WP_Post ) {
			return get_permalink( $page );
		}
	}

	return home_url( $fallback_path );
}

/* ── ۱۴. بی‌اثر کردن شورت‌کدهای مردهٔ افزونه‌های قبلی ── */
add_shortcode( 'qsci_card', '__return_empty_string' );
add_shortcode( 'qpt_card', '__return_empty_string' );
add_shortcode( 'qterm', '__return_empty_string' );

/* ── ۱۵. بارگذاری امن ماژول واژه‌نامه (اختیاری) ── */
foreach ( array( 'glossary-post-type', 'glossary-assets', 'glossary-cache', 'glossary-content' ) as $qpedia_inc ) {
	$qpedia_inc_file = get_stylesheet_directory() . '/inc/' . $qpedia_inc . '.php';
	if ( file_exists( $qpedia_inc_file ) ) {
		require_once $qpedia_inc_file;
	}
}
unset( $qpedia_inc, $qpedia_inc_file );

/* ── ۱۶. متاتگ‌های سئو و OpenGraph مقالات ── */
add_action( 'wp_head', 'qpedia_auto_seo_meta_tags', 1 );
function qpedia_auto_seo_meta_tags() {
	if ( ! is_singular( 'quantum_article' ) ) {
		return;
	}

	$post_id   = get_the_ID();
	$post      = get_post( $post_id );
	$title     = esc_attr( get_the_title( $post_id ) . ' | دانشنامه کوانتوم پدیا' );
	$url       = esc_url( get_permalink( $post_id ) );
	$image     = esc_url( get_the_post_thumbnail_url( $post_id, 'large' ) ?: get_site_icon_url( 512 ) );
	$published = esc_attr( get_the_date( 'c', $post_id ) );
	$modified  = esc_attr( get_the_modified_date( 'c', $post_id ) );

	$desc = wp_strip_all_tags( get_the_excerpt( $post_id ) );
	if ( empty( $desc ) ) {
		$desc = wp_trim_words( wp_strip_all_tags( $post->post_content ), 30, '...' );
	}
	$desc = esc_attr( $desc );

	echo "\n<!-- Qpedia SEO & OpenGraph Meta Tags -->\n";
	echo '<meta name="description" content="' . $desc . '">' . "\n";
	echo '<link rel="canonical" href="' . $url . '">' . "\n";

	echo '<meta property="og:locale" content="fa_IR">' . "\n";
	echo '<meta property="og:type" content="article">' . "\n";
	echo '<meta property="og:title" content="' . $title . '">' . "\n";
	echo '<meta property="og:description" content="' . $desc . '">' . "\n";
	echo '<meta property="og:url" content="' . $url . '">' . "\n";
	echo '<meta property="og:site_name" content="کوانتوم پدیا">' . "\n";
	if ( $image ) {
		echo '<meta property="og:image" content="' . $image . '">' . "\n";
	}
	echo '<meta property="article:published_time" content="' . $published . '">' . "\n";
	echo '<meta property="article:modified_time" content="' . $modified . '">' . "\n";

	echo '<meta name="twitter:card" content="summary_large_image">' . "\n";
	echo '<meta name="twitter:title" content="' . $title . '">' . "\n";
	echo '<meta name="twitter:description" content="' . $desc . '">' . "\n";
	if ( $image ) {
		echo '<meta name="twitter:image" content="' . $image . '">' . "\n";
	}
	echo "<!-- /Qpedia SEO -->\n\n";
}

/* ── ۱۷. اسکیمای ScholarlyArticle (JSON-LD) ── */
add_action( 'wp_head', 'qpedia_auto_article_schema', 2 );
function qpedia_auto_article_schema() {
	if ( ! is_singular( 'quantum_article' ) ) {
		return;
	}

	$post_id   = get_the_ID();
	$post      = get_post( $post_id );
	$image_url = get_the_post_thumbnail_url( $post_id, 'full' );
	$excerpt   = wp_strip_all_tags( get_the_excerpt( $post_id ) );

	if ( empty( $excerpt ) ) {
		$excerpt = wp_trim_words( wp_strip_all_tags( $post->post_content ), 35, '...' );
	}

	$schema = array(
		'@context'         => 'https://schema.org',
		'@type'            => 'ScholarlyArticle',
		'headline'         => get_the_title( $post_id ),
		'description'      => $excerpt,
		'inLanguage'       => 'fa-IR',
		'mainEntityOfPage' => array(
			'@type' => 'WebPage',
			'@id'   => get_permalink( $post_id ),
		),
		'datePublished'    => get_the_date( 'c', $post_id ),
		'dateModified'     => get_the_modified_date( 'c', $post_id ),
		'author'           => array(
			'@type' => 'Organization',
			'name'  => 'کوانتوم پدیا',
			'url'   => home_url(),
		),
		'publisher'        => array(
			'@type' => 'Organization',
			'name'  => 'کوانتوم پدیا',
			'url'   => home_url(),
			'logo'  => array(
				'@type' => 'ImageObject',
				'url'   => get_site_icon_url( 512 ) ?: home_url( '/wp-content/uploads/logo.png' ),
			),
		),
	);

	if ( $image_url ) {
		$schema['image'] = $image_url;
	}

	echo "\n" . '<script type="application/ld+json">' . wp_json_encode( $schema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT ) . '</script>' . "\n";
}

/* ── ۱۸. نقشهٔ سایت اختصاصی مقالات: /quantum-sitemap.xml ── */
add_action( 'init', 'qpedia_register_custom_sitemap_rewrite' );
function qpedia_register_custom_sitemap_rewrite() {
	add_rewrite_rule( '^quantum-sitemap\\.xml$', 'index.php?qpedia_sitemap=1', 'top' );
}

add_filter( 'query_vars', 'qpedia_register_custom_sitemap_query_vars' );
function qpedia_register_custom_sitemap_query_vars( $vars ) {
	$vars[] = 'qpedia_sitemap';
	return $vars;
}

add_action( 'template_redirect', 'qpedia_render_custom_sitemap' );
function qpedia_render_custom_sitemap() {
	if ( get_query_var( 'qpedia_sitemap' ) != 1 ) {
		return;
	}

	$articles = get_posts(
		array(
			'post_type'      => 'quantum_article',
			'post_status'    => 'publish',
			'posts_per_page' => -1,
			'orderby'        => 'modified',
			'order'          => 'DESC',
		)
	);

	header( 'Content-Type: application/xml; charset=utf-8', true, 200 );
	echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
	echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";

	echo "  <url>\n";
	echo '    <loc>' . esc_url( home_url( '/' ) ) . "</loc>\n";
	echo "    <changefreq>daily</changefreq>\n";
	echo "    <priority>1.0</priority>\n";
	echo "  </url>\n";

	foreach ( $articles as $post ) {
		echo "  <url>\n";
		echo '    <loc>' . esc_url( get_permalink( $post->ID ) ) . "</loc>\n";
		echo '    <lastmod>' . esc_html( get_the_modified_date( 'Y-m-d\TH:i:sP', $post->ID ) ) . "</lastmod>\n";
		echo "    <changefreq>weekly</changefreq>\n";
		echo "    <priority>0.8</priority>\n";
		echo "  </url>\n";
	}

	echo '</urlset>' . "\n";
	exit;
}

/* ══════════════════════════════════════════════════════════════
   ۱۹. شورت‌کدهای صفحهٔ اصلی — کنترل کامل از پیشخوان (v1.4.0)
   برگه «صفحه اصلی» فقط از این شورت‌کدها ساخته می‌شود؛ آمار و
   مطالب زنده‌اند و متن/تعداد/ترتیب همه از ویرایشگر برگه عوض می‌شود.
   ══════════════════════════════════════════════════════════════ */

/* ── آمار زنده: [qp_stats items="articles,cats,subs,scientists"] ── */
function qpedia_sc_stats( $atts ) {
	$atts  = shortcode_atts( array( 'items' => 'articles,cats,subs,scientists' ), $atts, 'qp_stats' );
	$items = array_map( 'trim', explode( ',', $atts['items'] ) );

	$out = '<div class="qp-front-hero__stats" aria-label="آمار دانشنامه">';
	foreach ( $items as $item ) {
		if ( 'articles' === $item ) {
			$counts = wp_count_posts( 'quantum_article' );
			$num    = isset( $counts->publish ) ? (int) $counts->publish : 0;
			$label  = 'مقاله';
		} elseif ( 'cats' === $item ) {
			$terms = get_terms( array( 'taxonomy' => 'quantum_category', 'hide_empty' => true, 'parent' => 0 ) );
			$num   = ( ! is_wp_error( $terms ) && ! empty( $terms ) ) ? count( $terms ) : 0;
			$label  = 'دستهٔ اصلی';
		} elseif ( 'subs' === $item ) {
			$terms = get_terms( array( 'taxonomy' => 'quantum_category', 'hide_empty' => true ) );
			$num   = 0;
			if ( ! is_wp_error( $terms ) && ! empty( $terms ) ) {
				foreach ( $terms as $term ) {
					if ( ! empty( $term->parent ) ) {
						$num++;
					}
				}
			}
			$label = 'زیردسته';
		} elseif ( 'scientists' === $item ) {
			$counts = wp_count_posts( 'quantum_scientist' );
			$num    = isset( $counts->publish ) ? (int) $counts->publish : 0;
			$label  = 'دانشمند';
		} else {
			continue;
		}
		$out .= '<div class="qp-front-stat"><span class="qp-front-stat__num">' . esc_html( number_format_i18n( $num ) ) . '</span><span class="qp-front-stat__label">' . esc_html( $label ) . '</span></div>';
	}
	return $out . '</div>';
}
add_shortcode( 'qp_stats', 'qpedia_sc_stats' );

/* ── هیرو: [qp_hero title="..." desc="..."] ── */
function qpedia_sc_hero( $atts ) {
	$a = shortcode_atts(
		array(
			'title' => 'کوانتوم پدیا فارسی',
			'desc'  => 'دانشنامه‌ای دقیق، کاربردی و خوش‌خوان برای یادگیری کوانتوم',
		),
		$atts,
		'qp_hero'
	);

	$out  = '<div class="qp-v2 qp-v2-hero">';
	$out .= '<h1>' . esc_html( $a['title'] ) . '</h1>';
	$out .= '<p>' . esc_html( $a['desc'] ) . '</p>';
	return $out . '</div>';
}
add_shortcode( 'qp_hero', 'qpedia_sc_hero' );

/* ── جست‌وجو: [qp_search] ── */
function qpedia_sc_search() {
	$form = get_search_form( array( 'echo' => false ) );
	return '<div class="qp-front-search">' . $form . '</div>';
}
add_shortcode( 'qp_search', 'qpedia_sc_search' );

/* ── کارت‌های دوقلو: [qp_cta_cards start_title="..." start_desc="..." start_url="..." sci_title="..." sci_desc="..." sci_url="..."] ── */
function qpedia_sc_cta_cards( $atts ) {
	$a = shortcode_atts(
		array(
			'start_title' => 'از کجا شروع کنیم؟',
			'start_desc'  => 'نقشهٔ راه ورود به سایت: ۶۰ ثانیه، ۵ دقیقه یا مسیر کامل — انتخاب با توست.',
			'start_url'   => home_url( '/start/' ),
			'sci_title'   => 'بیوگرافی دانشمندان',
			'sci_desc'    => 'با چهره‌هایی آشنا شو که این علم را ساختند: از پلانک و اینشتین تا بل و فاینمن.',
			'sci_url'     => home_url( '/scientists/' ),
		),
		$atts,
		'qp_cta_cards'
	);

	$out  = '<section class="qp-front-section qp-front-section--cta" aria-label="دو مسیر اصلی"><div class="qp-front-cta-grid">';
	$out .= '<a class="qp-front-cta-card qp-front-cta-card--start" href="' . esc_url( $a['start_url'] ) . '">';
	$out .= '<span class="qp-front-cta-card__icon" aria-hidden="true">01</span>';
	$out .= '<span class="qp-front-cta-card__body"><span class="qp-front-cta-card__title"><strong>' . esc_html( $a['start_title'] ) . '</strong></span>';
	$out .= '<span class="qp-front-cta-card__desc">' . esc_html( $a['start_desc'] ) . '</span></span>';
	$out .= '<span class="qp-front-cta-card__arrow" aria-hidden="true">←</span></a>';
	$out .= '<a class="qp-front-cta-card qp-front-cta-card--scientists" href="' . esc_url( $a['sci_url'] ) . '">';
	$out .= '<span class="qp-front-cta-card__icon" aria-hidden="true">02</span>';
	$out .= '<span class="qp-front-cta-card__body"><span class="qp-front-cta-card__title"><strong>' . esc_html( $a['sci_title'] ) . '</strong></span>';
	$out .= '<span class="qp-front-cta-card__desc">' . esc_html( $a['sci_desc'] ) . '</span></span>';
	$out .= '<span class="qp-front-cta-card__arrow" aria-hidden="true">←</span></a>';
	return $out . '</div></section>';
}
add_shortcode( 'qp_cta_cards', 'qpedia_sc_cta_cards' );

/* ── دسته‌ها (زنده): [qp_cats title="..." num="۰۱" note="" count="7" show_subs="yes"] ── */
function qpedia_sc_cats( $atts ) {
	$a = shortcode_atts(
		array(
			'title'     => 'دسته‌بندی موضوعات',
			'num'       => '۰۱',
			'note'      => '',
			'count'     => 7,
			'show_subs' => 'yes',
		),
		$atts,
		'qp_cats'
	);

	$descriptions = array(
		'fundamentals'        => 'سنگ‌بنای مکانیک کوانتومی؛ از مفاهیم پایه تا ذرات بنیادی.',
		'technology'          => 'از لیزر و GPS تا رایانش و کاربردهای واقعی کوانتوم.',
		'history-experiments' => 'روایت تاریخی نظریه و آزمایش‌هایی که فهم ما را تغییر دادند.',
		'phenomena'           => 'درهم‌تنیدگی، تونل‌زنی و پدیده‌هایی که شهود کلاسیک را می‌شکنند.',
		'mathematics'         => 'زبان ریاضی کوانتوم؛ فضای هیلبرت، عملگرها و معادلات.',
		'interpretations'     => 'خوانش‌های فلسفی و تفسیری از معنای نظریهٔ کوانتوم.',
		'pseudoscience'       => 'مرزبندی علم دقیق با سوءاستفاده‌های بازاری و شبه‌علم.',
	);

	$parents = get_terms(
		array(
			'taxonomy'   => 'quantum_category',
			'hide_empty' => true,
			'parent'     => 0,
			'orderby'    => 'count',
			'order'      => 'DESC',
			'number'     => min( 20, max( 1, absint( $a['count'] ) ) ),
		)
	);

	$note = trim( (string) $a['note'] );
	if ( '' === $note ) {
		$all_terms = get_terms( array( 'taxonomy' => 'quantum_category', 'hide_empty' => true ) );
		$total     = ( ! is_wp_error( $all_terms ) && ! empty( $all_terms ) ) ? count( $all_terms ) : 0;
		$note      = sprintf( '%s دسته و زیرشاخه', number_format_i18n( $total ) );
	}

	$out  = '<section class="qp-v2">';
	$out .= '<div class="qp-v2-head"><h2 class="qp-v2-title"><span class="qp-v2-num">' . esc_html( $a['num'] ) . '</span>' . esc_html( $a['title'] ) . '</h2>';
	$out .= '<p class="qp-v2-note">' . esc_html( $note ) . '</p></div>';

	if ( ! is_wp_error( $parents ) && ! empty( $parents ) ) {
		$out .= '<div class="qp-v2-grid">';
		foreach ( $parents as $cat ) {
			$slug = isset( $cat->slug ) ? $cat->slug : '';
			$out .= '<a class="qp-v2-card" href="' . esc_url( get_term_link( $cat ) ) . '">';
			$out .= '<div class="qp-v2-card__top"><h3 class="qp-v2-card__title">' . esc_html( $cat->name ) . '</h3>';
			$out .= '<span class="qp-v2-card__count">' . esc_html( number_format_i18n( (int) $cat->count ) ) . ' <small>مقاله</small></span></div>';
			if ( isset( $descriptions[ $slug ] ) ) {
				$out .= '<p class="qp-v2-card__desc">' . esc_html( $descriptions[ $slug ] ) . '</p>';
			}
			if ( 'yes' === $a['show_subs'] ) {
				$children = get_terms(
					array(
						'taxonomy'   => 'quantum_category',
						'hide_empty' => true,
						'parent'     => $cat->term_id,
						'number'     => 6,
					)
				);
				if ( ! is_wp_error( $children ) && ! empty( $children ) ) {
					$out .= '<div class="qp-v2-card__subs">';
					foreach ( $children as $child ) {
						$out .= '<span>' . esc_html( $child->name ) . '</span>';
					}
					$out .= '</div>';
				}
			}
			$out .= '</a>';
		}
		$out .= '</div>';
	}
	return $out . '</section>';
}
add_shortcode( 'qp_cats', 'qpedia_sc_cats' );

/* ── مطالب (زنده): [qp_posts title="..." num="۰۲" note="" count="8" orderby="date" order="DESC" category="" exclude="start,شروع" show_excerpt="no"] ──
   orderby: تاریخ date | ویرایش modified | پربحث‌ترین comment_count | تصادفی rand | الفبا title */
function qpedia_sc_posts( $atts ) {
	$a = shortcode_atts(
		array(
			'title'        => 'تازه‌ترین مقاله‌ها',
			'num'          => '۰۲',
			'note'         => '',
			'count'        => 8,
			'orderby'      => 'date',
			'order'        => 'DESC',
			'category'     => '',
			'exclude'      => 'start,شروع',
			'show_excerpt' => 'no',
		),
		$atts,
		'qp_posts'
	);

	$allowed_orderby = array( 'date', 'modified', 'comment_count', 'rand', 'title' );
	$orderby         = in_array( $a['orderby'], $allowed_orderby, true ) ? $a['orderby'] : 'date';
	$order           = ( 'ASC' === strtoupper( $a['order'] ) ) ? 'ASC' : 'DESC';

	$args = array(
		'post_type'              => 'quantum_article',
		'posts_per_page'         => min( 12, max( 1, absint( $a['count'] ) ) ),
		'post_status'            => 'publish',
		'orderby'                => $orderby,
		'order'                  => $order,
		'ignore_sticky_posts'    => true,
		'no_found_rows'          => true,
		'update_post_meta_cache' => false,
	);
	$cat_slugs = array_filter( array_map( 'trim', explode( ',', sanitize_text_field( $a['category'] ) ) ) );
	if ( ! empty( $cat_slugs ) ) {
		$args['tax_query'] = array(
			array(
				'taxonomy' => 'quantum_category',
				'field'    => 'slug',
				'terms'    => $cat_slugs,
			),
		);
	}
	if ( '' !== trim( $a['exclude'] ) ) {
		$not_in = array();
		foreach ( array_map( 'trim', explode( ',', sanitize_text_field( $a['exclude'] ) ) ) as $token ) {
			if ( '' === $token ) {
				continue;
			}
			if ( is_numeric( $token ) ) {
				$not_in[] = absint( $token );
				continue;
			}
			$found = get_page_by_path( $token, OBJECT, 'quantum_article' );
			if ( ! $found instanceof WP_Post ) {
				$found = get_page_by_title( $token, OBJECT, 'quantum_article' );
			}
			if ( $found instanceof WP_Post ) {
				$not_in[] = $found->ID;
			}
		}
		if ( ! empty( $not_in ) ) {
			$args['post__not_in'] = array_unique( array_map( 'absint', $not_in ) );
		}
	}

	$query = new WP_Query( $args );
	$shown = (int) $query->post_count;

	if ( 1 === count( $cat_slugs ) ) {
		$term  = get_term_by( 'slug', $cat_slugs[0], 'quantum_category' );
		$total = ( $term && ! is_wp_error( $term ) ) ? (int) $term->count : 0;
	} else {
		$counts = wp_count_posts( 'quantum_article' );
		$total  = isset( $counts->publish ) ? (int) $counts->publish : 0;
	}

	$note = trim( (string) $a['note'] );
	if ( '' === $note ) {
		$note = sprintf( '%s مورد از %s مقاله', number_format_i18n( $shown ), number_format_i18n( $total ) );
	}

	$out  = '<section class="qp-v2">';
	$out .= '<div class="qp-v2-head"><h2 class="qp-v2-title"><span class="qp-v2-num">' . esc_html( $a['num'] ) . '</span>' . esc_html( $a['title'] ) . '</h2>';
	$out .= '<p class="qp-v2-note">' . esc_html( $note ) . '</p></div>';

	if ( $query->have_posts() ) {
		$out .= '<div class="qp-v2-rows">';
		while ( $query->have_posts() ) {
			$query->the_post();
			$out .= '<a class="qp-v2-row" href="' . esc_url( get_permalink() ) . '">';
			$out .= '<span class="qp-v2-row__main"><span class="qp-v2-row__title">' . esc_html( get_the_title() ) . '</span>';
			if ( 'yes' === $a['show_excerpt'] ) {
				$out .= '<span class="qp-v2-row__excerpt">' . esc_html( get_the_excerpt() ) . '</span>';
			}
			$out .= '</span>';
			$out .= '<span class="qp-v2-row__id">شناسه ' . esc_html( number_format_i18n( get_the_ID() ) ) . '</span>';
			$out .= '</a>';
		}
		$out .= '</div>';
		wp_reset_postdata();
	}
	return $out . '</section>';
}
add_shortcode( 'qp_posts', 'qpedia_sc_posts' );

/* ── دانشمندان (زنده): [qp_scientists title="چهره‌های کوانتوم" num="۰۳" note="" count="6" orderby="date"] ── */
function qpedia_sc_scientists( $atts ) {
	$a = shortcode_atts(
		array(
			'title'   => 'چهره‌های کوانتوم',
			'num'     => '۰۳',
			'note'    => '',
			'count'   => 6,
			'orderby' => 'date',
		),
		$atts,
		'qp_scientists'
	);

	$allowed_orderby = array( 'date', 'modified', 'rand', 'title' );
	$orderby         = in_array( $a['orderby'], $allowed_orderby, true ) ? $a['orderby'] : 'date';

	$query = new WP_Query(
		array(
			'post_type'              => 'quantum_scientist',
			'posts_per_page'         => min( 12, max( 1, absint( $a['count'] ) ) ),
			'post_status'            => 'publish',
			'orderby'                => $orderby,
			'order'                  => 'DESC',
			'ignore_sticky_posts'    => true,
			'no_found_rows'          => true,
			'update_post_meta_cache' => false,
		)
	);

	$counts = wp_count_posts( 'quantum_scientist' );
	$total  = isset( $counts->publish ) ? (int) $counts->publish : 0;

	$note = trim( (string) $a['note'] );
	if ( '' === $note ) {
		$note = sprintf( 'همه %s دانشمند در تالار چهره‌ها', number_format_i18n( $total ) );
	}

	$out  = '<section class="qp-v2">';
	$out .= '<div class="qp-v2-head"><h2 class="qp-v2-title"><span class="qp-v2-num">' . esc_html( $a['num'] ) . '</span>' . esc_html( $a['title'] ) . '</h2>';
	$out .= '<p class="qp-v2-note">' . esc_html( $note ) . '</p></div>';

	if ( $query->have_posts() ) {
		$out .= '<div class="qp-v2-sc-grid">';
		while ( $query->have_posts() ) {
			$query->the_post();
			$en_name = trim( (string) get_post_meta( get_the_ID(), '_scientist_en_name', true ) );
			$out .= '<a class="qp-v2-sc" href="' . esc_url( get_permalink() ) . '">';
			$out .= '<h3 class="qp-v2-sc__name">' . esc_html( get_the_title() ) . '</h3>';
			if ( $en_name ) {
				$out .= '<p class="qp-v2-sc__latin">' . esc_html( $en_name ) . '</p>';
			}
			$excerpt = get_the_excerpt();
			if ( $excerpt ) {
				$out .= '<p class="qp-v2-sc__desc">' . esc_html( wp_trim_words( $excerpt, 15 ) ) . '</p>';
			}
			$out .= '</a>';
		}
		$out .= '</div>';
		wp_reset_postdata();
	}
	return $out . '</section>';
}
add_shortcode( 'qp_scientists', 'qpedia_sc_scientists' );

/* ── نوار پایانی: [qp_band title="..." desc="..." points="الف|ب|ج" btn1_text="..." btn1_url="..." btn2_text="..." btn2_url="..."] ── */
function qpedia_sc_band( $atts ) {
	$a = shortcode_atts(
		array(
			'title'     => 'قول ما به تو',
			'desc'      => 'کوانتوم‌پدیا قرار نیست با کلمهٔ «کوانتوم» سرت کلاه بگذارد؛ قرار است پلهٔ اول فهم عمیق را محکم زیر پایت بگذارد.',
			'points'    => 'بدون بزرگ‌نمایی|با منبع علمی|به زبان ساده',
			'btn1_text' => 'از کجا شروع کنیم؟',
			'btn1_url'  => home_url( '/start/' ),
			'btn2_text' => 'دانشمندان',
			'btn2_url'  => home_url( '/scientists/' ),
		),
		$atts,
		'qp_band'
	);

	$out  = '<div class="qp-guide-cta">';
	$out .= '<h2 class="qp-guide-cta__title">' . esc_html( $a['title'] ) . '</h2>';
	$points = array_filter( array_map( 'trim', explode( '|', $a['points'] ) ) );
	if ( ! empty( $points ) ) {
		$out .= '<ul class="qp-guide-promise">';
		foreach ( $points as $point ) {
			$out .= '<li>' . esc_html( $point ) . '</li>';
		}
		$out .= '</ul>';
	}
	$out .= '<p class="qp-guide-cta__desc">' . esc_html( $a['desc'] ) . '</p>';
	$out .= '<div class="qp-guide-cta__actions"><a class="qp-front-btn qp-front-btn--primary" href="' . esc_url( $a['btn1_url'] ) . '">' . esc_html( $a['btn1_text'] ) . '</a><a class="qp-front-btn qp-front-btn--ghost" href="' . esc_url( $a['btn2_url'] ) . '">' . esc_html( $a['btn2_text'] ) . '</a></div>';
	return $out . '</div>';
}
add_shortcode( 'qp_band', 'qpedia_sc_band' );

/* ══════════════════════════════════════════════════════════════
   ۲۰. بلوک‌های صفحه اصلی — ویرایش دیداری بدون شورت‌کد (v1.5.0)
   سه بلوک داینامیک (هیرو/دسته‌ها/مطالب) که سمت سرور رندر می‌شوند
   و تنظیماتشان (تیتر، تعداد، ترتیب) در ستون کناری ویرایشگر است.
   ══════════════════════════════════════════════════════════════ */
function qpedia_block_atts( $attributes, $keys ) {
	$out = array();
	if ( ! is_array( $attributes ) ) {
		return $out;
	}
	foreach ( $keys as $key ) {
		if ( isset( $attributes[ $key ] ) && '' !== $attributes[ $key ] ) {
			$out[ $key ] = $attributes[ $key ];
		}
	}
	return $out;
}

function qpedia_block_hero( $attributes ) {
	return qpedia_sc_hero( qpedia_block_atts( $attributes, array( 'title', 'desc' ) ) );
}

function qpedia_block_cats( $attributes ) {
	$atts = qpedia_block_atts( $attributes, array( 'title', 'num', 'note', 'count' ) );
	$atts['show_subs'] = ( isset( $attributes['showSubs'] ) && false === $attributes['showSubs'] ) ? 'no' : 'yes';
	return qpedia_sc_cats( $atts );
}

function qpedia_block_posts( $attributes ) {
	$atts = qpedia_block_atts( $attributes, array( 'title', 'num', 'note', 'count', 'orderby' ) );
	$atts['exclude'] = isset( $attributes['exclude'] ) ? $attributes['exclude'] : 'start,شروع';
	$atts['show_excerpt'] = ( isset( $attributes['showExcerpt'] ) && false !== $attributes['showExcerpt'] ) ? 'yes' : 'no';
	return qpedia_sc_posts( $atts );
}

function qpedia_block_scientists( $attributes ) {
	return qpedia_sc_scientists( qpedia_block_atts( $attributes, array( 'title', 'num', 'note', 'count', 'orderby' ) ) );
}

function qpedia_register_blocks() {
	wp_register_script(
		'qpedia-blocks',
		get_stylesheet_directory_uri() . '/assets/js/blocks.js',
		array( 'wp-blocks', 'wp-element', 'wp-block-editor', 'wp-components', 'wp-i18n' ),
		'1.6.0',
		true
	);
	wp_register_style(
		'qpedia-blocks-editor',
		get_stylesheet_directory_uri() . '/assets/css/blocks-editor.css',
		array(),
		'1.6.0'
	);

	register_block_type(
		'qp/hero',
		array(
			'editor_script'   => 'qpedia-blocks',
			'editor_style'    => 'qpedia-blocks-editor',
			'attributes'      => array(
				'title' => array( 'type' => 'string' ),
				'desc'  => array( 'type' => 'string' ),
			),
			'render_callback' => 'qpedia_block_hero',
		)
	);
	register_block_type(
		'qp/cats',
		array(
			'editor_script'   => 'qpedia-blocks',
			'editor_style'    => 'qpedia-blocks-editor',
			'attributes'      => array(
				'title'    => array( 'type' => 'string' ),
				'num'      => array( 'type' => 'string', 'default' => '۰۱' ),
				'note'     => array( 'type' => 'string' ),
				'count'    => array( 'type' => 'number', 'default' => 7 ),
				'showSubs' => array( 'type' => 'boolean', 'default' => true ),
			),
			'render_callback' => 'qpedia_block_cats',
		)
	);
	register_block_type(
		'qp/posts',
		array(
			'editor_script'   => 'qpedia-blocks',
			'editor_style'    => 'qpedia-blocks-editor',
			'attributes'      => array(
				'title'       => array( 'type' => 'string' ),
				'num'         => array( 'type' => 'string', 'default' => '۰۲' ),
				'note'        => array( 'type' => 'string' ),
				'count'       => array( 'type' => 'number', 'default' => 8 ),
				'orderby'     => array( 'type' => 'string', 'default' => 'date' ),
				'exclude'     => array( 'type' => 'string', 'default' => 'start,شروع' ),
				'showExcerpt' => array( 'type' => 'boolean', 'default' => false ),
			),
			'render_callback' => 'qpedia_block_posts',
		)
	);
	register_block_type(
		'qp/scientists',
		array(
			'editor_script'   => 'qpedia-blocks',
			'editor_style'    => 'qpedia-blocks-editor',
			'attributes'      => array(
				'title'   => array( 'type' => 'string' ),
				'num'     => array( 'type' => 'string', 'default' => '۰۳' ),
				'note'    => array( 'type' => 'string' ),
				'count'   => array( 'type' => 'number', 'default' => 6 ),
				'orderby' => array( 'type' => 'string', 'default' => 'date' ),
			),
			'render_callback' => 'qpedia_block_scientists',
		)
	);
}

add_action( 'init', 'qpedia_register_blocks' );

/* ══════════════════════════════════════════════════════════════
   ۲۲. فیکس‌های سئوی فنی (۱۴۰۵/۰۶): canonical سراسری + sitemap + تاکسونومی
   ══════════════════════════════════════════════════════════════ */

/* canonical یکتا برای همهٔ صفحات — تک‌مقاله در بخش ۱۶ پوشش دارد؛ 404 و جست‌وجو canonical نمی‌گیرند */
remove_action( 'wp_head', 'rel_canonical' );
function qpedia_universal_canonical() {
	if ( is_404() || is_search() || is_singular( 'quantum_article' ) ) {
		return;
	}

	$url = '';
	if ( is_front_page() ) {
		$url = home_url( '/' );
	} elseif ( is_home() ) {
		$posts_page = get_option( 'page_for_posts' );
		$url        = $posts_page ? get_permalink( $posts_page ) : home_url( '/' );
	} elseif ( is_post_type_archive() ) {
		$archive_object = get_queried_object();
		$url            = $archive_object ? get_post_type_archive_link( $archive_object->name ) : '';
	} elseif ( is_tax() || is_category() || is_tag() ) {
		$url = get_term_link( get_queried_object() );
	} elseif ( is_singular() ) {
		$url = get_permalink();
	} elseif ( is_author() ) {
		$url = get_author_posts_url( get_queried_object_id() );
	} elseif ( is_date() ) {
		$year  = get_query_var( 'year' );
		$month = get_query_var( 'monthnum' );
		$day   = get_query_var( 'day' );
		if ( $day ) {
			$url = get_day_link( $year, $month, $day );
		} elseif ( $month ) {
			$url = get_month_link( $year, $month );
		} else {
			$url = get_year_link( $year );
		}
	}

	if ( empty( $url ) || is_wp_error( $url ) ) {
		return;
	}

	if ( is_paged() && ( is_archive() || is_home() ) ) {
		$paged_url = get_pagenum_link( get_query_var( 'paged' ) );
		if ( $paged_url && ! is_wp_error( $paged_url ) ) {
			$url = $paged_url;
		}
	}

	echo '<link rel="canonical" href="' . esc_url( $url ) . '">' . "\n";
}
add_action( 'wp_head', 'qpedia_universal_canonical', 1 );

/* ریدایرکت مسیرهای رایج نقشهٔ سایت به نقشهٔ اصلی وردپرس */
function qpedia_redirect_common_sitemap_paths() {
	if ( is_admin() || ! is_404() ) {
		return;
	}

	$request_path = wp_parse_url( wp_unslash( $_SERVER['REQUEST_URI'] ?? '' ), PHP_URL_PATH );
	$request_path = is_string( $request_path ) ? trim( $request_path, '/' ) : '';

	if ( 'sitemap.xml' === $request_path || 'sitemap_index.xml' === $request_path ) {
		wp_safe_redirect( home_url( '/wp-sitemap.xml' ), 301 );
		exit;
	}
}
add_action( 'template_redirect', 'qpedia_redirect_common_sitemap_paths', 1 );

/* معرفی نقشهٔ سایت در robots.txt */
function qpedia_robots_sitemap_line( $output ) {
	$output .= 'Sitemap: ' . esc_url( home_url( '/wp-sitemap.xml' ) ) . "\n";
	return $output;
}
add_filter( 'robots_txt', 'qpedia_robots_sitemap_line' );

/* پنهان نگه‌داشتن تاکسونومی‌های قدیمی/خالی از نقشهٔ سایت و رویهٔ سایت */
function qpedia_hide_retired_taxonomies() {
	global $wp_taxonomies;
	foreach ( array( 'scientist_field', 'article_domain' ) as $taxonomy ) {
		if ( isset( $wp_taxonomies[ $taxonomy ] ) ) {
			$wp_taxonomies[ $taxonomy ]->public             = false;
			$wp_taxonomies[ $taxonomy ]->publicly_queryable = false;
		}
	}
}
add_action( 'init', 'qpedia_hide_retired_taxonomies', 99 );