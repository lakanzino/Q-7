<?php
/**
 * QPedia — اصلاح «تازه‌ترین مقاله‌ها»ٔ صفحهٔ اصلی + آرشیو مقالات
 * نسخه ۱.۱ · ۲۰۲۶-۰۹-۱۸
 *
 * نصب (یکی از سه راه):
 *   ۱) wp-content/mu-plugins/qpedia-home-fix.php            ← پیشنهادی
 *   ۲) انتهای wp-content/themes/quantum-pedia-child/functions.php
 *   ۳) افزونهٔ Code Snippets / WPCode → یک اسنیپت PHP فعال
 *
 * چه چیزی را حل می‌کند (بر اساس داده‌های زندهٔ qpedia.ir):
 *
 * ۱) بخش «تازه‌ترین مقاله‌ها» روی «در حال دریافت…» قفل شده.
 *    دلیل: loader آن یک <script> داخل همان بلوک HTMLِ برگهٔ home است و در
 *    دیتابیس، دو عملگر && به شکل &#038;&#038; ذخیره شده‌اند. داخل <script>
 *    هیچ entity بازگشایی نمی‌شود، پس کل اسکریپت با SyntaxError می‌میرد و هیچ
 *    fetch انجام نمی‌شود. (خودِ endpoint سالم است:
 *    /wp-json/wp/v2/quantum_article داده می‌دهد؛ /wp-json/wp/v2/posts آرایهٔ
 *    خالی می‌دهد چون هیچ نوشته‌ای از نوع post نداریم؛ و fallback آخر به
 *    /topics/ می‌رود که ۴۰۴ است.)
 *    راه‌حل: رندر سمت سرور. بدون JS. اسکریپت شکسته هم از خروجی حذف می‌شود.
 *
 * ۲) نوع پست quantum_article با has_archive=false ثبت شده، پس لینک
 *    «مشاهدهٔ همهٔ مقاله‌ها» می‌میرد. اینجا آرشیو /topics/ فعال می‌شود.
 *
 * ۳) ۷۸ مقاله _thumbnail_id شکسته دارند (اشاره به شناسهٔ نوشته، نه پیوست).
 *
 * ۴) ۲۳ نسخهٔ تکراریِ منتشرشده با اسلاگ «-2» وجود دارد؛ از لیست تازه‌ها
 *    بیرون می‌مانند.
 *
 * قاعدهٔ آینده: داخل بلوک «HTML سفارشی» هرگز && یا || نگذارید؛ اگر لازم شد،
 * if تودرتو بنویسید. وگرنه همین SyntaxError برمی‌گردد.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class QPedia_Home_Fix {

	const VERSION      = '1.1.0';
	const CPT          = 'quantum_article';
	const TAX          = 'quantum_category';
	const ARCHIVE      = 'topics';
	const CACHE_KEY    = 'qpedia_home_recent_v2';
	const DUPES_KEY    = 'qpedia_dupes_v2';
	const CACHE_TTL    = 300;

	public static function boot() {
		add_filter( 'register_post_type_args', array( __CLASS__, 'cpt_args' ), 20, 2 );
		add_filter( 'the_content', array( __CLASS__, 'patch_home' ), 99 );
		add_action( 'init', array( __CLASS__, 'maybe_flush_rules' ), 99 );
		add_action( 'pre_get_posts', array( __CLASS__, 'tune_query' ) );
		add_action( 'rest_api_init', array( __CLASS__, 'rest' ) );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'assets' ) );
		add_action( 'save_post_' . self::CPT, array( __CLASS__, 'bump' ) );
		add_action( 'save_page', array( __CLASS__, 'bump' ) );
		add_action( 'admin_init', array( __CLASS__, 'maybe_repair' ) );
		add_action( 'admin_notices', array( __CLASS__, 'admin_notice' ) );
		add_shortcode( 'qpedia_recent_articles', array( __CLASS__, 'shortcode' ) );
	}

	/** دکمهٔ ترمیم در پیشخوان → لینک آماده با nonce می‌سازد. */
	public static function admin_notice() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( $screen && 'dashboard' !== $screen->id ) {
			return;
		}

		$mk = function ( $mode ) {
			return wp_nonce_url(
				add_query_arg( 'qp_repair', $mode, admin_url( 'index.php' ) ),
				'qp_repair',
				'qp_nonce'
			);
		};

		echo '<div class="notice notice-info"><p><strong>QPedia Home Fix ' . esc_html( self::VERSION ) .
			'</strong> — لیست «تازه‌ترین مقاله‌ها» سمت سرور رندر می‌شود؛ JS صفحهٔ اصلی لازم نیست. ' .
			'<a class="button button-small" href="' . esc_url( $mk( 1 ) ) . '">ترمیم تصویر شاخص شکسته</a> ' .
			'<a class="button button-small" href="' . esc_url( $mk( 2 ) ) . '">ترمیم + به زباله‌دان بردن تکراری‌ها</a></p></div>';
	}

	/* ---------------------------------------------------------------- *
	 * نوع پست: آرشیو + REST   (rewrite را دست نمی‌زنیم تا ۲۰۱ پیوند نشکند)
	 * ---------------------------------------------------------------- */

	public static function cpt_args( $args, $post_type ) {
		if ( self::CPT !== $post_type ) {
			return $args;
		}
		$args['has_archive']         = self::archive_slug();
		$args['publicly_queryable']  = true;
		$args['exclude_from_search'] = false;
		$args['show_in_rest']        = true;
		$args['rest_base']           = self::CPT;
		$args['rest_namespace']      = 'wp/v2';
		return $args;
	}

	public static function archive_slug() {
		static $memo = null;
		if ( is_string( $memo ) ) {
			return $memo;
		}
		$slug = self::ARCHIVE;
		if ( get_page_by_path( $slug ) ) {
			$slug = 'articles';
		}
		$memo = $slug;
		return $memo;
	}

	public static function archive_url() {
		$link = get_post_type_archive_link( self::CPT );
		if ( $link ) {
			return $link;
		}
		return home_url( '/' . trailingslashit( self::archive_slug() ) );
	}

	public static function maybe_flush_rules() {
		global $wp_rewrite;
		if ( get_option( 'qpedia_home_fix_ver' ) === self::VERSION ) {
			return;
		}
		if ( $wp_rewrite instanceof WP_Rewrite ) {
			$wp_rewrite->flush_rules( false );
		}
		update_option( 'qpedia_home_fix_ver', self::VERSION );
	}

	public static function tune_query( $query ) {
		if ( is_admin() || ! $query instanceof WP_Query || ! $query->is_main_query() ) {
			return;
		}
		// صفحهٔ اصلی یک برگه است؛ اگر جایی is_home() اجرا شد و نوشته‌ای نبود، مقالات را بده.
		if ( $query->is_home() && 'post' === $query->get( 'post_type' ) ) {
			$query->set( 'post_type', self::CPT );
		}
		if ( $query->is_post_type_archive( self::CPT ) ) {
			$query->set( 'posts_per_page', 24 );
			$query->set( 'orderby', 'date' );
			$query->set( 'order', 'DESC' );
			$dupes = self::duplicate_ids();
			if ( $dupes ) {
				$query->set( 'post__not_in', $dupes );
			}
		}
	}

	/* ---------------------------------------------------------------- *
	 * تزریق لیست در صفحهٔ اصلی — بدون نیاز به ویرایش برگه
	 * ---------------------------------------------------------------- */

	public static function patch_home( $content ) {
		if ( is_admin() || is_feed() || is_embed() || ! is_front_page() ) {
			return $content;
		}
		if ( ( defined( 'REST_REQUEST' ) && REST_REQUEST ) || ( defined( 'DOING_AJAX' ) && DOING_AJAX ) ) {
			return $content;
		}

		$content = (string) $content;
		if ( false === strpos( $content, 'id="qp-articles"' ) ) {
			return $content;
		}

		$markup = self::rows_html( self::recent_posts( 8 ) );

		// الف) جای «در حال دریافت…» / پیام خطا ← ردیف‌های آماده
		$placed     = false;
		$replaced_a = preg_replace_callback(
			'/<div\b[^>]*class="[^"]*qp-(?:loading|error)[^"]*"[^>]*>.*?<\/div>/is',
			function ( $m ) use ( &$placed, $markup ) {
				if ( $placed ) {
					return '';
				}
				$placed = true;
				return $markup;
			},
			$content,
			1
		);
		if ( is_string( $replaced_a ) ) {
			$content = $replaced_a;
		}

		// ب) اگر placeholder نبود، درست بعد از تگ باز کانتینر درج کن
		if ( ! $placed ) {
			$replaced_b = preg_replace_callback(
				'/<div\b[^>]*id="qp-articles"[^>]*>/i',
				function ( $m ) use ( $markup ) {
					return $m[0] . $markup;
				},
				$content,
				1
			);
			if ( is_string( $replaced_b ) ) {
				$content = $replaced_b;
			}
		}

		// پ) حذف loader شکسته تا کنسول تمیز بماند و لیست را پاک نکند
		$stripped = preg_replace_callback(
			'/<script\b[^>]*>.*?<\/script>/is',
			function ( $m ) {
				return ( false !== strpos( $m[0], 'qp-articles' ) ) ? '' : $m[0];
			},
			$content
		);
		if ( is_string( $stripped ) ) {
			$content = $stripped;
		}

		// ت) لینک مردهٔ «مشاهدهٔ همهٔ مقاله‌ها» → نشانی درست آرشیو
		$archive = esc_url( self::archive_url() );
		$linked  = preg_replace_callback(
			'/(<a\b[^>]*class="[^"]*qp-more[^"]*"[^>]*href=")[^"]*(")/i',
			function ( $m ) use ( $archive ) {
				return $m[1] . $archive . $m[2];
			},
			$content,
			1
		);
		if ( is_string( $linked ) ) {
			$content = $linked;
		}

		return $content . '<!-- qpedia-home-fix ' . esc_html( self::VERSION ) . ' -->';
	}

	/* ---------------------------------------------------------------- *
	 * داده
	 * ---------------------------------------------------------------- */

	public static function recent_posts( $limit = 8 ) {
		$limit = max( 1, min( 24, (int) $limit ) );

		$bucket = get_transient( self::CACHE_KEY );
		if ( is_array( $bucket ) && isset( $bucket[ $limit ] ) && is_array( $bucket[ $limit ] ) && $bucket[ $limit ] ) {
			return $bucket[ $limit ];
		}

		$args = array(
			'post_type'           => self::CPT,
			'post_status'         => 'publish',
			'posts_per_page'      => $limit,
			'orderby'             => 'date',
			'order'               => 'DESC',
			'ignore_sticky_posts' => true,
			'no_found_rows'       => true,
		);
		$dupes = self::duplicate_ids();
		if ( $dupes ) {
			$args['post__not_in'] = $dupes;
		}

		$out = array();
		$q   = new WP_Query( $args );

		foreach ( $q->posts as $post ) {
			$title = trim( wp_strip_all_tags( get_the_title( $post ) ) );
			if ( '' === $title ) {
				$title = '(بی‌عنوان)';
			}

			$cat     = '';
			$catlink = '';
			$terms   = get_the_terms( $post, self::TAX );
			if ( $terms && ! is_wp_error( $terms ) ) {
				$first     = reset( $terms );
				$cat       = $first->name;
				$catlink   = get_term_link( $first );
				if ( is_wp_error( $catlink ) ) {
					$catlink = '';
				}
			}

			$out[] = array(
				'id'    => (int) $post->ID,
				'title' => $title,
				'url'   => get_permalink( $post ),
				'date'  => date_i18n( 'j F Y', strtotime( $post->post_date ) ),
				'cat'   => $cat,
				'catln' => $catlink,
			);
		}

		if ( ! $out ) {
			$out = self::rest_fallback( $limit );
		}
		if ( $out ) {
			$bucket           = is_array( $bucket ) ? $bucket : array();
			$bucket[ $limit ] = $out;
			set_transient( self::CACHE_KEY, $bucket, self::CACHE_TTL );
		}
		return $out;
	}

	/** فالبک: خواندن از REST همین سایت، وقتی کوئری اصلی به هر دلیل خالی برگشت. */
	private static function rest_fallback( $limit ) {
		$url  = add_query_arg(
			array(
				'per_page' => (int) $limit,
				'orderby'  => 'date',
				'order'    => 'desc',
				'_fields'  => 'id,title,link,date',
			),
			rest_url( 'wp/v2/' . self::CPT )
		);
		$raw  = wp_remote_get(
			$url,
			array(
				'timeout'     => 6,
				'redirection' => 0,
				'headers'     => array( 'Accept' => 'application/json' ),
			)
		);
		$out  = array();
		if ( is_wp_error( $raw ) ) {
			return $out;
		}
		$code = (int) wp_remote_retrieve_response_code( $raw );
		if ( $code < 200 || $code > 299 ) {
			return $out;
		}
		$data = json_decode( wp_remote_retrieve_body( $raw ), true );
		if ( ! is_array( $data ) ) {
			return $out;
		}
		foreach ( $data as $item ) {
			$title = '';
			if ( isset( $item['title']['rendered'] ) ) {
				$title = trim( wp_strip_all_tags( html_entity_decode( $item['title']['rendered'], ENT_QUOTES, 'UTF-8' ) ) );
			}
			if ( '' === $title || empty( $item['link'] ) ) {
				continue;
			}
			$out[] = array(
				'id'    => isset( $item['id'] ) ? (int) $item['id'] : 0,
				'title' => $title,
				'url'   => esc_url_raw( $item['link'] ),
				'date'  => isset( $item['date'] ) ? date_i18n( 'j F Y', strtotime( $item['date'] ) ) : '',
				'cat'   => '',
				'catln' => '',
			);
		}
		return $out;
	}

	/** شناسهٔ نسخه‌های تکراری (اسلاگ با پسوند -2). */
	public static function duplicate_ids() {
		static $memo = null;
		if ( is_array( $memo ) ) {
			return $memo;
		}
		$cached = get_transient( self::DUPES_KEY );
		if ( is_array( $cached ) ) {
			$memo = $cached;
			return $memo;
		}
		global $wpdb;
		$sql  = $wpdb->prepare(
			"SELECT ID FROM {$wpdb->posts}
			 WHERE post_type = %s AND post_status = 'publish' AND post_name LIKE '%%-2'
			 ORDER BY post_date DESC LIMIT 200",
			self::CPT
		);
		$rows = array_map( 'intval', (array) $wpdb->get_col( $sql ) );
		set_transient( self::DUPES_KEY, $rows, 600 );
		$memo = $rows;
		return $memo;
	}

	/* ---------------------------------------------------------------- *
	 * نمایش
	 * ---------------------------------------------------------------- */

	public static function rows_html( $items ) {
		if ( ! $items ) {
			return '<div class="qp-loading">هنوز مقاله‌ای برای نمایش نیست.</div>';
		}
		$html = '';
		foreach ( $items as $it ) {
			if ( empty( $it['url'] ) || empty( $it['title'] ) ) {
				continue;
			}
			$html .= '<a class="qp-article" href="' . esc_url( $it['url'] ) . '" dir="auto" title="' . esc_attr( $it['title'] ) . '">';
			$html .= '<span class="qp-article-title">' . esc_html( $it['title'] ) . '</span>';

			$meta = '';
			if ( ! empty( $it['date'] ) ) {
				$meta .= '<span class="qp-article-date">' . esc_html( $it['date'] ) . '</span>';
			}
			if ( ! empty( $it['cat'] ) ) {
				$chip = '<span class="qp-article-cat">' . esc_html( $it['cat'] ) . '</span>';
				if ( ! empty( $it['catln'] ) ) {
					$chip = '<span class="qp-article-cat"><a href="' . esc_url( $it['catln'] ) . '">' . esc_html( $it['cat'] ) . '</a></span>';
				}
				$meta .= $chip;
			}
			if ( '' !== $meta ) {
				$html .= '<span class="qp-article-meta">' . $meta . '</span>';
			}

			$html .= '<span class="qp-article-arrow" aria-hidden="true">⟵</span>';
			$html .= '</a>';
		}
		return $html;
	}

	public static function assets() {
		$css = '
#qpedia-articles{display:grid;gap:9px;}
#qpedia-articles .qp-article{display:flex;align-items:center;justify-content:space-between;gap:14px;
  padding:13px 16px;border:1px solid rgba(0,184,230,.10);border-radius:9px;
  background:rgba(255,255,255,.02);color:#e6f1f7;text-decoration:none;font-size:12.5px;}
#qpedia-articles .qp-article-arrow{flex:none;width:27px;height:27px;display:flex;align-items:center;
  justify-content:center;border-radius:6px;background:rgba(0,184,230,.12);color:#00b8e6;font-size:14px;}
#qpedia-home .qp-article,#qpedia-articles .qp-article{align-items:center;}
#qpedia-home .qp-article-title,#qpedia-articles .qp-article-title{flex:1 1 auto;line-height:1.9;}
#qpedia-home .qp-article-meta,#qpedia-articles .qp-article-meta{flex:0 1 auto;display:inline-flex;align-items:center;gap:10px;white-space:nowrap;}
#qpedia-home .qp-article-date,#qpedia-articles .qp-article-date{font-size:10.5px;color:var(--text-muted,#8a9aa5);}
#qpedia-home .qp-article-cat,#qpedia-articles .qp-article-cat{font-size:10.5px;color:var(--accent,#00b8e6);}
#qpedia-home .qp-article-cat a,#qpedia-articles .qp-article-cat a{color:inherit;text-decoration:none;}
#qpedia-home .qp-article:hover .qp-article-arrow,#qpedia-articles .qp-article:hover .qp-article-arrow{background:var(--accent,#00b8e6);color:#04141c;}
@media(max-width:600px){#qpedia-home .qp-article-meta,#qpedia-articles .qp-article-meta{display:none;}}
';
		wp_register_style( 'qpedia-home-fix', false, array(), self::VERSION );
		wp_enqueue_style( 'qpedia-home-fix' );
		wp_add_inline_style( 'qpedia-home-fix', $css );
	}

	public static function shortcode( $atts ) {
		$a = shortcode_atts(
			array(
				'limit'     => 8,
				'show_date' => 1,
				'show_cat'  => 1,
				'more'      => 1,
			),
			$atts,
			'qpedia_recent_articles'
		);

		$items = self::recent_posts( (int) $a['limit'] );
		if ( ! $a['show_date'] ) {
			foreach ( $items as $k => $v ) {
				$items[ $k ]['date'] = '';
			}
		}
		if ( ! $a['show_cat'] ) {
			foreach ( $items as $k => $v ) {
				$items[ $k ]['cat'] = '';
			}
		}

		$html = '<div class="qp-articles" id="qpedia-articles">' . self::rows_html( $items ) . '</div>';
		if ( $a['more'] ) {
			$html .= '<a class="qp-more" href="' . esc_url( self::archive_url() ) . '">مشاهدهٔ همهٔ مقاله‌ها <span>←</span></a>';
		}
		return $html;
	}

	/* ---------------------------------------------------------------- *
	 * REST: /wp-json/qpedia/v1/recent  (برای تست سریع و مصرف در جاهای دیگر)
	 * ---------------------------------------------------------------- */

	public static function rest() {
		register_rest_route(
			'qpedia/v1',
			'/recent',
			array(
				'methods'             => 'GET',
				'permission_callback' => '__return_true',
				'args'                => array(
					'limit' => array(
						'type'              => 'integer',
						'default'           => 8,
						'minimum'           => 1,
						'maximum'           => 24,
						'sanitize_callback' => 'absint',
					),
				),
				'callback'            => array( __CLASS__, 'rest_recent' ),
			)
		);
		register_rest_route(
			'qpedia/v1',
			'/recent-html',
			array(
				'methods'             => 'GET',
				'permission_callback' => '__return_true',
				'callback'            => array( __CLASS__, 'rest_recent_html' ),
			)
		);
	}

	public static function rest_recent( $request ) {
		$items    = self::recent_posts( (int) $request->get_param( 'limit' ) );
		$response = new WP_REST_Response( array( 'count' => count( $items ), 'items' => $items ), 200 );
		$response->header( 'Cache-Control', 'no-store, max-age=0' );
		return $response;
	}

	public static function rest_recent_html( $request ) {
		$items    = self::recent_posts( (int) $request->get_param( 'limit' ) );
		$response = new WP_REST_Response( array( 'count' => count( $items ), 'html' => self::rows_html( $items ) ), 200 );
		$response->header( 'Cache-Control', 'no-store, max-age=0' );
		return $response;
	}

	public static function bump() {
		delete_transient( self::CACHE_KEY );
		delete_transient( self::DUPES_KEY );
		if ( function_exists( 'litespeed_purge_all' ) ) {
			litespeed_purge_all();
		}
	}

	/* ---------------------------------------------------------------- *
	 * ترمیم یک‌باره: تصویر شاخص شکسته + به زباله‌دان بردن تکراری‌ها
	 * اجرا: qpedia.ir/wp-admin/?qp_repair=1   (فقط برای مدیر، یک‌بار)
	 *   qpedia.ir/wp-admin/?qp_repair=2       → ترمیم + حذف تکراری‌ها
	 * ---------------------------------------------------------------- */

	public static function maybe_repair() {
		if ( empty( $_GET['qp_repair'] ) || ! current_user_can( 'manage_options' ) ) {
			return;
		}
		check_admin_referer( 'qp_repair', 'qp_nonce' );
		$mode = (int) $_GET['qp_repair'];

		global $wpdb;
		$log = array();

		// الف) _thumbnail_id هایی که به پیوست اشاره نمی‌کنند
		$rows = (array) $wpdb->get_results(
			$wpdb->prepare(
				"SELECT pm.post_id, pm.meta_value
				 FROM {$wpdb->postmeta} pm
				 INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
				 WHERE pm.meta_key = '_thumbnail_id' AND p.post_type = %s
				   AND ( pm.meta_value = '' OR pm.meta_value = '0'
				         OR NOT EXISTS ( SELECT 1 FROM {$wpdb->posts} a
				                         WHERE a.ID = pm.meta_value AND a.post_type = 'attachment' ) )",
				self::CPT
			),
			ARRAY_A
		);

		$relinked = 0;
		$cleared  = 0;
		foreach ( $rows as $row ) {
			$pid = (int) $row['post_id'];
			$att = (int) $wpdb->get_var(
				$wpdb->prepare(
					"SELECT ID FROM {$wpdb->posts}
					 WHERE post_parent = %d AND post_type = 'attachment' AND post_status = 'inherit'
					 ORDER BY menu_order ASC, ID ASC LIMIT 1",
					$pid
				)
			);
			if ( $att ) {
				update_post_meta( $pid, '_thumbnail_id', $att );
				$relinked++;
			} else {
				delete_post_meta( $pid, '_thumbnail_id' );
				$cleared++;
			}
		}
		$log[] = 'تصویر شاخص: ' . count( $rows ) . ' مورد بررسی · ' . $relinked . ' اتصال مجدد · ' . $cleared . ' حذف meta شکسته';

		// ب) تکراری‌ها
		if ( 2 === $mode ) {
			require_once ABSPATH . 'wp-admin/includes/post.php';
			$trashed = 0;
			foreach ( self::duplicate_ids() as $id ) {
				wp_trash_post( $id );
				$trashed++;
			}
			$log[] = 'تکراری‌ها: ' . $trashed . ' نسخه به زباله‌دان رفت';
			delete_transient( self::DUPES_KEY );
		}

		self::bump();
		wp_die(
			'<h2>QPedia Home Fix — ترمیم انجام شد</h2><ul><li>' .
			implode( '</li><li>', array_map( 'esc_html', $log ) ) .
			'</li></ul><p><a href="' . esc_url( home_url( '/' ) ) . '">رفتن به صفحهٔ اصلی</a></p>',
			'QPedia Home Fix',
			array( 'response' => 200 )
		);
	}
}

QPedia_Home_Fix::boot();
