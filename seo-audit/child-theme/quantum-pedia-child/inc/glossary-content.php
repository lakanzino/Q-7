<?php
/**
 * Glossary module — display-time linking, tooltips and shortcodes.
 *
 * نسخهٔ ۲:
 * - اصطلاح‌ها به‌جای <button> با <a href> رندر می‌شوند → لینک داخلی واقعی و خزش‌پذیر
 * - اجرا روی مقاله‌ها، صفحهٔ دانشمندان و خود مدخل‌های واژه‌نامه
 * - شورت‌کدهای [qterm]، [qglossary] و [qp_terms]
 *
 * @package Quantum_Pedia_Child
 */

defined( 'ABSPATH' ) || exit;

/* ──────────────────────────────────────────────────────────────
   ۱. لینک‌دهی و تولتیپ خودکار در متن
   ────────────────────────────────────────────────────────────── */
add_filter( 'the_content', 'qpedia_glossary_highlight_article_terms', 18 );
function qpedia_glossary_highlight_article_terms( $content ) {
	if ( is_admin() || is_feed() || ! in_the_loop() || ! is_main_query() ) {
		return $content;
	}

	if ( ! is_singular( array( 'quantum_article', 'quantum_scientist', QPEDIA_GLOSSARY_POST_TYPE ) ) ) {
		return $content;
	}

	if ( ! apply_filters( 'qpedia_glossary_autolink_enabled', true ) ) {
		return $content;
	}

	$terms = qpedia_glossary_get_terms();
	if ( empty( $terms ) || ! class_exists( 'DOMDocument' ) ) {
		return $content;
	}

	$current_id = (int) get_the_ID();

	$previous = libxml_use_internal_errors( true );
	$dom      = new DOMDocument( '1.0', 'UTF-8' );
	$dom->loadHTML( '<?xml encoding="utf-8" ?><div id="qpedia-glossary-root">' . $content . '</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD );

	$xpath = new DOMXPath( $dom );
	$nodes = $xpath->query( '//div[@id="qpedia-glossary-root"]//text()[normalize-space(.) != "" and not(ancestor::a) and not(ancestor::button) and not(ancestor::code) and not(ancestor::pre) and not(ancestor::script) and not(ancestor::style) and not(ancestor::h1) and not(ancestor::h2) and not(ancestor::h3) and not(ancestor::h4) and not(ancestor::h5) and not(ancestor::h6) and not(ancestor::*[contains(concat(" ", normalize-space(@class), " "), " qpedia-glossary-term ")]) and not(ancestor::*[contains(concat(" ", normalize-space(@class), " "), " qp-sources ")]) and not(ancestor::*[contains(concat(" ", normalize-space(@class), " "), " qp-term-sources ")]) and not(ancestor::figcaption)]' );

	$used  = array();
	$count = 0;
	$max   = (int) apply_filters( 'qpedia_glossary_max_terms_per_article', 12 );

	// اصطلاح‌هایی که همین حالا در متن لینک شده‌اند دوباره لینک نشوند.
	$already = $xpath->query( '//div[@id="qpedia-glossary-root"]//a/@href' );
	$linked  = array();
	if ( $already ) {
		foreach ( $already as $href ) {
			$linked[ untrailingslashit( (string) $href->nodeValue ) ] = true;
		}
	}

	foreach ( iterator_to_array( $nodes ) as $node ) {
		if ( $count >= $max ) {
			break;
		}

		$text = $node->nodeValue;

		foreach ( $terms as $entry ) {
			$key = (int) $entry['id'];

			if ( isset( $used[ $key ] ) || $key === $current_id ) {
				continue;
			}

			if ( isset( $linked[ untrailingslashit( (string) $entry['url'] ) ] ) ) {
				continue;
			}

			$pattern = '/(?<![\p{L}\p{N}_])(' . preg_quote( $entry['term'], '/' ) . ')(?![\p{L}\p{N}_])/u';
			if ( ! preg_match( $pattern, $text, $match, PREG_OFFSET_CAPTURE ) ) {
				continue;
			}

			$matched = $match[1][0];
			$offset  = $match[1][1];

			$fragment = $dom->createDocumentFragment();
			$before   = substr( $text, 0, $offset );
			$after    = substr( $text, $offset + strlen( $matched ) );

			if ( '' !== $before ) {
				$fragment->appendChild( $dom->createTextNode( $before ) );
			}

			$link = $dom->createElement( 'a' );
			$link->setAttribute( 'href', $entry['url'] );
			$link->setAttribute( 'class', 'qpedia-glossary-term' );
			$link->setAttribute( 'aria-expanded', 'false' );
			$link->setAttribute( 'title', $entry['title'] );
			$link->setAttribute( 'data-glossary-definition', $entry['definition'] );
			$link->setAttribute( 'data-glossary-url', $entry['url'] );
			$link->appendChild( $dom->createTextNode( $matched ) );
			$fragment->appendChild( $link );

			if ( '' !== $after ) {
				$fragment->appendChild( $dom->createTextNode( $after ) );
			}

			$node->parentNode->replaceChild( $fragment, $node );

			$used[ $key ] = true;
			$count++;
			break;
		}
	}

	$root   = $dom->getElementById( 'qpedia-glossary-root' );
	$output = '';

	if ( $root ) {
		foreach ( $root->childNodes as $child ) {
			$output .= $dom->saveHTML( $child );
		}
	}

	libxml_clear_errors();
	libxml_use_internal_errors( $previous );

	return $output ? $output : $content;
}

/* ──────────────────────────────────────────────────────────────
   ۲. شورت‌کد [qterm slug="qubit"]کیوبیت[/qterm]
   ────────────────────────────────────────────────────────────── */
add_shortcode( 'qterm', 'qpedia_glossary_sc_term' );
function qpedia_glossary_sc_term( $atts, $content = '' ) {
	$a = shortcode_atts(
		array(
			'slug' => '',
			'id'   => '',
		),
		$atts,
		'qterm'
	);

	$post = null;

	if ( '' !== $a['id'] && ctype_digit( (string) $a['id'] ) ) {
		$candidate = get_post( (int) $a['id'] );
		if ( $candidate instanceof WP_Post && QPEDIA_GLOSSARY_POST_TYPE === $candidate->post_type ) {
			$post = $candidate;
		}
	}

	if ( ! $post && '' !== $a['slug'] ) {
		$post = get_page_by_path( sanitize_title( $a['slug'] ), OBJECT, QPEDIA_GLOSSARY_POST_TYPE );
	}

	$label = trim( wp_strip_all_tags( (string) $content ) );

	if ( ! $post instanceof WP_Post ) {
		return esc_html( $label );
	}

	if ( '' === $label ) {
		$label = $post->post_title;
	}

	$definition = qpedia_glossary_short_definition( $post->ID );

	return '<a class="qpedia-glossary-term" href="' . esc_url( get_permalink( $post ) ) . '" aria-expanded="false" title="' . esc_attr( $post->post_title ) . '" data-glossary-definition="' . esc_attr( $definition ) . '" data-glossary-url="' . esc_url( get_permalink( $post ) ) . '">' . esc_html( $label ) . '</a>';
}

/* ──────────────────────────────────────────────────────────────
   ۳. شورت‌کد [qglossary] — فهرست کامل الفبایی برای هر برگه
   ────────────────────────────────────────────────────────────── */
add_shortcode( 'qglossary', 'qpedia_glossary_sc_list' );
function qpedia_glossary_sc_list( $atts ) {
	$a = shortcode_atts(
		array(
			'columns' => '2',
			'limit'   => '500',
		),
		$atts,
		'qglossary'
	);

	$entries = qpedia_glossary_get_all_entries();
	if ( empty( $entries ) ) {
		return '';
	}

	$limit   = max( 1, absint( $a['limit'] ) );
	$entries = array_slice( $entries, 0, $limit );

	$out = '<div class="qp-glossary-inline" data-columns="' . esc_attr( absint( $a['columns'] ) ) . '">';

	foreach ( $entries as $entry ) {
		$en    = qpedia_glossary_en_title( $entry->ID );
		$short = qpedia_glossary_short_definition( $entry->ID );

		$out .= '<a class="qp-glossary-inline__item" href="' . esc_url( get_permalink( $entry ) ) . '">';
		$out .= '<span class="qp-glossary-inline__fa">' . esc_html( $entry->post_title ) . '</span>';

		if ( '' !== $en ) {
			$out .= '<span class="qp-glossary-inline__en" dir="ltr">' . esc_html( $en ) . '</span>';
		}

		if ( '' !== $short ) {
			$out .= '<span class="qp-glossary-inline__def">' . esc_html( wp_trim_words( $short, 18, '…' ) ) . '</span>';
		}

		$out .= '</a>';
	}

	return $out . '</div>';
}

/* ──────────────────────────────────────────────────────────────
   ۴. شورت‌کد [qp_terms] — بلوک صفحهٔ اصلی، کنار «چهره‌های کوانتوم»
   ────────────────────────────────────────────────────────────── */
add_shortcode( 'qp_terms', 'qpedia_sc_terms_block' );
function qpedia_sc_terms_block( $atts ) {
	$a = shortcode_atts(
		array(
			'title'   => 'اصطلاحات کوانتومی',
			'num'     => '۰۴',
			'note'    => '',
			'count'   => 8,
			'orderby' => 'title',
		),
		$atts,
		'qp_terms'
	);

	$allowed = array( 'date', 'modified', 'rand', 'title' );
	$orderby = in_array( $a['orderby'], $allowed, true ) ? $a['orderby'] : 'title';

	$query = new WP_Query(
		array(
			'post_type'           => QPEDIA_GLOSSARY_POST_TYPE,
			'post_status'         => 'publish',
			'posts_per_page'      => min( 24, max( 1, absint( $a['count'] ) ) ),
			'orderby'             => $orderby,
			'order'               => ( 'title' === $orderby ) ? 'ASC' : 'DESC',
			'ignore_sticky_posts' => true,
			'no_found_rows'       => true,
		)
	);

	$counts = wp_count_posts( QPEDIA_GLOSSARY_POST_TYPE );
	$total  = isset( $counts->publish ) ? (int) $counts->publish : 0;

	$note = trim( (string) $a['note'] );
	if ( '' === $note ) {
		$note = sprintf( 'همهٔ %s اصطلاح در واژه‌نامهٔ کوانتوم', number_format_i18n( $total ) );
	}

	$archive = get_post_type_archive_link( QPEDIA_GLOSSARY_POST_TYPE );

	$out  = '<section class="qp-v2">';
	$out .= '<div class="qp-v2-head"><h2 class="qp-v2-title"><span class="qp-v2-num">' . esc_html( $a['num'] ) . '</span>' . esc_html( $a['title'] ) . '</h2>';
	$out .= '<p class="qp-v2-note">' . esc_html( $note ) . '</p></div>';

	if ( $query->have_posts() ) {
		$out .= '<div class="qp-v2-sc-grid">';

		while ( $query->have_posts() ) {
			$query->the_post();

			$en    = qpedia_glossary_en_title( get_the_ID() );
			$short = qpedia_glossary_short_definition( get_the_ID() );

			$out .= '<a class="qp-v2-sc" href="' . esc_url( get_permalink() ) . '">';
			$out .= '<h3 class="qp-v2-sc__name">' . esc_html( get_the_title() ) . '</h3>';

			if ( '' !== $en ) {
				$out .= '<p class="qp-v2-sc__latin" dir="ltr">' . esc_html( $en ) . '</p>';
			}

			if ( '' !== $short ) {
				$out .= '<p class="qp-v2-sc__desc">' . esc_html( wp_trim_words( $short, 16, '…' ) ) . '</p>';
			}

			$out .= '</a>';
		}

		$out .= '</div>';
		wp_reset_postdata();
	}

	if ( $archive ) {
		$out .= '<p class="qp-v2-more"><a class="qp-v2-more__link" href="' . esc_url( $archive ) . '">مشاهدهٔ واژه‌نامهٔ کامل ←</a></p>';
	}

	return $out . '</section>';
}
