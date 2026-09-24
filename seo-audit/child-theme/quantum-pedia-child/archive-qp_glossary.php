<?php
/**
 * Archive template for qp_glossary — واژه‌نامهٔ اصطلاحات کوانتومی.
 *
 * @package Quantum_Pedia_Child
 */

defined( 'ABSPATH' ) || exit;

get_header();

$qp_entries = qpedia_glossary_get_all_entries();
$qp_total   = count( $qp_entries );

$qp_letters_fa = array();
$qp_letters_en = array();

foreach ( $qp_entries as $qp_entry ) {
	$qp_initials = qpedia_glossary_initials( $qp_entry );

	if ( '' !== $qp_initials['fa'] ) {
		$qp_letters_fa[ $qp_initials['fa'] ] = true;
	}

	if ( '' !== $qp_initials['en'] ) {
		$qp_letters_en[ $qp_initials['en'] ] = true;
	}
}

$qp_letters_fa = array_keys( $qp_letters_fa );
$qp_letters_en = array_keys( $qp_letters_en );

sort( $qp_letters_en );
usort(
	$qp_letters_fa,
	function ( $a, $b ) {
		$order = array( 'ا', 'ب', 'پ', 'ت', 'ث', 'ج', 'چ', 'ح', 'خ', 'د', 'ذ', 'ر', 'ز', 'ژ', 'س', 'ش', 'ص', 'ض', 'ط', 'ظ', 'ع', 'غ', 'ف', 'ق', 'ک', 'گ', 'ل', 'م', 'ن', 'و', 'ه', 'ی' );
		$ai    = array_search( $a, $order, true );
		$bi    = array_search( $b, $order, true );
		$ai    = ( false === $ai ) ? 999 : $ai;
		$bi    = ( false === $bi ) ? 999 : $bi;
		return $ai <=> $bi;
	}
);
?>
<main id="primary" class="site-main">
	<div class="container qp-archive qp-archive--glossary">

		<header class="qp-archive__hero">
			<div class="qp-archive__eyebrow">واژه‌نامهٔ کوانتوم</div>
			<h1 class="qp-archive__title">اصطلاحات کوانتومی</h1>
			<p class="qp-archive__desc">
				تعریف دقیق و فارسیِ اصطلاح‌های فیزیک کوانتوم، همراه با معادل انگلیسی هر مدخل.
				هر اصطلاح صفحهٔ مستقل خودش را دارد و در متن مقاله‌ها هم با تولتیپ در دسترس است.
			</p>
			<p class="qp-glossary-count"><?php echo esc_html( number_format_i18n( $qp_total ) ); ?> اصطلاح ثبت‌شده</p>
		</header>

		<?php if ( $qp_total > 0 ) : ?>

			<div class="qp-glossary-tools" data-qp-glossary-tools>
				<label class="screen-reader-text" for="qp-glossary-search">جست‌وجو در اصطلاحات</label>
				<input
					type="search"
					id="qp-glossary-search"
					class="qp-glossary-search"
					placeholder="جست‌وجو در اصطلاحات فارسی یا انگلیسی…"
					autocomplete="off"
					data-qp-glossary-search
				/>

				<div class="qp-glossary-alpha" role="group" aria-label="فیلتر الفبایی">
					<button type="button" class="qp-glossary-alpha__btn is-active" data-letter="all">همه</button>

					<?php foreach ( $qp_letters_fa as $qp_letter ) : ?>
						<button type="button" class="qp-glossary-alpha__btn" data-letter="<?php echo esc_attr( $qp_letter ); ?>"><?php echo esc_html( $qp_letter ); ?></button>
					<?php endforeach; ?>

					<span class="qp-glossary-alpha__sep" aria-hidden="true">|</span>

					<?php foreach ( $qp_letters_en as $qp_letter ) : ?>
						<button type="button" class="qp-glossary-alpha__btn qp-glossary-alpha__btn--en" data-letter="<?php echo esc_attr( $qp_letter ); ?>" dir="ltr"><?php echo esc_html( $qp_letter ); ?></button>
					<?php endforeach; ?>
				</div>
			</div>

			<div class="qp-glossary-grid" data-qp-glossary-grid>
				<?php
				foreach ( $qp_entries as $qp_entry ) :
					$qp_id       = (int) $qp_entry->ID;
					$qp_en       = qpedia_glossary_en_title( $qp_id );
					$qp_short    = qpedia_glossary_short_definition( $qp_id );
					$qp_abbr     = trim( (string) get_post_meta( $qp_id, '_qp_term_abbr', true ) );
					$qp_level    = (string) get_post_meta( $qp_id, '_qp_term_level', true );
					$qp_initials = qpedia_glossary_initials( $qp_entry );

					$qp_levels = array(
						'beginner'     => 'مقدماتی',
						'intermediate' => 'متوسط',
						'advanced'     => 'پیشرفته',
					);
					?>
					<article
						class="qp-glossary-card"
						data-fa="<?php echo esc_attr( $qp_initials['fa'] ); ?>"
						data-en="<?php echo esc_attr( $qp_initials['en'] ); ?>"
						data-search="<?php
						$qp_search_blob = $qp_entry->post_title . ' ' . $qp_en . ' ' . $qp_abbr;
						echo esc_attr( function_exists( 'mb_strtolower' ) ? mb_strtolower( $qp_search_blob, 'UTF-8' ) : strtolower( $qp_search_blob ) );
						?>"
					>
						<div class="qp-glossary-card__head">
							<h2 class="qp-glossary-card__title">
								<a href="<?php echo esc_url( get_permalink( $qp_entry ) ); ?>"><?php echo esc_html( $qp_entry->post_title ); ?></a>
							</h2>
							<?php if ( isset( $qp_levels[ $qp_level ] ) ) : ?>
								<span class="qp-glossary-card__level qp-glossary-card__level--<?php echo esc_attr( $qp_level ); ?>"><?php echo esc_html( $qp_levels[ $qp_level ] ); ?></span>
							<?php endif; ?>
						</div>

						<?php if ( '' !== $qp_en ) : ?>
							<p class="qp-glossary-card__en" dir="ltr"><?php echo esc_html( $qp_en ); ?><?php echo '' !== $qp_abbr ? esc_html( ' · ' . $qp_abbr ) : ''; ?></p>
						<?php endif; ?>

						<?php if ( '' !== $qp_short ) : ?>
							<p class="qp-glossary-card__def"><?php echo esc_html( wp_trim_words( $qp_short, 26, '…' ) ); ?></p>
						<?php endif; ?>

						<a class="qp-glossary-card__more" href="<?php echo esc_url( get_permalink( $qp_entry ) ); ?>">
							تعریف کامل ←
						</a>
					</article>
				<?php endforeach; ?>
			</div>

			<p class="qp-glossary-empty" data-qp-glossary-empty hidden>اصطلاحی با این جست‌وجو پیدا نشد.</p>

			<section class="qp-glossary-cta">
				<h2 class="qp-glossary-cta__title">از واژه‌نامه به دانشنامه</h2>
				<p class="qp-glossary-cta__desc">هر اصطلاح در مقاله‌های کوانتوم پدیا با جزئیات بیشتر توضیح داده شده است.</p>
				<div class="qp-glossary-cta__links">
					<a href="<?php echo esc_url( home_url( '/' ) ); ?>">مقاله‌های کوانتوم</a>
					<?php
					$qp_sci_archive = get_post_type_archive_link( 'quantum_scientist' );
					if ( $qp_sci_archive ) :
						?>
						<a href="<?php echo esc_url( $qp_sci_archive ); ?>">دانشمندان کوانتوم</a>
					<?php endif; ?>
				</div>
			</section>

		<?php else : ?>

			<div class="qp-empty-state">
				<h2 class="qp-empty-state__title">هنوز اصطلاحی ثبت نشده است</h2>
				<p class="qp-empty-state__desc">پس از افزودن مدخل‌های واژه‌نامه، این صفحه کامل می‌شود.</p>
			</div>

		<?php endif; ?>

	</div>
</main>
<?php
get_footer();
