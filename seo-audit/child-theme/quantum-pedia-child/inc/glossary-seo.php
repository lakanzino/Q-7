<?php
/**
 * Glossary module — SEO meta, OpenGraph and structured data.
 *
 * - متا دیسکریپشن و OpenGraph برای مدخل‌ها و آرشیو واژه‌نامه
 * - اسکیمای DefinedTerm + DefinedTermSet + BreadcrumbList + FAQPage
 * - سازگار با Rank Math / Yoast (در صورت فعال بودن، فقط اسکیما را اضافه می‌کند)
 *
 * @package Quantum_Pedia_Child
 */

defined( 'ABSPATH' ) || exit;

/**
 * آیا افزونهٔ سئو فعال است؟
 */
function qpedia_glossary_seo_plugin_active() {
	return defined( 'RANK_MATH_VERSION' ) || defined( 'WPSEO_VERSION' ) || defined( 'AIOSEO_VERSION' ) || defined( 'SEOPRESS_VERSION' );
}

/**
 * متا دیسکریپشن مدخل.
 */
function qpedia_glossary_meta_description( $post_id ) {
	$desc = trim( (string) get_post_meta( $post_id, '_qp_term_meta_desc', true ) );

	if ( '' === $desc ) {
		$short = qpedia_glossary_short_definition( $post_id );
		$en    = qpedia_glossary_en_title( $post_id );
		$title = get_the_title( $post_id );

		$desc = $title . ( '' !== $en ? ' (' . $en . ')' : '' ) . ': ' . $short;
	}

	$desc = wp_strip_all_tags( $desc );

	if ( function_exists( 'mb_strlen' ) && mb_strlen( $desc, 'UTF-8' ) > 300 ) {
		$desc = mb_substr( $desc, 0, 297, 'UTF-8' ) . '…';
	}

	return $desc;
}

/**
 * نشانی آرشیو واژه‌نامه.
 */
function qpedia_glossary_archive_url() {
	$url = get_post_type_archive_link( QPEDIA_GLOSSARY_POST_TYPE );
	return $url ? $url : home_url( '/' . QPEDIA_GLOSSARY_BASE . '/' );
}

/* ──────────────────────────────────────────────────────────────
   ۱. عنوان صفحه
   ────────────────────────────────────────────────────────────── */
add_filter( 'document_title_parts', 'qpedia_glossary_document_title' );
function qpedia_glossary_document_title( $parts ) {
	if ( qpedia_glossary_seo_plugin_active() ) {
		return $parts;
	}

	if ( is_singular( QPEDIA_GLOSSARY_POST_TYPE ) ) {
		$post_id = get_queried_object_id();
		$en      = qpedia_glossary_en_title( $post_id );
		$title   = get_the_title( $post_id );

		$parts['title'] = '' !== $en ? $title . ' (' . $en . ') — تعریف' : $title . ' — تعریف';
		unset( $parts['tagline'] );
		return $parts;
	}

	if ( is_post_type_archive( QPEDIA_GLOSSARY_POST_TYPE ) ) {
		$parts['title'] = 'واژه‌نامهٔ اصطلاحات کوانتومی';
		unset( $parts['tagline'] );
	}

	return $parts;
}

/* ──────────────────────────────────────────────────────────────
   ۲. متا تگ‌ها و OpenGraph
   ────────────────────────────────────────────────────────────── */
add_action( 'wp_head', 'qpedia_glossary_meta_tags', 1 );
function qpedia_glossary_meta_tags() {
	$is_single  = is_singular( QPEDIA_GLOSSARY_POST_TYPE );
	$is_archive = is_post_type_archive( QPEDIA_GLOSSARY_POST_TYPE );

	if ( ! $is_single && ! $is_archive ) {
		return;
	}

	if ( $is_single ) {
		$post_id = get_queried_object_id();

		if ( '1' === (string) get_post_meta( $post_id, '_qp_term_noindex', true ) ) {
			echo '<meta name="robots" content="noindex, follow">' . "\n";
		}

		if ( qpedia_glossary_seo_plugin_active() ) {
			return;
		}

		$desc  = esc_attr( qpedia_glossary_meta_description( $post_id ) );
		$title = esc_attr( get_the_title( $post_id ) . ' | واژه‌نامهٔ کوانتوم پدیا' );
		$url   = esc_url( get_permalink( $post_id ) );
		$image = esc_url( get_the_post_thumbnail_url( $post_id, 'large' ) ? get_the_post_thumbnail_url( $post_id, 'large' ) : get_site_icon_url( 512 ) );

		echo "\n<!-- Qpedia Glossary SEO -->\n";
		echo '<meta name="description" content="' . $desc . '">' . "\n";
		echo '<meta property="og:locale" content="fa_IR">' . "\n";
		echo '<meta property="og:type" content="article">' . "\n";
		echo '<meta property="og:title" content="' . $title . '">' . "\n";
		echo '<meta property="og:description" content="' . $desc . '">' . "\n";
		echo '<meta property="og:url" content="' . $url . '">' . "\n";
		echo '<meta property="og:site_name" content="کوانتوم پدیا">' . "\n";

		if ( $image ) {
			echo '<meta property="og:image" content="' . $image . '">' . "\n";
		}

		echo '<meta name="twitter:card" content="summary_large_image">' . "\n";
		echo '<meta name="twitter:title" content="' . $title . '">' . "\n";
		echo '<meta name="twitter:description" content="' . $desc . '">' . "\n";

		return;
	}

	if ( qpedia_glossary_seo_plugin_active() ) {
		return;
	}

	$count = wp_count_posts( QPEDIA_GLOSSARY_POST_TYPE );
	$total = isset( $count->publish ) ? (int) $count->publish : 0;
	$desc  = esc_attr( sprintf( 'واژه‌نامهٔ کوانتوم پدیا: تعریف دقیق و فارسی %s اصطلاح فیزیک کوانتوم، از برهم‌نهی و درهم‌تنیدگی تا کیوبیت و تصحیح خطای کوانتومی — با معادل انگلیسی و منابع معتبر.', number_format_i18n( $total ) ) );

	echo "\n<!-- Qpedia Glossary Archive SEO -->\n";
	echo '<meta name="description" content="' . $desc . '">' . "\n";
	echo '<meta property="og:locale" content="fa_IR">' . "\n";
	echo '<meta property="og:type" content="website">' . "\n";
	echo '<meta property="og:title" content="' . esc_attr( 'واژه‌نامهٔ اصطلاحات کوانتومی | کوانتوم پدیا' ) . '">' . "\n";
	echo '<meta property="og:description" content="' . $desc . '">' . "\n";
	echo '<meta property="og:url" content="' . esc_url( qpedia_glossary_archive_url() ) . '">' . "\n";
	echo '<meta property="og:site_name" content="کوانتوم پدیا">' . "\n";
}

/* ──────────────────────────────────────────────────────────────
   ۳. تغذیهٔ توضیحات به Rank Math (در صورت نصب)
   ────────────────────────────────────────────────────────────── */
add_filter( 'rank_math/frontend/description', 'qpedia_glossary_rank_math_description' );
function qpedia_glossary_rank_math_description( $description ) {
	if ( is_singular( QPEDIA_GLOSSARY_POST_TYPE ) ) {
		$custom = trim( (string) get_post_meta( get_queried_object_id(), '_qp_term_meta_desc', true ) );
		if ( '' !== $custom ) {
			return $custom;
		}
	}

	return $description;
}

/* ──────────────────────────────────────────────────────────────
   ۴. دادهٔ ساخت‌یافته
   ────────────────────────────────────────────────────────────── */
add_action( 'wp_head', 'qpedia_glossary_schema', 3 );
function qpedia_glossary_schema() {
	$home    = trailingslashit( home_url( '/' ) );
	$archive = qpedia_glossary_archive_url();
	$set_id  = trailingslashit( $archive ) . '#termset';

	if ( is_singular( QPEDIA_GLOSSARY_POST_TYPE ) ) {
		$post_id = get_queried_object_id();
		$post    = get_post( $post_id );

		if ( ! $post instanceof WP_Post ) {
			return;
		}

		$url   = get_permalink( $post_id );
		$en    = qpedia_glossary_en_title( $post_id );
		$short = qpedia_glossary_short_definition( $post_id );
		$abbr  = trim( (string) get_post_meta( $post_id, '_qp_term_abbr', true ) );

		$defined_term = array(
			'@type'            => 'DefinedTerm',
			'@id'              => $url . '#definedterm',
			'name'             => get_the_title( $post_id ),
			'description'      => $short,
			'termCode'         => urldecode( (string) $post->post_name ),
			'url'              => $url,
			'inLanguage'       => 'fa-IR',
			'inDefinedTermSet' => array(
				'@type' => 'DefinedTermSet',
				'@id'   => $set_id,
				'name'  => 'واژه‌نامهٔ اصطلاحات کوانتومی کوانتوم پدیا',
				'url'   => $archive,
			),
		);

		$alternates = array();
		if ( '' !== $en ) {
			$alternates[] = $en;
		}
		if ( '' !== $abbr ) {
			$alternates[] = $abbr;
		}
		if ( ! empty( $alternates ) ) {
			$defined_term['alternateName'] = ( 1 === count( $alternates ) ) ? $alternates[0] : $alternates;
		}

		$web_page = array(
			'@type'            => 'WebPage',
			'@id'              => $url . '#webpage',
			'url'              => $url,
			'name'             => get_the_title( $post_id ),
			'description'      => qpedia_glossary_meta_description( $post_id ),
			'inLanguage'       => 'fa-IR',
			'datePublished'    => get_the_date( 'c', $post_id ),
			'dateModified'     => get_the_modified_date( 'c', $post_id ),
			'isPartOf'         => array( '@id' => $home . '#website' ),
			'mainEntity'       => array( '@id' => $url . '#definedterm' ),
			'breadcrumb'       => array( '@id' => $url . '#breadcrumb' ),
			'potentialAction'  => array(
				'@type'  => 'ReadAction',
				'target' => array( $url ),
			),
		);

		$reviewer = trim( (string) get_post_meta( $post_id, '_qp_term_reviewer', true ) );
		if ( '' !== $reviewer ) {
			$web_page['reviewedBy'] = array(
				'@type' => 'Person',
				'name'  => $reviewer,
			);
		}

		$breadcrumb = array(
			'@type'           => 'BreadcrumbList',
			'@id'             => $url . '#breadcrumb',
			'itemListElement' => array(
				array(
					'@type'    => 'ListItem',
					'position' => 1,
					'name'     => 'خانه',
					'item'     => $home,
				),
				array(
					'@type'    => 'ListItem',
					'position' => 2,
					'name'     => 'واژه‌نامهٔ کوانتوم',
					'item'     => $archive,
				),
				array(
					'@type'    => 'ListItem',
					'position' => 3,
					'name'     => get_the_title( $post_id ),
				),
			),
		);

		$graph = array( $defined_term, $web_page, $breadcrumb );

		$faq = qpedia_glossary_parse_pairs( get_post_meta( $post_id, '_qp_term_faq', true ) );
		if ( ! empty( $faq ) ) {
			$entities = array();
			foreach ( $faq as $row ) {
				if ( '' === $row['value'] ) {
					continue;
				}
				$entities[] = array(
					'@type'          => 'Question',
					'name'           => $row['key'],
					'acceptedAnswer' => array(
						'@type' => 'Answer',
						'text'  => $row['value'],
					),
				);
			}

			if ( ! empty( $entities ) ) {
				$graph[] = array(
					'@type'      => 'FAQPage',
					'@id'        => $url . '#faq',
					'inLanguage' => 'fa-IR',
					'mainEntity' => $entities,
				);
			}
		}

		$schema = array(
			'@context' => 'https://schema.org',
			'@graph'   => $graph,
		);

		echo "\n" . '<script type="application/ld+json">' . wp_json_encode( $schema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) . '</script>' . "\n";
		return;
	}

	if ( ! is_post_type_archive( QPEDIA_GLOSSARY_POST_TYPE ) ) {
		return;
	}

	$entries = qpedia_glossary_get_all_entries();
	$list    = array();
	$terms   = array();
	$index   = 1;

	foreach ( array_slice( $entries, 0, 200 ) as $entry ) {
		$entry_url = get_permalink( $entry );

		$list[] = array(
			'@type'    => 'ListItem',
			'position' => $index,
			'url'      => $entry_url,
			'name'     => $entry->post_title,
		);

		$terms[] = array(
			'@type'       => 'DefinedTerm',
			'@id'         => $entry_url . '#definedterm',
			'name'        => $entry->post_title,
			'description' => qpedia_glossary_short_definition( $entry->ID ),
			'url'         => $entry_url,
		);

		$index++;
	}

	$schema = array(
		'@context' => 'https://schema.org',
		'@graph'   => array(
			array(
				'@type'       => 'DefinedTermSet',
				'@id'         => $set_id,
				'name'        => 'واژه‌نامهٔ اصطلاحات کوانتومی کوانتوم پدیا',
				'description' => 'تعریف فارسی و معادل انگلیسی اصطلاح‌های فیزیک کوانتوم با منابع معتبر.',
				'url'         => $archive,
				'inLanguage'  => 'fa-IR',
				'hasDefinedTerm' => $terms,
			),
			array(
				'@type'           => 'CollectionPage',
				'@id'             => $archive . '#webpage',
				'url'             => $archive,
				'name'            => 'واژه‌نامهٔ اصطلاحات کوانتومی',
				'inLanguage'      => 'fa-IR',
				'isPartOf'        => array( '@id' => $home . '#website' ),
				'mainEntity'      => array( '@id' => $set_id ),
				'breadcrumb'      => array( '@id' => $archive . '#breadcrumb' ),
			),
			array(
				'@type'           => 'BreadcrumbList',
				'@id'             => $archive . '#breadcrumb',
				'itemListElement' => array(
					array(
						'@type'    => 'ListItem',
						'position' => 1,
						'name'     => 'خانه',
						'item'     => $home,
					),
					array(
						'@type'    => 'ListItem',
						'position' => 2,
						'name'     => 'واژه‌نامهٔ کوانتوم',
					),
				),
			),
			array(
				'@type'           => 'ItemList',
				'@id'             => $archive . '#itemlist',
				'numberOfItems'   => count( $list ),
				'itemListElement' => $list,
			),
		),
	);

	echo "\n" . '<script type="application/ld+json">' . wp_json_encode( $schema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) . '</script>' . "\n";
}
