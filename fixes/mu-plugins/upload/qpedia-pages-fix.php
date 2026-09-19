<?php
/**
 * Plugin Name: QPedia — رفع ۴۰۴ شدن برگه‌ها
 * Description: برگه‌ها (خانه /home/، شروع /start/، درباره ما، تماس، حریم خصوصی و هر برگهٔ تازه) را از قاعدهٔ catch-all مقاله‌های کوانتومی نجات می‌دهد و تأکید می‌کند «برگه» خوانده شوند.
 * Version: 2026.09.20-pages4 · Author: QPedia
 *
 * علت خرابی: پوستهٔ فرزند با add_rewrite_rule(..., 'top') هر آدرس تک‌بخشی را به
 * «مقالهٔ کوانتوم» تبدیل می‌کند و لیست استثناها ثابت است ⇒ هر برگهٔ تازه ۴۰۴ می‌شود.
 * /start/ هم جدا از این: برگهٔ 3221 (start) و مقالهٔ 2801 (start) هر دو publish‌اند ⇒
 * یک آدرس، دو صاحب. در این نسخه start و home «صفحه‌محور»ند و برگه برنده است.
 *
 * نصب: جایگزین همین فایل در wp-content/mu-plugins/qpedia-pages-fix.php
 *      بعد: ابزارها → LiteSpeed Cache → Purge All (قوانین rewrite خودکار flush می‌شوند).
 * بازگشت: حذف همین فایل + یک‌بار Settings → Permalinks → Save Changes.
 * جزئیات و فیلترها: README.md کنار همین فایل.
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }
define( 'QPEDIA_PAGES_FIX_VERSION', '2026.09.20-pages4' );

/* ─── ۰) ابزارها ─────────────────────────────────────────────────── */
/** اسلاگ‌هایی که حتماً «برگه»‌اند، حتی اگر مقاله‌ای همان اسلاگ را داشته باشد. */
function qpedia_pages_fix_page_first_slugs() {
	$slugs = (array) apply_filters( 'qpedia_pages_fix_page_first_slugs', array( 'start', 'home' ) );
	return array_values( array_filter( array_unique( array_map( 'sanitize_title', $slugs ) ) ) );
}
/** تور ایمنی: اسلاگ‌هایی که همیشه برگه‌‌اند (حتی با عنوانِ دیگر ساخته شده باشند). */
function qpedia_pages_fix_known_slugs() {
	return (array) apply_filters( 'qpedia_pages_fix_known_slugs', array( 'home', 'front-page', 'start', 'starting', 'start-here', 'about-us', 'about', 'about-me', 'contact-us', 'contact', 'privacy-policy', 'privacy', 'terms', 'terms-of-service', 'rules', 'regulations', 'faq', 'scientists', 'topics', 'articles' ) );
}
/** آدرس‌های جایگزین → اسلاگ مقصد (۳۰۱)؛ '' یعنی صفحهٔ اصلی. می‌توانی خط کم/زیاد کنی. */
function qpedia_pages_fix_alias_map() {
	return (array) apply_filters( 'qpedia_pages_fix_alias_map', array( 'starting' => 'start', 'start-here' => 'start', 'begin' => 'start', 'about' => 'about-us', 'contact' => 'contact-us', 'privacy' => 'privacy-policy', 'front-page' => '' ) );
}
/** نرمال‌سازی عنوان: نیم‌فاصله، ی/ک عربی، فاصله‌های اضافه. */
function qpedia_pages_fix_norm( $text ) {
	$text = wp_strip_all_tags( (string) $text );
	$text = str_replace( '&nbsp;', ' ', $text );
	$text = preg_replace( '/\x{200c}|\x{200b}|\x{feff}/u', ' ', $text );
	$text = str_replace( array( 'ي', 'ك', 'ى', 'آ', 'أ', 'إ', 'ة' ), array( 'ی', 'ک', 'ی', 'ا', 'ا', 'ا', 'ه' ), $text );
	return trim( strtolower( preg_replace( '/\s+/u', ' ', $text ) ) );
}
/** اسلاگ آرشیو مقاله‌ها (همان که qpedia-home-fix.php می‌سازد). */
function qpedia_pages_fix_archive_slug() {
	static $slug = null;
	if ( null !== $slug ) { return $slug; }
	$pt = get_post_type_object( 'quantum_article' );
	$ha = ( $pt && isset( $pt->has_archive ) ) ? $pt->has_archive : false;
	if ( is_string( $ha ) && '' !== $ha ) { $slug = $ha; }
	elseif ( $ha ) { $slug = get_page_by_path( 'topics' ) ? 'articles' : 'topics'; }
	else { $slug = 'topics'; }
	return $slug;
}
/** مسیر واقعی برگه؛ از permalink تا یتیم‌ها (پدرِ در زباله) خراب نشوند. */
function qpedia_pages_fix_path_of( $page ) {
	if ( ! $page instanceof WP_Post ) { return ''; }
	$path = wp_parse_url( (string) get_permalink( $page ), PHP_URL_PATH );
	if ( is_string( $path ) && '' !== trim( $path, '/' ) ) { return trim( $path, '/' ); }
	$path   = (string) $page->post_name;
	$parent = (int) $page->post_parent;
	$guard  = 0;
	while ( $parent > 0 && $guard < 10 ) {
		$guard++;
		$p = get_post( $parent );
		if ( ! $p instanceof WP_Post || 'publish' !== $p->post_status ) { break; }
		$path   = $p->post_name . '/' . $path;
		$parent = (int) $p->post_parent;
	}
	return $path;
}

/* ─── ۱) پنج برگهٔ ثابت همیشه باشند و منتشر باشند ────────────────── */
/** برگه را با «عنوان» پیدا کن؛ برگهٔ هم‌اسلاگ اولویت دارد. */
function qpedia_pages_fix_find_by_titles( $titles, $slug = '' ) {
	$wanted = array();
	foreach ( (array) $titles as $title ) {
		$key = qpedia_pages_fix_norm( $title );
		if ( '' !== $key ) { $wanted[ $key ] = true; }
	}
	if ( empty( $wanted ) ) { return null; }
	$pages = get_posts( array( 'post_type' => 'page', 'post_status' => array( 'publish', 'draft', 'pending', 'private' ), 'posts_per_page' => 300, 'orderby' => 'ID', 'order' => 'ASC', 'no_found_rows' => true, 'update_post_term_cache' => false, 'update_post_meta_cache' => false ) );
	$fallback = null;
	foreach ( $pages as $page ) {
		if ( ! $page instanceof WP_Post ) { continue; }
		if ( '' !== $slug && $page->post_name === $slug && 'publish' === $page->post_status ) { return $page; }
		if ( null === $fallback && isset( $wanted[ qpedia_pages_fix_norm( $page->post_title ) ] ) ) { $fallback = $page; }
	}
	return $fallback;
}
/** اگر برگه‌ای منتشرنشده بود (و در زباله نبود)، منتشرش کن. */
function qpedia_pages_fix_ensure_public( $page ) {
	if ( $page instanceof WP_Post && ! in_array( $page->post_status, array( 'publish', 'trash', 'inherit' ), true ) ) {
		wp_update_post( array( 'ID' => $page->ID, 'post_status' => 'publish' ) );
	}
}
/** هستند ولی پیش‌نویس‌اند ⇒ منتشر · با اسلاگ دیگرند ⇒ دست نمی‌خورند · نیستند ⇒ ساخته می‌شوند. */
function qpedia_pages_fix_ensure_pages() {
	$pages = (array) apply_filters(
		'qpedia_pages_fix_fixed_pages',
		array(
			'home'           => array( 'title' => 'خانه', 'titles' => array( 'خانه', 'صفحه اصلی', 'صفحهٔ اصلی', 'home', 'front page' ) ),
			'start'          => array( 'title' => 'شروع از اینجا', 'titles' => array( 'شروع از اینجا', 'شروع', 'اینجا شروع کن', 'start', 'start here', 'getting started' ) ),
			'about-us'       => array( 'title' => 'درباره ما', 'titles' => array( 'درباره ما', 'دربارهٔ ما', 'about', 'about us' ) ),
			'contact-us'     => array( 'title' => 'تماس با ما', 'titles' => array( 'تماس با ما', 'تماس باما', 'contact', 'contact us' ) ),
			'privacy-policy' => array( 'title' => 'حریم خصوصی و مقررات', 'titles' => array( 'حریم خصوصی و مقررات', 'حریم خصوصی', 'حفظ حریم خصوصی', 'privacy', 'privacy policy' ) ),
		)
	);

	foreach ( $pages as $slug => $meta ) {
		$slug = sanitize_title( $slug );
		if ( '' === $slug || empty( $meta['title'] ) ) { continue; }

		$page = get_page_by_path( $slug, OBJECT, 'page' );
		if ( $page instanceof WP_Post ) { qpedia_pages_fix_ensure_public( $page ); continue; }

		$aliased = qpedia_pages_fix_find_by_titles( isset( $meta['titles'] ) ? $meta['titles'] : array(), $slug );
		if ( $aliased instanceof WP_Post ) { qpedia_pages_fix_ensure_public( $aliased ); continue; }

		wp_insert_post(
			array(
				'post_title'   => $meta['title'],
				'post_name'    => $slug,
				'post_status'  => 'publish',
				'post_type'    => 'page',
				'post_content' => '<!-- QPedia: این برگه خودکار ساخته شد؛ محتوایش را در ویرایشگر بچسبانید. -->',
			)
		);
	}

	// صفحهٔ اصلی تنظیم نشده ولی برگهٔ home هست ⇒ وصلش کن.
	if ( ! (int) get_option( 'page_on_front' ) ) {
		$home = get_page_by_path( 'home', OBJECT, 'page' );
		if ( $home instanceof WP_Post ) {
			update_option( 'show_on_front', 'page' );
			update_option( 'page_on_front', (int) $home->ID );
		}
	}
}
add_action( 'init', 'qpedia_pages_fix_ensure_pages', 18 ); // پیش از قواعد (۱۹) و flush (۹۸).

/* ─── ۱.۵) ترمیم برگهٔ یتیم: پدرِ رفته، آدرس را می‌بلعد ──────────────
 * چرا لازم است: وردپرس برگه را از «مسیر آدرس» پیدا می‌کند (get_page_by_path).
 * اگر post_parent به برگهٔ حذف‌شده/زباله/پیش‌نویس برود، آدرسِ تک‌بخشی مثل
 * /privacy-policy/ هرگز پیدا نمی‌شود ⇒ ۴۰۴، درحالی‌که برگه publish است و
 * متنش سالم. در مقابل get_permalink همان /privacy-policy/ را می‌دهد؛ این
 * دوسو بودگی دقیقاً همان چیزی است که کاربر به‌عنوان «برگهٔ ۴۰۴» می‌بیند.
 * این تابع فقط post_parent را صفر می‌کند — به محتوا، عنوان و اسلاگ دست نمی‌زند.
 * (برگهٔ 2308 حریم خصوصی، پدرِ 2275: منتشرشده نیست.)
 */
function qpedia_pages_fix_unorphan_pages() {
	if ( get_transient( 'qpedia_pages_fix_unorphan_scan' ) ) {
		return array();
	}
	set_transient( 'qpedia_pages_fix_unorphan_scan', 1, 300 ); // هر ۵ دقیقه یک بار

	$ids = get_posts(
		array(
			'post_type'              => 'page',
			'post_status'            => 'publish',
			'posts_per_page'         => 200,
			'post_parent__not_in'    => array( 0 ), // فقط برگه‌هایی که والد دارند
			'fields'                 => 'ids',
			'no_found_rows'          => true,
			'update_post_term_cache' => false,
		)
	);

	$fixed = array();
	foreach ( $ids as $id ) {
		$page   = get_post( $id );
		$parent = $page instanceof WP_Post ? (int) $page->post_parent : 0;
		if ( $parent <= 0 ) { continue; }

		$par = get_post( $parent );
		if ( $par instanceof WP_Post && 'publish' === $par->post_status ) { continue; } // زنجیره سالم است

		$done = wp_update_post( array( 'ID' => (int) $id, 'post_parent' => 0 ), true );
		if ( is_wp_error( $done ) ) { continue; }

		$fixed[] = (int) $id;
	}

	if ( $fixed ) {
		update_option( 'qpedia_pages_fix_unorphaned', $fixed, false );
		flush_rewrite_rules( false );
		if ( function_exists( 'litespeed_purge_all' ) ) { litespeed_purge_all(); }
		error_log( 'QPedia pages-fix: detached orphaned page(s) from a missing parent: ' . implode( ', ', $fixed ) );
	}
	return $fixed;
}
add_action( 'init', 'qpedia_pages_fix_unorphan_pages', 17 );

/* ─── ۲) اصلاح درخواست: اسلاگی که برگه است باید pagename باشد ─────── */
function qpedia_pages_fix_request( $query_vars ) {
	if ( ! is_array( $query_vars ) || empty( $query_vars['quantum_article'] ) ) { return $query_vars; }

	$slug = sanitize_title_for_query( (string) $query_vars['quantum_article'] );
	if ( '' === $slug ) { return $query_vars; }

	// ۱) مقالهٔ واقعی با این اسلاگ هست و اسلاگ صفحه‌محور نیست ⇒ همان درست است.
	$article      = get_page_by_path( $slug, OBJECT, 'quantum_article' );
	$article_ok   = ( $article instanceof WP_Post && 'publish' === $article->post_status );
	$must_be_page = in_array( $slug, qpedia_pages_fix_page_first_slugs(), true );
	if ( $article_ok && ! $must_be_page ) { return $query_vars; }

	// ۲) برگه‌ای با این اسلاگ هست؟ (تخت یا زیرمجموعه‌دار)
	$target = '';
	$page   = get_page_by_path( $slug, OBJECT, 'page' );
	if ( $page instanceof WP_Post && 'publish' === $page->post_status ) {
		$target = qpedia_pages_fix_path_of( $page );
	}

	// ۳) تور ایمنی: شاید برگه با عنوانِ دیگر ساخته شده باشد.
	if ( '' === $target && in_array( $slug, qpedia_pages_fix_known_slugs(), true ) ) {
		$aliased = qpedia_pages_fix_find_by_titles( array( str_replace( '-', ' ', $slug ), $slug ), $slug );
		if ( $aliased instanceof WP_Post && 'publish' === $aliased->post_status ) {
			$target = qpedia_pages_fix_path_of( $aliased );
		}
	}

	// ۴) /topics/ و /articles/ → برگه نیستند، آرشیو مقاله‌ها هستند.
	if ( '' === $target && $slug === qpedia_pages_fix_archive_slug() ) {
		$query_vars['post_type'] = 'quantum_article';
		unset( $query_vars['quantum_article'], $query_vars['pagename'], $query_vars['name'], $query_vars['error'] );
		return $query_vars;
	}

	if ( '' === $target ) { return $query_vars; } // نه مقاله بود نه برگه ⇒ ۴۰۴ طبیعی.

	$query_vars['pagename'] = $target;
	unset( $query_vars['quantum_article'], $query_vars['name'], $query_vars['post_type'], $query_vars['error'] );
	return $query_vars;
}
add_filter( 'request', 'qpedia_pages_fix_request', 5 ); // پیش از فیلتر پوسته (اولویت ۱۰).

/* ─── ۳) قاعدهٔ rewrite برای هر برگه، پیش از catch-all ────────────── */
/** مسیر همهٔ برگه‌های منتشرشده (یک‌بار در هر درخواست). */
function qpedia_pages_fix_page_paths() {
	static $paths = null;
	if ( null !== $paths ) { return $paths; }

	$paths = array();
	$pages = get_posts( array( 'post_type' => 'page', 'post_status' => 'publish', 'posts_per_page' => 300, 'orderby' => 'ID', 'order' => 'ASC', 'no_found_rows' => true, 'update_post_term_cache' => false, 'update_post_meta_cache' => false ) );

	foreach ( $pages as $page ) {
		foreach ( array( qpedia_pages_fix_path_of( $page ), (string) $page->post_name ) as $path ) {
			if ( '' !== $path && ! in_array( $path, $paths, true ) ) { $paths[] = $path; }
		}
	}
	return $paths;
}
/** اسلاگِ برگه‌ای که صفحهٔ اصلی است. */
function qpedia_pages_fix_home_path() {
	static $path = null;
	if ( null !== $path ) { return $path; }
	$id   = (int) get_option( 'page_on_front' );
	$path = $id ? qpedia_pages_fix_path_of( get_post( $id ) ) : '';
	if ( '' === $path ) { $path = 'home'; }
	return $path;
}
/** init ۱۹ → پیش از پوسته (init ۲۰) ⇒ قاعدهٔ ما در گروه top جلوتر از catch-all می‌افتد. */
function qpedia_pages_fix_rewrite_rules() {
	foreach ( qpedia_pages_fix_page_paths() as $path ) {
		add_rewrite_rule( '^' . preg_quote( $path, '/' ) . '/?$', 'index.php?pagename=' . rawurlencode( $path ), 'top' );
	}
	$archive = qpedia_pages_fix_archive_slug();
	add_rewrite_rule( '^' . preg_quote( $archive, '/' ) . '/?$', 'index.php?post_type=quantum_article', 'top' );
}
add_action( 'init', 'qpedia_pages_fix_rewrite_rules', 19 );
/** با عوض‌شدن شمارهٔ نسخه، یک‌بار قوانین تازه می‌شود. */
function qpedia_pages_fix_maybe_flush() {
	if ( get_option( 'qpedia_pages_fix_version' ) !== QPEDIA_PAGES_FIX_VERSION ) {
		flush_rewrite_rules( false );
		update_option( 'qpedia_pages_fix_version', QPEDIA_PAGES_FIX_VERSION, false );
	}
}
add_action( 'init', 'qpedia_pages_fix_maybe_flush', 98 );

/* ─── ۴) ریدایرکت نرم جایگزین‌ها (بدون flush هم کار می‌کند) ───────── */
add_action(
	'template_redirect',
	function () {
		if ( is_admin() || ! isset( $_SERVER['REQUEST_URI'] ) ) { return; }

		$aliases = qpedia_pages_fix_alias_map();
		$path    = trim( (string) wp_parse_url( (string) wp_unslash( $_SERVER['REQUEST_URI'] ), PHP_URL_PATH ), '/' );
		if ( '' === $path || false !== strpos( $path, '/' ) ) { return; } // فقط تک‌بخشی‌ها.

		$slug = sanitize_title( urldecode( $path ) );
		if ( '' === $slug || ! isset( $aliases[ $slug ] ) ) { return; }

		// خودِ همین اسلاگ برگهٔ منتشرشده است ⇒ دست نزن.
		$direct = get_page_by_path( $slug, OBJECT, 'page' );
		if ( $direct instanceof WP_Post && 'publish' === $direct->post_status ) { return; }

		// مقاله‌ای همان اسلاگ را دارد و صفحه‌محور نیست ⇒ دست نزن.
		if ( ! in_array( $slug, qpedia_pages_fix_page_first_slugs(), true ) ) {
			$article = get_page_by_path( $slug, OBJECT, 'quantum_article' );
			if ( $article instanceof WP_Post && 'publish' === $article->post_status ) { return; }
		}

		$dest = (string) $aliases[ $slug ];
		if ( '' === $dest ) {
			if ( ! is_front_page() ) { wp_safe_redirect( home_url( '/' ), 301 ); exit; }
			return;
		}

		$page = get_page_by_path( $dest, OBJECT, 'page' );
		if ( ! $page instanceof WP_Post || 'publish' !== $page->post_status ) { return; }

		// /home/ خودش صفحهٔ اصلی است؛ فقط با فیلترِ پایین به / برمی‌گردد.
		if ( in_array( $slug, array( 'home', 'front-page' ), true ) && (int) get_option( 'page_on_front' ) === (int) $page->ID ) {
			if ( false === apply_filters( 'qpedia_pages_fix_home_redirect', false ) ) { return; }
		}

		wp_safe_redirect( (string) get_permalink( $page ), 301 );
		exit;
	},
	2
);
/**
 * canonicalِ وردپرس /home/ را به / می‌فرستد؛ پیش‌فرض اینجا /home/ همان صفحهٔ اصلی را
 * رندر می‌کند. رفتار استاندارد را خواستی، در mu-plugin دیگری بگذار:
 *   add_filter( 'qpedia_pages_fix_home_redirect', '__return_true' );
 */
add_filter(
	'redirect_canonical',
	function ( $redirect ) {
		if ( ! $redirect || false !== apply_filters( 'qpedia_pages_fix_home_redirect', false ) ) { return $redirect; }

		$home = qpedia_pages_fix_home_path();
		$here = trim( (string) wp_parse_url( (string) ( isset( $_SERVER['REQUEST_URI'] ) ? wp_unslash( $_SERVER['REQUEST_URI'] ) : '' ), PHP_URL_PATH ), '/' );
		if ( '' === $home || $here !== $home ) { return $redirect; }

		global $wp_query;
		if ( ! $wp_query instanceof WP_Query || (int) get_option( 'page_on_front' ) !== (int) $wp_query->get_queried_object_id() ) { return $redirect; }
		if ( untrailingslashit( (string) $redirect ) !== untrailingslashit( home_url( '/' ) ) ) { return $redirect; }

		return false; // /home/ بی‌مزاحم رندر شود.
	},
	20
);

/* ─── ۵) برگرندی اسلاگِ برگه و مقاله ⇒ اطلاعیه + یک کلیک (بدون کلیک: هیچ) ─── */
function qpedia_pages_fix_collisions() {
	static $found = null;
	if ( null !== $found ) { return $found; }

	$found  = array();
	$cached = get_transient( 'qpedia_pages_fix_collisions' );
	if ( is_array( $cached ) ) { $found = $cached; return $found; }

	global $wpdb;
	$rows = (array) $wpdb->get_results(
		$wpdb->prepare(
			"SELECT a.ID AS article_id, a.post_title AS article_title, a.post_name AS slug, p.ID AS page_id, p.post_title AS page_title
			 FROM {$wpdb->posts} a
			 INNER JOIN {$wpdb->posts} p ON p.post_name = a.post_name AND p.post_type = 'page' AND p.post_status = 'publish'
			 WHERE a.post_type = %s AND a.post_status = 'publish'
			 ORDER BY a.post_date DESC LIMIT 50",
			'quantum_article'
		),
		ARRAY_A
	);

	foreach ( $rows as $row ) {
		$found[] = array(
			'article_id'    => (int) $row['article_id'],
			'article_title' => (string) $row['article_title'],
			'page_id'       => (int) $row['page_id'],
			'page_title'    => (string) $row['page_title'],
			'slug'          => (string) $row['slug'],
		);
	}

	set_transient( 'qpedia_pages_fix_collisions', $found, 300 );
	return $found;
}

add_action(
	'admin_notices',
	function () {
		if ( ! current_user_can( 'publish_pages' ) ) { return; }

		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( $screen && isset( $screen->id ) && ! in_array( (string) $screen->id, array( 'dashboard', 'edit-page', 'page', 'edit-quantum_article', 'quantum_article' ), true ) ) { return; }

		$collisions = qpedia_pages_fix_collisions();
		$first      = qpedia_pages_fix_page_first_slugs();

		echo '<div class="notice notice-info"><p><strong>QPedia Pages Fix ' . esc_html( QPEDIA_PAGES_FIX_VERSION ) . '</strong>';
		if ( $first ) { echo ' — اسلاگ‌های صفحه‌محور: <code>' . esc_html( implode( '</code> · <code>', $first ) ) . '</code>.'; }

		if ( empty( $collisions ) ) {
			echo ' برگرندی اسلاگ بین برگه و مقاله نیست.</p></div>';
			return;
		}

		echo ' این اسلاگ‌ها هم برگه‌‌اند و هم مقاله ⇒ برگه را نشان می‌دهیم؛ برای آزادشدن آدرسِ مقاله این را بزن:</p><ul style="margin:0">';
		foreach ( $collisions as $c ) {
			$new = $c['slug'] . '-' . $c['article_id'];
			$url = wp_nonce_url( add_query_arg( array( 'qp_rename' => (int) $c['article_id'], 'qp_to' => $new ), admin_url( 'index.php' ) ), 'qp_rename_' . (int) $c['article_id'], 'qp_nonce' );
			echo '<li style="margin:5px 0">برگهٔ «' . esc_html( $c['page_title'] ) . '» و مقالهٔ «' . esc_html( $c['article_title'] ) . '» هر دو <code>/' . esc_html( $c['slug'] ) . '/</code> '
				. '<a class="button button-small" href="' . esc_url( $url ) . '">اسلاگ مقاله ← ' . esc_html( $new ) . '</a> '
				. '<a class="button button-small" href="' . esc_url( admin_url( 'post.php?post=' . (int) $c['article_id'] . '&action=edit' ) ) . '">خودم ویرایش می‌کنم</a></li>';
		}
		echo '</ul><p>تغییر اسلاگ، نشانی مقاله را عوض می‌کند؛ اگر جای دیگری به آن لینک داده‌اید، همان لینک‌ها را بعداً مرور کنید.</p></div>';
	}
);

add_action(
	'admin_init',
	function () {
		if ( empty( $_GET['qp_rename'] ) || ! current_user_can( 'publish_pages' ) ) { return; }

		$id = (int) $_GET['qp_rename'];
		check_admin_referer( 'qp_rename_' . $id, 'qp_nonce' );

		$to = isset( $_GET['qp_to'] ) ? sanitize_title( wp_unslash( $_GET['qp_to'] ) ) : '';
		if ( '' === $to ) { return; }

		$post = get_post( $id );
		if ( ! $post instanceof WP_Post || 'quantum_article' !== $post->post_type || $post->post_name === $to ) { return; }

		$done = wp_update_post( array( 'ID' => $id, 'post_name' => $to ), true );
		if ( is_wp_error( $done ) ) {
			add_action( 'admin_notices', function () use ( $done ) { echo '<div class="notice notice-error"><p>QPedia Pages Fix: ' . esc_html( $done->get_error_message() ) . '</p></div>'; } );
			return;
		}

		delete_transient( 'qpedia_pages_fix_collisions' );
		delete_transient( 'qpedia_rows_v2' );
		delete_transient( 'qpedia_dupes_v2' );
		if ( function_exists( 'litespeed_purge_all' ) ) { litespeed_purge_all(); }

		wp_safe_redirect( admin_url( 'index.php?qp_renamed=' . $id ) );
		exit;
	}
);
