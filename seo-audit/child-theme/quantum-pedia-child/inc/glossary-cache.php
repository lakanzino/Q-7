<?php
/**
 * Glossary module — cached term dictionary.
 *
 * نسخهٔ ۲: افزودن نام انگلیسی و مخفف به صورت‌های قابل تشخیص + کش نسخه‌دار.
 *
 * @package Quantum_Pedia_Child
 */

defined( 'ABSPATH' ) || exit;

if ( ! defined( 'QPEDIA_GLOSSARY_CACHE_KEY' ) ) {
	define( 'QPEDIA_GLOSSARY_CACHE_KEY', 'qpedia_glossary_terms_v2' );
}

/**
 * پاک‌سازی کش واژه‌نامه.
 */
function qpedia_glossary_clear_cache() {
	delete_transient( QPEDIA_GLOSSARY_CACHE_KEY );
	delete_transient( 'qpedia_glossary_terms_v1' );
	delete_transient( 'qpedia_glossary_archive_index_v1' );
}

foreach ( array( 'save_post_qp_glossary', 'deleted_post', 'trashed_post', 'untrashed_post' ) as $qpedia_glossary_hook ) {
	add_action( $qpedia_glossary_hook, 'qpedia_glossary_clear_cache' );
}
unset( $qpedia_glossary_hook );

/**
 * فهرست اصطلاح‌ها برای تشخیص خودکار در متن.
 *
 * هر ردیف: term (صورت نوشتاری)، definition، url، id، title، en
 *
 * @return array
 */
function qpedia_glossary_get_terms() {
	$cached = get_transient( QPEDIA_GLOSSARY_CACHE_KEY );
	if ( is_array( $cached ) ) {
		return $cached;
	}

	$posts = get_posts(
		array(
			'post_type'              => QPEDIA_GLOSSARY_POST_TYPE,
			'post_status'            => 'publish',
			'posts_per_page'         => 500,
			'orderby'                => 'title',
			'order'                  => 'ASC',
			'no_found_rows'          => true,
			'update_post_term_cache' => false,
		)
	);

	$terms = array();

	foreach ( $posts as $post ) {
		$definition = trim( wp_strip_all_tags( qpedia_glossary_short_definition( $post->ID ), true ) );
		if ( '' === $definition ) {
			continue;
		}

		$en    = qpedia_glossary_en_title( $post->ID );
		$abbr  = trim( (string) get_post_meta( $post->ID, '_qp_term_abbr', true ) );
		$forms = array( $post->post_title );

		if ( '' !== $en ) {
			$forms[] = $en;
		}

		if ( '' !== $abbr && ( function_exists( 'mb_strlen' ) ? mb_strlen( $abbr, 'UTF-8' ) : strlen( $abbr ) ) >= 3 ) {
			$forms[] = $abbr;
		}

		$aliases = preg_split( '/\R/u', (string) get_post_meta( $post->ID, '_qpedia_glossary_aliases', true ) );
		if ( is_array( $aliases ) ) {
			foreach ( $aliases as $alias ) {
				$alias = trim( (string) $alias );
				if ( '' !== $alias ) {
					$forms[] = $alias;
				}
			}
		}

		$url = get_permalink( $post );

		foreach ( array_unique( $forms ) as $form ) {
			$length = function_exists( 'mb_strlen' ) ? mb_strlen( $form, 'UTF-8' ) : strlen( $form );
			if ( $length < 3 ) {
				continue;
			}

			$terms[] = array(
				'term'       => $form,
				'definition' => $definition,
				'url'        => $url,
				'id'         => (int) $post->ID,
				'title'      => $post->post_title,
				'en'         => $en,
			);
		}
	}

	// طولانی‌ترین صورت‌ها اول بررسی شوند تا «برهم‌نهی کوانتومی» پیش از «برهم‌نهی» بگیرد.
	usort(
		$terms,
		function ( $a, $b ) {
			$al = function_exists( 'mb_strlen' ) ? mb_strlen( $a['term'], 'UTF-8' ) : strlen( $a['term'] );
			$bl = function_exists( 'mb_strlen' ) ? mb_strlen( $b['term'], 'UTF-8' ) : strlen( $b['term'] );
			return $bl <=> $al;
		}
	);

	set_transient( QPEDIA_GLOSSARY_CACHE_KEY, $terms, DAY_IN_SECONDS );

	return $terms;
}

/**
 * همهٔ مدخل‌های منتشرشده — برای آرشیو، شورت‌کد و اسکیما.
 *
 * @return WP_Post[]
 */
function qpedia_glossary_get_all_entries() {
	static $entries = null;

	if ( null !== $entries ) {
		return $entries;
	}

	$entries = get_posts(
		array(
			'post_type'              => QPEDIA_GLOSSARY_POST_TYPE,
			'post_status'            => 'publish',
			'posts_per_page'         => 500,
			'orderby'                => 'title',
			'order'                  => 'ASC',
			'no_found_rows'          => true,
			'update_post_term_cache' => false,
		)
	);

	return $entries;
}

/**
 * حرف نخست فارسی یا لاتین برای فیلتر الفبایی.
 *
 * @param WP_Post $post مدخل.
 * @return array آرایه‌ای شامل fa و en
 */
function qpedia_glossary_initials( $post ) {
	$fa_title = trim( (string) $post->post_title );
	$en_title = qpedia_glossary_en_title( $post->ID );

	$fa = '';
	if ( '' !== $fa_title ) {
		$fa = function_exists( 'mb_substr' ) ? mb_substr( $fa_title, 0, 1, 'UTF-8' ) : substr( $fa_title, 0, 1 );
		if ( 'آ' === $fa || 'أ' === $fa || 'إ' === $fa ) {
			$fa = 'ا';
		}
	}

	$en = '';
	if ( '' !== $en_title ) {
		$en = strtoupper( substr( $en_title, 0, 1 ) );
		if ( ! preg_match( '/^[A-Z]$/', $en ) ) {
			$en = '';
		}
	}

	return array(
		'fa' => $fa,
		'en' => $en,
	);
}
