<?php
/**
 * Glossary module — conditional front-end assets.
 *
 * @package Quantum_Pedia_Child
 */

defined( 'ABSPATH' ) || exit;

add_action( 'wp_enqueue_scripts', 'qpedia_glossary_enqueue_assets', 30 );
function qpedia_glossary_enqueue_assets() {
	if ( ! is_singular( 'quantum_article' ) ) return;
	$css = get_stylesheet_directory() . '/assets/css/qpedia-glossary.css';
	$js  = get_stylesheet_directory() . '/assets/js/qpedia-glossary.js';
	if ( file_exists( $css ) ) wp_enqueue_style( 'qpedia-glossary', get_stylesheet_directory_uri() . '/assets/css/qpedia-glossary.css', array( 'qpedia-child-custom' ), filemtime( $css ) );
	if ( file_exists( $js ) ) wp_enqueue_script( 'qpedia-glossary', get_stylesheet_directory_uri() . '/assets/js/qpedia-glossary.js', array(), filemtime( $js ), true );
}
