<?php
/**
 * Glossary module — safe, display-time article highlighting.
 *
 * @package Quantum_Pedia_Child
 */

defined( 'ABSPATH' ) || exit;

add_filter( 'the_content', 'qpedia_glossary_highlight_article_terms', 18 );
function qpedia_glossary_highlight_article_terms( $content ) {
	if ( is_admin() || is_feed() || ! is_singular( 'quantum_article' ) || ! in_the_loop() || ! is_main_query() ) return $content;
	$terms = qpedia_glossary_get_terms();
	if ( empty( $terms ) || ! class_exists( 'DOMDocument' ) ) return $content;

	$previous = libxml_use_internal_errors( true );
	$dom = new DOMDocument( '1.0', 'UTF-8' );
	$dom->loadHTML( '<?xml encoding="utf-8" ?><div id="qpedia-glossary-root">' . $content . '</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD );
	$xpath = new DOMXPath( $dom );
	$nodes = $xpath->query( '//div[@id="qpedia-glossary-root"]//text()[normalize-space(.) != "" and not(ancestor::a) and not(ancestor::button) and not(ancestor::code) and not(ancestor::pre) and not(ancestor::script) and not(ancestor::style) and not(ancestor::h1) and not(ancestor::h2) and not(ancestor::h3) and not(ancestor::h4) and not(ancestor::h5) and not(ancestor::h6) and not(ancestor::*[contains(concat(" ", normalize-space(@class), " "), " qpedia-glossary-term ")])]' );
	$used = array();
	$count = 0;
	$max = (int) apply_filters( 'qpedia_glossary_max_terms_per_article', 12 );

	foreach ( iterator_to_array( $nodes ) as $node ) {
		if ( $count >= $max ) break;
		$text = $node->nodeValue;
		foreach ( $terms as $entry ) {
			$key = (int) $entry['id'];
			if ( isset( $used[ $key ] ) ) continue;
			$pattern = '/(?<![\p{L}\p{N}_])(' . preg_quote( $entry['term'], '/' ) . ')(?![\p{L}\p{N}_])/u';
			if ( ! preg_match( $pattern, $text, $match, PREG_OFFSET_CAPTURE ) ) continue;
			$matched = $match[1][0]; $offset = $match[1][1];
			$fragment = $dom->createDocumentFragment();
			$before = substr( $text, 0, $offset ); $after = substr( $text, $offset + strlen( $matched ) );
			if ( $before !== '' ) $fragment->appendChild( $dom->createTextNode( $before ) );
			$button = $dom->createElement( 'button' );
			$button->setAttribute( 'type', 'button' );
			$button->setAttribute( 'class', 'qpedia-glossary-term' );
			$button->setAttribute( 'aria-expanded', 'false' );
			$button->setAttribute( 'data-glossary-definition', $entry['definition'] );
			$button->setAttribute( 'data-glossary-url', $entry['url'] );
			$button->appendChild( $dom->createTextNode( $matched ) );
			$fragment->appendChild( $button );
			if ( $after !== '' ) $fragment->appendChild( $dom->createTextNode( $after ) );
			$node->parentNode->replaceChild( $fragment, $node );
			$used[ $key ] = true; $count++; break;
		}
	}
	$root = $dom->getElementById( 'qpedia-glossary-root' );
	$output = '';
	if ( $root ) foreach ( $root->childNodes as $child ) $output .= $dom->saveHTML( $child );
	libxml_clear_errors(); libxml_use_internal_errors( $previous );
	return $output ?: $content;
}
