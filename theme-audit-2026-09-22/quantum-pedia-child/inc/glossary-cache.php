<?php
/**
 * Glossary module — cached term dictionary.
 *
 * @package Quantum_Pedia_Child
 */

defined( 'ABSPATH' ) || exit;

function qpedia_glossary_clear_cache() {
	delete_transient( 'qpedia_glossary_terms_v1' );
}

foreach ( array( 'save_post_qp_glossary', 'deleted_post', 'trashed_post', 'untrashed_post' ) as $qpedia_glossary_hook ) {
	add_action( $qpedia_glossary_hook, 'qpedia_glossary_clear_cache' );
}
unset( $qpedia_glossary_hook );

function qpedia_glossary_get_terms() {
	$cached = get_transient( 'qpedia_glossary_terms_v1' );
	if ( is_array( $cached ) ) return $cached;

	$posts = get_posts( array(
		'post_type' => QPEDIA_GLOSSARY_POST_TYPE, 'post_status' => 'publish',
		'posts_per_page' => 500, 'orderby' => 'title', 'order' => 'ASC', 'no_found_rows' => true,
	) );
	$terms = array();
	foreach ( $posts as $post ) {
		$definition = trim( wp_strip_all_tags( $post->post_excerpt ?: $post->post_content, true ) );
		if ( '' === $definition ) continue;
		$forms = array( $post->post_title );
		$aliases = preg_split( '/\R/u', (string) get_post_meta( $post->ID, '_qpedia_glossary_aliases', true ) );
		foreach ( $aliases as $alias ) if ( trim( $alias ) !== '' ) $forms[] = trim( $alias );
		foreach ( array_unique( $forms ) as $form ) {
			$terms[] = array( 'term' => $form, 'definition' => $definition, 'url' => get_permalink( $post ), 'id' => (int) $post->ID );
		}
	}
	usort( $terms, function( $a, $b ) {
		$al = function_exists( 'mb_strlen' ) ? mb_strlen( $a['term'], 'UTF-8' ) : strlen( $a['term'] );
		$bl = function_exists( 'mb_strlen' ) ? mb_strlen( $b['term'], 'UTF-8' ) : strlen( $b['term'] );
		return $bl <=> $al;
	} );
	set_transient( 'qpedia_glossary_terms_v1', $terms, DAY_IN_SECONDS );
	return $terms;
}
