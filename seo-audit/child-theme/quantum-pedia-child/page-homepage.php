<?php
/**
 * Template Name: صفحه اصلی
 * Description: بوم خالی تمام‌عرض برای برگهٔ «صفحه اصلی» — بدون تیتر، بدون سایدبار. محتوا را با شورت‌کدهای qp_hero و... بسازید.
 *
 * @package Quantum_Pedia_Child
 */

defined( 'ABSPATH' ) || exit;

get_header();
?>
<main id="primary" class="site-main">
	<div class="container qp-home-canvas">
		<?php
		while ( have_posts() ) :
			the_post();
			the_content();
		endwhile;
		?>
	</div>
</main>
<?php
get_footer();
