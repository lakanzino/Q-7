<?php
/**
 * Single template for qp_glossary — صفحهٔ یک اصطلاح کوانتومی.
 *
 * @package Quantum_Pedia_Child
 */

defined( 'ABSPATH' ) || exit;

get_header();

while ( have_posts() ) :
	the_post();

	$qp_id    = get_the_ID();
	$qp_en    = qpedia_glossary_en_title( $qp_id );
	$qp_abbr  = trim( (string) get_post_meta( $qp_id, '_qp_term_abbr', true ) );
	$qp_short = qpedia_glossary_short_definition( $qp_id );
	$qp_level = (string) get_post_meta( $qp_id, '_qp_term_level', true );

	$qp_levels = array(
		'beginner'     => 'مقدماتی',
		'intermediate' => 'متوسط',
		'advanced'     => 'پیشرفته',
	);

	$qp_reviewer = trim( (string) get_post_meta( $qp_id, '_qp_term_reviewer', true ) );
	$qp_reviewed = trim( (string) get_post_meta( $qp_id, '_qp_term_reviewed', true ) );

	$qp_related_terms      = qpedia_glossary_resolve_posts( get_post_meta( $qp_id, '_qp_term_related_terms', true ), array( QPEDIA_GLOSSARY_POST_TYPE ) );
	$qp_related_articles   = qpedia_glossary_resolve_posts( get_post_meta( $qp_id, '_qp_term_related_articles', true ), array( 'quantum_article' ) );
	$qp_related_scientists = qpedia_glossary_resolve_posts( get_post_meta( $qp_id, '_qp_term_related_scientists', true ), array( 'quantum_scientist' ) );

	$qp_faq     = qpedia_glossary_parse_pairs( get_post_meta( $qp_id, '_qp_term_faq', true ) );
	$qp_sources = qpedia_glossary_parse_pairs( get_post_meta( $qp_id, '_qp_term_sources', true ) );
	?>
	<main id="primary" class="site-main">
		<div class="container qp-shell qp-shell--glossary">

			<nav class="qp-breadcrumb" aria-label="مسیر صفحه">
				<a href="<?php echo esc_url( home_url( '/' ) ); ?>">خانه</a>
				<span aria-hidden="true">/</span>
				<a href="<?php echo esc_url( qpedia_glossary_archive_url() ); ?>">واژه‌نامهٔ کوانتوم</a>
				<span aria-hidden="true">/</span>
				<span aria-current="page"><?php echo esc_html( get_the_title() ); ?></span>
			</nav>

			<article <?php post_class( 'qp-glossary-entry' ); ?>>

				<header class="qp-glossary-entry__header">
					<div class="qp-glossary-entry__eyebrow">اصطلاح کوانتومی</div>
					<h1 class="qp-glossary-entry__title"><?php echo esc_html( get_the_title() ); ?></h1>

					<?php if ( '' !== $qp_en ) : ?>
						<p class="qp-glossary-entry__en" dir="ltr"><?php echo esc_html( $qp_en ); ?></p>
					<?php endif; ?>

					<div class="qp-glossary-entry__chips">
						<?php if ( '' !== $qp_abbr ) : ?>
							<span class="qp-glossary-chip qp-glossary-chip--abbr" dir="ltr"><?php echo esc_html( $qp_abbr ); ?></span>
						<?php endif; ?>

						<?php if ( isset( $qp_levels[ $qp_level ] ) ) : ?>
							<span class="qp-glossary-chip">سطح: <?php echo esc_html( $qp_levels[ $qp_level ] ); ?></span>
						<?php endif; ?>

						<span class="qp-glossary-chip qp-glossary-chip--code" dir="ltr">/<?php echo esc_html( urldecode( (string) get_post_field( 'post_name', $qp_id ) ) ); ?>/</span>
					</div>
				</header>

				<?php if ( '' !== $qp_short ) : ?>
					<div class="qp-glossary-defbox">
						<div class="qp-glossary-defbox__label">تعریف کوتاه</div>
						<p class="qp-glossary-defbox__text"><?php echo esc_html( $qp_short ); ?></p>
					</div>
				<?php endif; ?>

				<div class="qp-glossary-entry__content entry-content">
					<?php the_content(); ?>
				</div>

				<?php if ( ! empty( $qp_faq ) ) : ?>
					<section class="qp-glossary-section qp-glossary-faq" aria-labelledby="qp-glossary-faq-title">
						<h2 id="qp-glossary-faq-title" class="qp-glossary-section__title">پرسش‌های رایج دربارهٔ <?php echo esc_html( get_the_title() ); ?></h2>

						<?php foreach ( $qp_faq as $qp_row ) : ?>
							<?php if ( '' === $qp_row['value'] ) : continue; endif; ?>
							<details class="qp-glossary-faq__item">
								<summary class="qp-glossary-faq__q"><?php echo esc_html( $qp_row['key'] ); ?></summary>
								<div class="qp-glossary-faq__a"><?php echo wp_kses_post( wpautop( $qp_row['value'] ) ); ?></div>
							</details>
						<?php endforeach; ?>
					</section>
				<?php endif; ?>

				<?php if ( ! empty( $qp_related_articles ) || ! empty( $qp_related_scientists ) ) : ?>
					<section class="qp-glossary-section" aria-labelledby="qp-glossary-related-title">
						<h2 id="qp-glossary-related-title" class="qp-glossary-section__title">مطالعهٔ بیشتر</h2>

						<div class="qp-glossary-related">
							<?php foreach ( $qp_related_articles as $qp_related ) : ?>
								<a class="qp-glossary-related__card" href="<?php echo esc_url( get_permalink( $qp_related ) ); ?>">
									<span class="qp-glossary-related__kind">مقاله</span>
									<span class="qp-glossary-related__title"><?php echo esc_html( get_the_title( $qp_related ) ); ?></span>
								</a>
							<?php endforeach; ?>

							<?php foreach ( $qp_related_scientists as $qp_related ) : ?>
								<a class="qp-glossary-related__card" href="<?php echo esc_url( get_permalink( $qp_related ) ); ?>">
									<span class="qp-glossary-related__kind">دانشمند</span>
									<span class="qp-glossary-related__title"><?php echo esc_html( get_the_title( $qp_related ) ); ?></span>
								</a>
							<?php endforeach; ?>
						</div>
					</section>
				<?php endif; ?>

				<?php if ( ! empty( $qp_related_terms ) ) : ?>
					<section class="qp-glossary-section" aria-labelledby="qp-glossary-terms-title">
						<h2 id="qp-glossary-terms-title" class="qp-glossary-section__title">اصطلاحات مرتبط</h2>

						<div class="qp-glossary-terms">
							<?php foreach ( $qp_related_terms as $qp_related ) : ?>
								<a class="qp-glossary-terms__link" href="<?php echo esc_url( get_permalink( $qp_related ) ); ?>">
									<?php echo esc_html( get_the_title( $qp_related ) ); ?>
								</a>
							<?php endforeach; ?>
						</div>
					</section>
				<?php endif; ?>

				<?php if ( ! empty( $qp_sources ) ) : ?>
					<section class="qp-glossary-section qp-glossary-sources" aria-labelledby="qp-glossary-sources-title">
						<h2 id="qp-glossary-sources-title" class="qp-glossary-section__title">منابع</h2>
						<ol class="qp-glossary-sources__list">
							<?php foreach ( $qp_sources as $qp_row ) : ?>
								<li>
									<?php if ( '' !== $qp_row['value'] ) : ?>
										<a href="<?php echo esc_url( $qp_row['value'] ); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html( $qp_row['key'] ); ?></a>
									<?php else : ?>
										<?php echo esc_html( $qp_row['key'] ); ?>
									<?php endif; ?>
								</li>
							<?php endforeach; ?>
						</ol>
					</section>
				<?php endif; ?>

				<?php if ( '' !== $qp_reviewer || '' !== $qp_reviewed ) : ?>
					<p class="qp-glossary-reviewed">
						<?php if ( '' !== $qp_reviewer ) : ?>
							بازبینی علمی: <strong><?php echo esc_html( $qp_reviewer ); ?></strong>
						<?php endif; ?>
						<?php if ( '' !== $qp_reviewed ) : ?>
							<span> · آخرین بازبینی: <?php echo esc_html( $qp_reviewed ); ?></span>
						<?php endif; ?>
					</p>
				<?php endif; ?>

				<footer class="qp-glossary-entry__footer">
					<p class="qp-glossary-entry__back">
						<a href="<?php echo esc_url( qpedia_glossary_archive_url() ); ?>">← بازگشت به واژه‌نامهٔ کوانتوم</a>
					</p>
				</footer>

			</article>

			<?php
			$qp_prev = get_previous_post();
			$qp_next = get_next_post();

			if ( $qp_prev || $qp_next ) :
				?>
				<nav class="qp-glossary-nav" aria-label="اصطلاح‌های همسایه">
					<?php if ( $qp_prev ) : ?>
						<a class="qp-glossary-nav__link qp-glossary-nav__link--prev" href="<?php echo esc_url( get_permalink( $qp_prev ) ); ?>">
							<span class="qp-glossary-nav__dir">اصطلاح قبلی</span>
							<span class="qp-glossary-nav__title"><?php echo esc_html( get_the_title( $qp_prev ) ); ?></span>
						</a>
					<?php endif; ?>

					<?php if ( $qp_next ) : ?>
						<a class="qp-glossary-nav__link qp-glossary-nav__link--next" href="<?php echo esc_url( get_permalink( $qp_next ) ); ?>">
							<span class="qp-glossary-nav__dir">اصطلاح بعدی</span>
							<span class="qp-glossary-nav__title"><?php echo esc_html( get_the_title( $qp_next ) ); ?></span>
						</a>
					<?php endif; ?>
				</nav>
			<?php endif; ?>

		</div>
	</main>
	<?php
endwhile;

get_footer();
