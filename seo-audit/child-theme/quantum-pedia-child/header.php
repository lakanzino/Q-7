<?php
/**
 * Global custom header for Quantum Pedia Child.
 *
 * نسخهٔ Cosmic (v2 — مینیمال):
 * - هدر چسبان، کاملاً هم‌رنگ زمینه (بدون border، بدون خط جداکننده)
 * - فقط لوگو + نام + یک آیکون جست‌وجو (مینیمال واقعی)
 * - هنگام اسکرول: blur ملایم + خط نورانی آبی زیر هدر
 * - اورلی جست‌وجو با کلیک روی آیکون (شیشه‌ای، وسط صفحه)
 * - دسته‌ها از هدر حذف شدند (در صفحه اصلی با شورت‌کد [qp_cats] خودشان نمایش داده می‌شوند)
 *
 * @package Quantum_Pedia_Child
 */

defined( 'ABSPATH' ) || exit;
?><!doctype html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<link rel="profile" href="https://gmpg.org/xfn/11">
	<?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>
<?php wp_body_open(); ?>
<a class="skip-link screen-reader-text" href="#primary"><?php esc_html_e( 'Skip to content', 'quantum-pedia-child' ); ?></a>

<header id="masthead" class="qp-global-header" data-qp-header>
	<div class="container qp-global-header__inner">

		<div class="qp-global-header__brand">
			<a class="qp-brand" href="<?php echo esc_url( home_url( '/' ) ); ?>" rel="home">
				<span class="qp-brand__logo-wrap">
					<img class="qp-brand__logo" src="<?php echo esc_url( get_stylesheet_directory_uri() . '/assets/images/qpedia-logo-neon.png' ); ?>" alt="<?php esc_attr_e( 'لوگوی کوانتوم پدیا', 'quantum-pedia-child' ); ?>" width="44" height="44" decoding="async" />
				</span>
				<span class="qp-brand__text">
					<span class="qp-brand__title"><?php esc_html_e( 'کوانتوم پدیا فارسی', 'quantum-pedia-child' ); ?></span>
				</span>
			</a>
		</div>

		<div class="qp-global-header__actions">
			<?php
			$qpedia_header_links = array();

			$qpedia_scientists_url = get_post_type_archive_link( 'quantum_scientist' );
			if ( $qpedia_scientists_url ) {
				$qpedia_header_links[] = array(
					'url'   => $qpedia_scientists_url,
					'label' => 'دانشمندان',
				);
			}

			$qpedia_glossary_url = get_post_type_archive_link( 'qp_glossary' );
			if ( ! $qpedia_glossary_url ) {
				$qpedia_glossary_url = home_url( '/glossary/' );
			}
			$qpedia_header_links[] = array(
				'url'   => $qpedia_glossary_url,
				'label' => 'اصطلاحات',
			);

			$qpedia_request_path = isset( $_SERVER['REQUEST_URI'] ) ? (string) wp_parse_url( sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) ), PHP_URL_PATH ) : '';
			$qpedia_current_path = trailingslashit( $qpedia_request_path );
			?>
			<nav class="qp-global-header__nav" aria-label="<?php esc_attr_e( 'بخش‌های اصلی', 'quantum-pedia-child' ); ?>">
				<?php
				foreach ( $qpedia_header_links as $qpedia_header_link ) :
					$qpedia_link_path = trailingslashit( (string) wp_parse_url( $qpedia_header_link['url'], PHP_URL_PATH ) );
					$qpedia_is_current = ( '' !== $qpedia_request_path && 0 === strpos( $qpedia_current_path, $qpedia_link_path ) );
					?>
					<a
						class="qp-global-header__nav-link<?php echo $qpedia_is_current ? ' is-current' : ''; ?>"
						href="<?php echo esc_url( $qpedia_header_link['url'] ); ?>"
						<?php echo $qpedia_is_current ? ' aria-current="page"' : ''; ?>
					><?php echo esc_html( $qpedia_header_link['label'] ); ?></a>
				<?php endforeach; ?>
			</nav>

			<button type="button" class="qp-global-header__search-toggle" aria-label="<?php esc_attr_e( 'جست‌وجو', 'quantum-pedia-child' ); ?>" aria-expanded="false" aria-controls="qp-search-overlay">
				<svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="11" cy="11" r="7"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg>
			</button>
		</div>

	</div>
</header>

<div id="qp-search-overlay" class="qp-search-overlay" hidden>
	<div class="qp-search-overlay__backdrop" data-qp-search-close></div>
	<div class="qp-search-overlay__panel" role="dialog" aria-modal="true" aria-label="<?php esc_attr_e( 'جست‌وجو', 'quantum-pedia-child' ); ?>">
		<form role="search" method="get" class="qp-search-overlay__form" action="<?php echo esc_url( home_url( '/' ) ); ?>">
			<span class="qp-search-overlay__icon" aria-hidden="true">
				<svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="7"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg>
			</span>
			<label class="screen-reader-text" for="qp-search-overlay-input"><?php esc_html_e( 'جست‌وجو در سایت:', 'quantum-pedia-child' ); ?></label>
			<input id="qp-search-overlay-input" type="search" name="s" class="qp-search-overlay__input" placeholder="<?php esc_attr_e( 'جست‌وجو در مقاله‌ها، اصطلاحات و دانشمندان...', 'quantum-pedia-child' ); ?>" value="<?php echo esc_attr( get_search_query() ); ?>" autocomplete="off" />
			<button type="button" class="qp-search-overlay__close" data-qp-search-close aria-label="<?php esc_attr_e( 'بستن', 'quantum-pedia-child' ); ?>">
				<svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>
			</button>
		</form>
	</div>
</div>