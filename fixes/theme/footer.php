<?php
/**
 * Global slim footer for Quantum Pedia Child.
 * (Ocean Breeze — قانون ۱/۲: نوار باریک؛ لوگو + شعار + سه لینک)
 *
 * @package Quantum_Pedia_Child
 */

defined( 'ABSPATH' ) || exit;
?>
<footer id="colophon" class="qp-global-footer">
	<div class="container qp-global-footer__inner">
		<a class="qp-global-footer__brand" href="<?php echo esc_url( home_url( '/' ) ); ?>" rel="home">
			<img class="qp-global-footer__logo" src="<?php echo esc_url( get_stylesheet_directory_uri() . '/assets/images/qpedia-logo-neon.png' ); ?>" alt="<?php esc_attr_e( 'لوگوی کوانتوم پدیا', 'quantum-pedia-child' ); ?>" width="40" height="40" decoding="async" />
			<span class="qp-global-footer__slogan"><?php esc_html_e( 'منبعی مینیمال و دقیق برای مرور مفاهیم، فناوری‌ها و روایت‌های مهم دنیای کوانتوم.', 'quantum-pedia-child' ); ?></span>
		</a>
		<nav class="qp-global-footer__links" aria-label="<?php esc_attr_e( 'صفحات سایت', 'quantum-pedia-child' ); ?>">
			<a href="<?php echo esc_url( qpedia_child_find_page_url( array( 'about-us', 'about', 'درباره-ما' ) ) ); ?>"><?php esc_html_e( 'درباره ما', 'quantum-pedia-child' ); ?></a>
			<a href="<?php echo esc_url( qpedia_child_find_page_url( array( 'contact-us', 'contact', 'تماس-با-ما' ) ) ); ?>"><?php esc_html_e( 'تماس با ما', 'quantum-pedia-child' ); ?></a>
			<a href="<?php echo esc_url( qpedia_child_find_page_url( array( 'privacy-policy', 'rules', 'terms', 'regulations', 'مقررات-ما' ) ) ); ?>"><?php esc_html_e( 'مقررات ما', 'quantum-pedia-child' ); ?></a>
		</nav>
	</div>
</footer>
<?php wp_footer(); ?>
</body>
</html>
