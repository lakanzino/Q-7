<?php
/**
 * Search form override for Quantum Pedia Child.
 *
 * @package Quantum_Pedia_Child
 */

defined( 'ABSPATH' ) || exit;
?>
<form role="search" method="get" class="qp-search-form" action="<?php echo esc_url( home_url( '/' ) ); ?>">
	<label class="screen-reader-text" for="qp-search-input"><?php esc_html_e( 'جست‌وجو برای:', 'quantum-pedia-child' ); ?></label>
	<input id="qp-search-input" type="search" class="qp-search-form__input" placeholder="<?php esc_attr_e( 'جست‌وجو در مقاله‌ها، مفاهیم و دانشمندان...', 'quantum-pedia-child' ); ?>" value="<?php echo esc_attr( get_search_query() ); ?>" name="s" />
	<button type="submit" class="qp-search-form__submit"><?php esc_html_e( 'جست‌وجو', 'quantum-pedia-child' ); ?></button>
</form>
