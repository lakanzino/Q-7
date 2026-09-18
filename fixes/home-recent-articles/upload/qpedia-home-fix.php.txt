<?php
/**
 * QPedia — اصلاح «تازه‌ترین مقاله‌ها» در صفحهٔ اصلی (رندر سمت سرور، بدون JS)
 * نسخه ۱.۱ · ۲۰۲۶-۰۹-۱۸
 *
 * نصب (یکی از سه راه):
 *   ۱) wp-content/mu-plugins/qpedia-home-fix.php        ← پیشنهادی
 *   ۲) انتهای wp-content/themes/quantum-pedia-child/functions.php
 *   ۳) افزونهٔ Code Snippets / WPCode → یک اسنیپت PHP فعال
 * پس از نصب: LiteSpeed Cache → Purge All (تا HTML کش‌شدهٔ قدیمی برود).
 *
 * خلاصهٔ علت خرابی: در بلوک HTMLِ برگهٔ home، عملگر && به شکل &#038;&#038; ذخیره
 * شده؛ داخل <script> هیچ entity بازگشایی نمی‌شود، پس loader با SyntaxError می‌میرد
 * و «در حال دریافت…» می‌ماند. این فایل لیست را سمت سرور رندر می‌کند، همان
 * اسکریپت شکسته را از خروجی برمی‌دارد و آرشیو /topics/ را می‌سازد.
 * توضیح کامل + ابزار ترمیم تصویر شاخص: README.md کنار همین فایل.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/* ------------------------------------------------------------------ *
 * ۱) نوع پست: آرشیو + REST
 *    rewrite را دست نمی‌زنیم؛ پیوندهای موجود مقاله به شکل /slug/ هستند و
 *    تغییر slug همهٔ ۲۰۱ نشانی را می‌شکند.
 * ------------------------------------------------------------------ */
add_filter(
	'register_post_type_args',
	function ( $args, $post_type ) {
		if ( 'quantum_article' !== $post_type ) {
			return $args;
		}

		static $slug = null;
		if ( null === $slug ) {
			$slug = get_page_by_path( 'topics' ) ? 'articles' : 'topics';
		}

		$args['has_archive']         = $slug;
		$args['publicly_queryable']  = true;
		$args['exclude_from_search'] = false;
		$args['show_in_rest']        = true;
		$args['rest_base']           = 'quantum_article';
		$args['rest_namespace']      = 'wp/v2';

		return $args;
	},
	20,
	2
);

/* یک‌بار rebuild برای پیوندهای یکتا، تا /topics/ واقعی شود */
add_action(
	'init',
	function () {
		if ( 'done' === get_option( 'qpedia_home_fix_flush' ) ) {
			return;
		}
		global $wp_rewrite;
		if ( $wp_rewrite instanceof WP_Rewrite ) {
			$wp_rewrite->flush_rules( false );
		}
		update_option( 'qpedia_home_fix_flush', 'done' );
	},
	99
);

/* ------------------------------------------------------------------ *
 * ۲) ساخت لیست «تازه‌ترین مقاله‌ها»
 * ------------------------------------------------------------------ */
if ( ! function_exists( 'qpedia_recent_rows' ) ) {
	/**
	 * @param int  $limit     تعداد ردیف
	 * @param bool $with_date نمایش تاریخ شمسی/میلادی در انتهای ردیف
	 * @return string HTML ردیف‌ها
	 */
	function qpedia_recent_rows( $limit = 8, $with_date = true ) {
		$limit  = max( 1, min( 24, (int) $limit ) );
		$ckey   = $limit . ( $with_date ? ':d' : ':n' );
		$bucket = get_transient( 'qpedia_rows_v2' );

		if ( is_array( $bucket ) && isset( $bucket[ $ckey ] ) && is_string( $bucket[ $ckey ] ) && '' !== $bucket[ $ckey ] ) {
			return $bucket[ $ckey ];
		}

		/* نسخه‌های تکراریِ ایمپورت (اسلاگ با پسوند -2) از لیست بیرون می‌مانند */
		$dupes = get_transient( 'qpedia_dupes_v2' );
		if ( ! is_array( $dupes ) ) {
			global $wpdb;
			$dupes = array_map(
				'intval',
				(array) $wpdb->get_col(
					$wpdb->prepare(
						"SELECT ID FROM {$wpdb->posts}
						 WHERE post_type = %s AND post_status = 'publish' AND post_name LIKE '%%-2'
						 ORDER BY post_date DESC LIMIT 200",
						'quantum_article'
					)
				)
			);
			set_transient( 'qpedia_dupes_v2', $dupes, 600 );
		}

		$args = array(
			'post_type'           => 'quantum_article',
			'post_status'         => 'publish',
			'posts_per_page'      => $limit,
			'orderby'             => 'date',
			'order'               => 'DESC',
			'ignore_sticky_posts' => true,
			'no_found_rows'       => true,
		);
		if ( $dupes ) {
			$args['post__not_in'] = $dupes;
		}

		$query = new WP_Query( $args );
		$rows  = '';

		foreach ( $query->posts as $post ) {
			$title = trim( wp_strip_all_tags( get_the_title( $post ) ) );
			if ( '' === $title ) {
				$title = '(بی‌عنوان)';
			}

			$meta = '';
			if ( $with_date ) {
				$stamp = date_i18n( 'j F Y', strtotime( $post->post_date ) );
				$meta  = '<span class="qp-article-meta"><span class="qp-article-date">' . esc_html( $stamp ) . '</span></span>';
			}

			$rows .= '<a class="qp-article" href="' . esc_url( get_permalink( $post ) ) . '" dir="auto" title="' . esc_attr( $title ) . '">'
				. '<span class="qp-article-title">' . esc_html( $title ) . '</span>'
				. $meta
				. '<span class="qp-article-arrow" aria-hidden="true">⟵</span>'
				. '</a>';
		}

		if ( '' === $rows ) {
			$rows = '<div class="qp-loading">هنوز مقاله‌ای برای نمایش نیست.</div>';
		}

		if ( ! is_array( $bucket ) ) {
			$bucket = array();
		}
		$bucket[ $ckey ] = $rows;
		set_transient( 'qpedia_rows_v2', $bucket, 300 );

		return $rows;
	}
}

/* ------------------------------------------------------------------ *
 * ۳) تزریق در صفحهٔ اصلی + حذف اسکریپت شکسته — بدون ویرایش برگه
 * ------------------------------------------------------------------ */
add_filter(
	'the_content',
	function ( $content ) {
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

		$rows   = qpedia_recent_rows( 8 );
		$placed = false;

		/* الف) جای «در حال دریافت…» (یا پیام خطا) ← ردیف‌های آماده */
		$swapped = preg_replace_callback(
			'/<div\b[^>]*class="[^"]*qp-(?:loading|error)[^"]*"[^>]*>.*?<\/div>/is',
			function ( $m ) use ( &$placed, $rows ) {
				if ( $placed ) {
					return '';
				}
				$placed = true;
				return $rows;
			},
			$content,
			1
		);
		if ( is_string( $swapped ) ) {
			$content = $swapped;
		}

		/* ب) اگر placeholder نبود، درست بعد از تگ باز کانتینر درج کن */
		if ( ! $placed ) {
			$injected = preg_replace_callback(
				'/<div\b[^>]*id="qp-articles"[^>]*>/i',
				function ( $m ) use ( $rows ) {
					return $m[0] . $rows;
				},
				$content,
				1
			);
			if ( is_string( $injected ) ) {
				$content = $injected;
			}
		}

		/* پ) حذف loader شکسته، تا کنسول تمیز بماند و لیست را پاک نکند */
		$stripped = preg_replace_callback(
			'/<script\b[^>]*>.*?<\/script>/is',
			function ( $m ) {
				if ( false !== strpos( $m[0], 'qp-articles' ) ) {
					return '';
				}
				return $m[0];
			},
			$content
		);
		if ( is_string( $stripped ) ) {
			$content = $stripped;
		}

		/* ت) لینک «مشاهدهٔ همهٔ مقاله‌ها» را به نشانی درست آرشیو وصل کن */
		$archive = get_post_type_archive_link( 'quantum_article' );
		if ( $archive ) {
			$linked = preg_replace_callback(
				'/(<a\b[^>]*class="[^"]*qp-more[^"]*"[^>]*href=")[^"]*(")/i',
				function ( $m ) use ( $archive ) {
					return $m[1] . esc_url( $archive ) . $m[2];
				},
				$content,
				1
			);
			if ( is_string( $linked ) ) {
				$content = $linked;
			}
		}

		return $content . '<!-- qpedia-home-fix 1.1 -->';
	},
	99
);

/* ------------------------------------------------------------------ *
 * ۴) شورت‌کد — برای وقتی که بلوک JS را از برگه پاک می‌کنید
 *    [qpedia_recent_articles limit="8" date="1"]
 * ------------------------------------------------------------------ */
add_shortcode(
	'qpedia_recent_articles',
	function ( $atts ) {
		$a = shortcode_atts(
			array(
				'limit' => 8,
				'date'  => 1,
			),
			$atts,
			'qpedia_recent_articles'
		);

		$html = '<div class="qp-articles" id="qpedia-articles">'
			. qpedia_recent_rows( (int) $a['limit'], (bool) $a['date'] )
			. '</div>';

		$archive = get_post_type_archive_link( 'quantum_article' );
		if ( $archive ) {
			$html .= '<a class="qp-more" href="' . esc_url( $archive ) . '">مشاهدهٔ همهٔ مقاله‌ها <span>←</span></a>';
		}

		return $html;
	}
);

/* ------------------------------------------------------------------ *
 * ۵) استایل مکمل (هم‌رنگ پالت خودِ صفحه؛ اگر بیرون از #qpedia-home استفاده شد
 *    هم درست دیده می‌شود)
 * ------------------------------------------------------------------ */
add_action(
	'wp_enqueue_scripts',
	function () {
		wp_register_style( 'qpedia-home-fix', false, array(), '1.1' );
		wp_enqueue_style( 'qpedia-home-fix' );
		wp_add_inline_style(
			'qpedia-home-fix',
			'#qpedia-articles{display:grid;gap:9px;}'
			. '#qpedia-home .qp-article-meta,#qpedia-articles .qp-article-meta{display:inline-flex;gap:10px;flex:none;white-space:nowrap;}'
			. '#qpedia-home .qp-article-date,#qpedia-articles .qp-article-date{font-size:10.5px;color:var(--text-muted,#8a9aa5);}'
			. '#qpedia-articles .qp-article{display:flex;align-items:center;justify-content:space-between;gap:14px;padding:13px 16px;'
			. 'border:1px solid rgba(0,184,230,.10);border-radius:9px;background:rgba(255,255,255,.02);color:#e6f1f7;text-decoration:none;font-size:12.5px;}'
			. '#qpedia-articles .qp-article-arrow{flex:none;width:27px;height:27px;display:flex;align-items:center;justify-content:center;'
			. 'border-radius:6px;background:rgba(0,184,230,.12);color:var(--accent,#00b8e6);font-size:14px;}'
			. '@media(max-width:600px){#qpedia-home .qp-article-meta,#qpedia-articles .qp-article-meta{display:none;}}'
		);
	}
);

/* ------------------------------------------------------------------ *
 * ۶) بعد از هر انتشار/ویرایش مقاله: کش لیست و کش صفحهٔ LiteSpeed پاک شود
 * ------------------------------------------------------------------ */
add_action(
	'save_post_quantum_article',
	function () {
		delete_transient( 'qpedia_rows_v2' );
		delete_transient( 'qpedia_dupes_v2' );
		if ( function_exists( 'litespeed_purge_all' ) ) {
			litespeed_purge_all();
		}
	}
);

/* ------------------------------------------------------------------ *
 * ۷) (اختیاری) ترمیم تصویر شاخص شکسته
 *    ۷۸ مقاله _thumbnail_id دارند که به شناسهٔ یک «نوشته» اشاره می‌کند نه یک
 *    پیوست (نگاشت غلطِ ایمپورت). اجرا فقط برای مدیر، با لینک امنِ پیشخوان:
 *      qpedia.ir/wp-admin/?qp_repair=1            → اتصال/پاک‌کردن thumbnail
 *      qpedia.ir/wp-admin/?qp_repair=2            → به‌علاوه: به زباله‌دان بردن
 *                                                   نسخه‌های تکراری (-2)
 *    لینکِ آماده با nonce در داشبورد به‌عنوان اطلاعیه نمایش داده می‌شود.
 * ------------------------------------------------------------------ */
add_action(
	'admin_notices',
	function () {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( $screen && isset( $screen->id ) && 'dashboard' !== $screen->id ) {
			return;
		}
		$mk = function ( $mode ) {
			return wp_nonce_url( add_query_arg( 'qp_repair', $mode, admin_url( 'index.php' ) ), 'qp_repair', 'qp_nonce' );
		};
		echo '<div class="notice notice-info"><p><strong>QPedia Home Fix 1.1</strong> فعال است — '
			. 'لیست «تازه‌ترین مقاله‌ها» سمت سرور رندر می‌شود. '
			. '<a class="button button-small" href="' . esc_url( $mk( 1 ) ) . '">ترمیم تصویر شاخص شکسته</a> '
			. '<a class="button button-small" href="' . esc_url( $mk( 2 ) ) . '">ترمیم + حذف تکراری‌ها</a></p></div>';
	}
);

add_action(
	'admin_init',
	function () {
		if ( empty( $_GET['qp_repair'] ) || ! current_user_can( 'manage_options' ) ) {
			return;
		}
		check_admin_referer( 'qp_repair', 'qp_nonce' );
		$mode = isset( $_GET['qp_repair'] ) ? (int) $_GET['qp_repair'] : 0;

		global $wpdb;
		$log = array();

		$rows = (array) $wpdb->get_results(
			$wpdb->prepare(
				"SELECT pm.post_id
				 FROM {$wpdb->postmeta} pm
				 INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
				 WHERE pm.meta_key = '_thumbnail_id' AND p.post_type = %s
				   AND ( pm.meta_value = '' OR pm.meta_value = '0'
				         OR NOT EXISTS ( SELECT 1 FROM {$wpdb->posts} a
				                         WHERE a.ID = pm.meta_value AND a.post_type = 'attachment' ) )",
				'quantum_article'
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

		if ( 2 === $mode ) {
			require_once ABSPATH . 'wp-admin/includes/post.php';
			$trashed = 0;
			$ids     = array_map( 'intval', (array) $wpdb->get_col(
				$wpdb->prepare(
					"SELECT ID FROM {$wpdb->posts} WHERE post_type = %s AND post_status = 'publish' AND post_name LIKE '%%-2' ORDER BY post_date DESC LIMIT 200",
					'quantum_article'
				)
			) );
			foreach ( $ids as $id ) {
				wp_trash_post( $id );
				$trashed++;
			}
			$log[] = 'تکراری‌ها: ' . $trashed . ' نسخه به زباله‌دان منتقل شد';
		}

		delete_transient( 'qpedia_rows_v2' );
		delete_transient( 'qpedia_dupes_v2' );
		if ( function_exists( 'litespeed_purge_all' ) ) {
			litespeed_purge_all();
		}

		wp_die(
			'<h2>QPedia Home Fix — ترمیم انجام شد</h2><ul><li>'
			. implode( '</li><li>', array_map( 'esc_html', $log ) )
			. '</li></ul><p><a href="' . esc_url( home_url( '/' ) ) . '">رفتن به صفحهٔ اصلی</a></p>',
			'QPedia Home Fix',
			array( 'response' => 200 )
		);
	}
);
