<?php
/**
 * Template Name: صفحهٔ اصلی QPedia (بدون JS، بدون CSS)
 * فایل: wp-content/themes/quantum-pedia-child/page-homepage.php
 *
 * چرا این فایل؟ صفحهٔ home (برگهٔ 2790) محتوایش را با یک اسکریپت سمت کلاینت پر می‌کرد
 * و آن اسکریپت به‌خاطر entity‌شدن کاراکتر & در بلوک HTML می‌مرد ⇒ «در حال دریافت…» همیشگی.
 * بعد هم متن PHP داخل برگه چسبانده شد و محتوای اصلی برگه از بین رفت.
 * این قالب، کل صفحهٔ اصلی را سمت سرور می‌سازد: هیچ JS ندارد، هیچ CSS ندارد (از
 * استایل‌های موجودِ پوسته با همان کلاس‌ها استفاده می‌کند) و مستقل از خرابی محتوای
 * برگه کار می‌کند.
 *
 * پوستهٔ فرزند همین نامِ فایل را جای پوستهٔ والد پیدا می‌کند، پس اگر برگهٔ Home هنوز
 * قالب «page-homepage.php» را در ستون سمت چپِ ویرایشگر دارد، چیزی برای عوض‌کردن نیست.
 * برای بازگشت: همین فایل را حذف کن (قالب والد دوباره استفاده می‌شود).
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

get_header();

global $post;

/* ── داده‌ها ─────────────────────────────────────────────────────── */

/* ۱) آخرین مقاله‌ها (نسخه‌های تکراریِ «-2» رد می‌شوند) */
$qp_recent = array();
$qp_query  = new WP_Query(
	array(
		'post_type'           => 'quantum_article',
		'post_status'         => 'publish',
		'posts_per_page'      => 14,
		'orderby'             => 'date',
		'order'               => 'DESC',
		'ignore_sticky_posts' => true,
		'no_found_rows'       => true,
	)
);

if ( $qp_query->have_posts() ) {
	while ( $qp_query->have_posts() && count( $qp_recent ) < 8 ) {
		$qp_query->the_post();
		$qp_slug = (string) get_post_field( 'post_name', get_the_ID() );
		if ( '' !== $qp_slug && preg_match( '/-2$/', $qp_slug ) ) {
			continue;
		}
		$qp_recent[] = array(
			'url'   => (string) get_permalink(),
			'title' => trim( (string) get_the_title() ),
			'date'  => date_i18n( 'j F Y', strtotime( (string) get_post_time( 'Y-m-d H:i:s', true ) ) ),
		);
	}
	wp_reset_postdata();
}

/* ۲) دسته‌بندی موضوعات */
$qp_topics = array();
$qp_terms  = get_terms(
	array(
		'taxonomy'   => 'quantum_category',
		'hide_empty' => false,
		'number'     => 12,
		'orderby'    => 'count',
		'order'      => 'DESC',
	)
);

if ( ! is_wp_error( $qp_terms ) && $qp_terms ) {
	foreach ( $qp_terms as $qp_term ) {
		$qp_link = get_term_link( $qp_term );
		if ( is_wp_error( $qp_link ) ) {
			continue;
		}
		$qp_topics[] = array(
			'url'   => (string) $qp_link,
			'title' => (string) $qp_term->name,
			'desc'  => trim( (string) $qp_term->description ),
			'count' => (int) $qp_term->count,
		);
	}
}

/* ۳) نشانی آرشیو مقاله‌ها؛ اگر آرشیو نساخته بودیم، اولین دسته */
$qp_more = get_post_type_archive_link( 'quantum_article' );
if ( ! $qp_more && isset( $qp_topics[0]['url'] ) ) {
	$qp_more = $qp_topics[0]['url'];
}
if ( ! $qp_more ) {
	$qp_more = home_url( '/' );
}

/* ۴) شمارنده‌ها برای نوار آمار */
$qp_counts  = wp_count_posts( 'quantum_article' );
$qp_n_art   = isset( $qp_counts->publish ) ? (int) $qp_counts->publish : 0;
$qp_n_top   = count( $qp_topics );
$qp_sci     = wp_count_posts( 'quantum_scientist' );
$qp_n_sci   = ( $qp_sci && isset( $qp_sci->publish ) ) ? (int) $qp_sci->publish : 0;

/* اگر محتوای خودِ برگه تمیز بود (نه کد PHP چسبانده‌شده)، همان را هم نشان بده
   تا چیزی از دست نرود؛ اگر آلوده بود، نادیده‌اش می‌گیریم تا صفحه خراب دیده نشود. */
$qp_raw          = is_object( $post ) ? (string) $post->post_content : '';
$qp_show_content = ( strlen( trim( $qp_raw ) ) > 40
	&& false === stripos( $qp_raw, '<?php' )
	&& false === stripos( $qp_raw, 'add_filter' )
	&& false === stripos( $qp_raw, 'qpedia_' ) );
?>

<div id="qpedia-home" class="qp-home" dir="rtl">

	<section class="qp-hero">
		<p class="qp-kicker">دانشنامهٔ کوانتوم پدیا</p>
		<h1 class="qp-hero-title">کوانتوم را <span>درست</span> بفهم</h1>
		<p class="qp-hero-text">
			مقاله‌های علمیِ فارسی، با تفکیکِ «آنچه اثبات شده» از «آنچه ادعاست» —
			برای کسی که می‌خواهد فیزیک کوانتوم را فراتر از شعار بداند.
		</p>
		<p class="qp-hero-actions">
			<a class="qp-btn qp-btn-primary" href="<?php echo esc_url( home_url( '/start/' ) ); ?>">از اینجا شروع کن</a>
			<a class="qp-btn qp-btn-secondary" href="<?php echo esc_url( home_url( '/scientists/' ) ); ?>">دانشمندانش را بشناس</a>
		</p>
		<p class="qp-hero-stats">
			<span><strong><?php echo esc_html( number_format_i18n( $qp_n_art ) ); ?></strong> مقاله</span>
			<span><strong><?php echo esc_html( number_format_i18n( $qp_n_top ) ); ?></strong> موضوع</span>
			<?php if ( $qp_n_sci ) : ?>
				<span><strong><?php echo esc_html( number_format_i18n( $qp_n_sci ) ); ?></strong> دانشمند</span>
			<?php endif; ?>
		</p>
	</section>

	<?php if ( $qp_topics ) : ?>
		<section class="qp-section qp-topics">
			<h2 class="qp-section-title"><span class="qp-index">۰۱</span> دسته‌بندی موضوعات</h2>
			<div class="qp-topic-grid">
				<?php foreach ( $qp_topics as $qp_topic ) : ?>
					<a class="qp-topic-card" href="<?php echo esc_url( $qp_topic['url'] ); ?>">
						<span class="qp-topic-title"><?php echo esc_html( $qp_topic['title'] ); ?></span>
						<?php if ( '' !== $qp_topic['desc'] ) : ?>
							<span class="qp-topic-desc"><?php echo esc_html( $qp_topic['desc'] ); ?></span>
						<?php endif; ?>
						<span class="qp-topic-count"><?php echo esc_html( number_format_i18n( $qp_topic['count'] ) ); ?> مقاله</span>
					</a>
				<?php endforeach; ?>
			</div>
		</section>
	<?php endif; ?>

	<section class="qp-section qp-recent">
		<h2 class="qp-section-title"><span class="qp-index">۰۲</span> تازه‌ترین مقاله‌ها</h2>

		<div class="qp-articles" id="qp-articles">
			<?php if ( $qp_recent ) : ?>
				<?php foreach ( $qp_recent as $qp_item ) : ?>
					<a class="qp-article" href="<?php echo esc_url( $qp_item['url'] ); ?>" dir="auto" title="<?php echo esc_attr( $qp_item['title'] ); ?>">
						<span class="qp-article-title"><?php echo esc_html( $qp_item['title'] ); ?></span>
						<span class="qp-article-meta"><span class="qp-article-date"><?php echo esc_html( $qp_item['date'] ); ?></span></span>
						<span class="qp-article-arrow" aria-hidden="true">⟵</span>
					</a>
				<?php endforeach; ?>
			<?php else : ?>
				<div class="qp-loading">هنوز مقاله‌ای برای نمایش نیست.</div>
			<?php endif; ?>
		</div>

		<p class="qp-more-wrap">
			<a class="qp-more" href="<?php echo esc_url( $qp_more ); ?>">مشاهدهٔ همهٔ مقاله‌ها <span aria-hidden="true">←</span></a>
		</p>
	</section>

	<?php if ( $qp_show_content ) : ?>
		<section class="qp-section qp-page-content">
			<?php echo wp_kses_post( wpautop( $post->post_content ) ); ?>
		</section>
	<?php endif; ?>

</div>

<?php
get_footer();
