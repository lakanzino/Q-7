<?php
/**
 * QPedia — نسخهٔ کوچک و کم‌ریسک اصلاح «تازه‌ترین مقاله‌ها»
 *
 * فقط در فایل PHP قرارش دهید (نه در محتوای برگه!):
 *   الف) wp-content/mu-plugins/qpedia-home-fix.php   ← اگر پوشه نیست، بسازید
 *   ب) یا انتهای wp-content/themes/quantum-pedia-child/functions.php
 *      (پیشخوان → ظاهر → ویرایشگر پوسته → فایل functions.php → «پایان» فایل)
 *
 * قبل از ذخیره: مطمئن شوید ویرایشگر «متنی/بصری» نبود؛ نقل‌قول‌ها باید
 * صاف (' و ") باشند، نه موج‌دار (‘ ’ “ ”).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/* ۱) آرشیو برای /topics/ (rewrite دست نمی‌خورد تا پیوندها نشکنند) */
add_filter(
	'register_post_type_args',
	function ( $args, $post_type ) {
		if ( 'quantum_article' === $post_type ) {
			$args['has_archive'] = get_page_by_path( 'topics' ) ? 'articles' : 'topics';
		}
		return $args;
	},
	20,
	2
);

add_action(
	'init',
	function () {
		if ( '1' === get_option( 'qpedia_fix_flushed' ) ) {
			return;
		}
		global $wp_rewrite;
		$wp_rewrite->flush_rules( false );
		update_option( 'qpedia_fix_flushed', '1' );
	},
	99
);

/* ۲) ردیف‌ها را بساز */
function qpedia_recent_rows( $limit = 8 ) {
	$ids  = array();
	global $wpdb;
	$ids  = array_map( 'intval', (array) $wpdb->get_col( $wpdb->prepare( "SELECT ID FROM {$wpdb->posts} WHERE post_type = %s AND post_status = 'publish' AND post_name LIKE '%%-2' LIMIT 200", 'quantum_article' ) ) );

	$posts = get_posts(
		array(
			'post_type'      => 'quantum_article',
			'post_status'    => 'publish',
			'posts_per_page' => (int) $limit,
			'orderby'        => 'date',
			'order'          => 'DESC',
			'exclude'        => $ids,
		)
	);

	$rows = '';
	foreach ( $posts as $post ) {
		$title = trim( wp_strip_all_tags( get_the_title( $post ) ) );
		if ( '' === $title ) {
			$title = '(بی‌عنوان)';
		}
		$rows .= '<a class="qp-article" href="' . esc_url( get_permalink( $post ) ) . '" dir="auto">';
		$rows .= '<span class="qp-article-title">' . esc_html( $title ) . '</span>';
		$rows .= '<span class="qp-article-arrow" aria-hidden="true">⟵</span>';
		$rows .= '</a>';
	}

	if ( '' === $rows ) {
		$rows = '<div class="qp-loading">هنوز مقاله‌ای برای نمایش نیست.</div>';
	}
	return $rows;
}

/* ۳) در همان کانتینر صفحهٔ اصلی بگذار + اسکریپت شکسته را حذف کن */
add_filter(
	'the_content',
	function ( $content ) {
		if ( is_admin() || ! is_front_page() ) {
			return $content;
		}
		$content = (string) $content;
		if ( false === strpos( $content, 'id="qp-articles"' ) ) {
			return $content;
		}

		$rows = qpedia_recent_rows( 8 );

		$out = preg_replace_callback(
			'/<div\b[^>]*class="[^"]*qp-(loading|error)[^"]*"[^>]*>.*?<\/div>/is',
			function () use ( $rows ) {
				return $rows;
			},
			$content,
			1
		);

		$out = preg_replace_callback(
			'/<script\b[^>]*>.*?<\/script>/is',
			function ( $m ) {
				if ( false !== strpos( $m[0], 'qp-articles' ) ) {
					return '';
				}
				return $m[0];
			},
			$out
		);

		return $out;
	},
	99
);

/* ۴) بعد از هر ویرایش مقاله، کش را بزن بیرون */
add_action(
	'save_post_quantum_article',
	function () {
		if ( function_exists( 'litespeed_purge_all' ) ) {
			litespeed_purge_all();
		}
	}
);
