<?php
/**
 * Global slim footer for Quantum Pedia Child — Cosmic v3.
 *
 * - کاملاً هم‌رنگ زمینه (پس‌زمینهٔ کمی روشن‌تر برای ایجاد مرز)
 * - بدون EDITORIAL & MANAGEMENT (به صفحهٔ «درباره ما» منتقل شد)
 * - «QUANTUM PEDIA ◆» در کنار لوگو (نه در بالای فوتر)
 * - کپی‌رایت زیر فوتر با خط جداگانه
 *
 * @package Quantum_Pedia_Child
 */

defined( 'ABSPATH' ) || exit;
?>
<footer id="colophon" class="qp-global-footer">
	<div class="container qp-global-footer__inner">

		<div class="qp-global-footer__main">
			<a class="qp-global-footer__brand-side" href="<?php echo esc_url( home_url( '/' ) ); ?>" rel="home">
				<img class="qp-global-footer__logo" src="<?php echo esc_url( get_stylesheet_directory_uri() . '/assets/images/qpedia-logo-neon.png' ); ?>" alt="<?php esc_attr_e( 'لوگوی کوانتوم پدیا', 'quantum-pedia-child' ); ?>" width="44" height="44" decoding="async" />
				<span class="qp-global-footer__brand-mark">
					<span class="qp-global-footer__dot" aria-hidden="true">◆</span>
					QUANTUM PEDIA
					<span class="qp-global-footer__dot" aria-hidden="true">◆</span>
				</span>
			</a>

			<nav class="qp-global-footer__links" aria-label="<?php esc_attr_e( 'صفحات سایت', 'quantum-pedia-child' ); ?>">
				<a href="<?php echo esc_url( qpedia_child_find_page_url( array( 'about-us', 'about', 'درباره-ما' ) ) ); ?>"><?php esc_html_e( 'درباره ما', 'quantum-pedia-child' ); ?></a>
				<a href="<?php echo esc_url( qpedia_child_find_page_url( array( 'contact-us', 'contact', 'تماس-با-ما' ) ) ); ?>"><?php esc_html_e( 'تماس با ما', 'quantum-pedia-child' ); ?></a>
				<a href="<?php echo esc_url( qpedia_child_find_page_url( array( 'rules', 'terms', 'regulations', 'مقررات-ما' ) ) ); ?>"><?php esc_html_e( 'مقررات ما', 'quantum-pedia-child' ); ?></a>
			</nav>
		</div>

		<p class="qp-global-footer__copy">© <?php echo esc_html( gmdate( 'Y' ) ); ?> <?php esc_html_e( 'کوانتوم پدیا · تمامی حقوق محفوظ است.', 'quantum-pedia-child' ); ?></p>

	</div>
</footer>
<?php wp_footer(); ?>
</body>
</html>