<?php
/**
 * Global custom header for Quantum Pedia Child.
 *
 * @package Quantum_Pedia_Child
 */

defined( 'ABSPATH' ) || exit;

/**
 * دریافت درخت دسته‌های هدر با کش (transient) برای کاهش کوئری در هر بارگذاری صفحه.
 * دلیل اصلی کندی خواندن برگه‌ها: get_terms تکراری در هدر روی هر درخواست.
 */
function qpedia_get_header_nav_tree() {
	$cache_key = 'qpedia_header_nav_tree_v1';
	$cached    = get_transient( $cache_key );

	if ( false !== $cached && is_array( $cached ) ) {
		return $cached;
	}

	$short_labels = array(
		'fundamentals'        => 'مفاهیم',
		'technology'          => 'فناوری',
		'phenomena'           => 'پدیده',
		'history-experiments' => 'آزمایش',
		'interpretations'     => 'تفسیر',
		'pseudoscience'       => 'شبه علم',
	);

	$order = array(
		'fundamentals'        => 10,
		'technology'          => 20,
		'phenomena'           => 30,
		'history-experiments' => 40,
		'interpretations'     => 50,
		'pseudoscience'       => 60,
	);

	$parents = get_terms(
		array(
			'taxonomy'   => 'quantum_category',
			'hide_empty' => true,
			'parent'     => 0,
		)
	);

	$tree = array();

	if ( ! is_wp_error( $parents ) && ! empty( $parents ) ) {
		usort(
			$parents,
			static function ( $a, $b ) use ( $order ) {
				$ao = isset( $order[ $a->slug ] ) ? $order[ $a->slug ] : 999;
				$bo = isset( $order[ $b->slug ] ) ? $order[ $b->slug ] : 999;
				if ( $ao === $bo ) {
					return strcmp( $a->name, $b->name );
				}
				return $ao <=> $bo;
			}
		);

		foreach ( $parents as $parent_term ) {
			$children = get_terms(
				array(
					'taxonomy'   => 'quantum_category',
					'hide_empty' => true,
					'parent'     => $parent_term->term_id,
					'orderby'    => 'count',
					'order'      => 'DESC',
				)
			);

			$child_items = array();
			if ( ! is_wp_error( $children ) && ! empty( $children ) ) {
				foreach ( $children as $child ) {
					$child_items[] = array(
						'name' => $child->name,
						'url'  => get_term_link( $child ),
					);
				}
			}

			$tree[] = array(
				'name'       => $parent_term->name,
				'label'      => isset( $short_labels[ $parent_term->slug ] ) ? $short_labels[ $parent_term->slug ] : $parent_term->name,
				'url'        => get_term_link( $parent_term ),
				'has_children' => ! empty( $child_items ),
				'children'   => $child_items,
			);
		}
	}

	// کش ۱۲ ساعت — با تغییر دسته پاک می‌شود.
	set_transient( $cache_key, $tree, 12 * HOUR_IN_SECONDS );

	return $tree;
}

$qpedia_header_nav = qpedia_get_header_nav_tree();
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

<header id="masthead" class="qp-global-header">
	<div class="container qp-global-header__inner">
		<div class="qp-global-header__brand">
			<a class="qp-brand" href="<?php echo esc_url( home_url( '/' ) ); ?>" rel="home">
				<span class="qp-brand__logo-wrap">
					<img class="qp-brand__logo" src="<?php echo esc_url( get_stylesheet_directory_uri() . '/assets/images/qpedia-logo-neon.png' ); ?>" alt="<?php esc_attr_e( 'لوگوی کوانتوم پدیا', 'quantum-pedia-child' ); ?>" width="52" height="52" decoding="async" />
				</span>
				<span class="qp-brand__text">
					<span class="qp-brand__title">کوانتوم پدیا فارسی</span>
				</span>
			</a>
		</div>

		<nav class="qp-desktop-nav" aria-label="<?php esc_attr_e( 'Quantum categories', 'quantum-pedia-child' ); ?>">
			<?php if ( ! empty( $qpedia_header_nav ) ) : ?>
				<ul class="qp-desktop-nav__list">
					<?php foreach ( $qpedia_header_nav as $item ) : ?>
						<li class="qp-desktop-nav__item<?php echo ! empty( $item['has_children'] ) ? ' has-children' : ''; ?>">
							<div class="qp-desktop-nav__trigger-wrap">
								<a class="qp-desktop-nav__link" href="<?php echo esc_url( $item['url'] ); ?>"><?php echo esc_html( $item['label'] ); ?></a>
								<?php if ( ! empty( $item['has_children'] ) ) : ?>
									<button class="qp-desktop-nav__toggle" type="button" aria-expanded="false" aria-label="<?php echo esc_attr( sprintf( 'نمایش زیردسته‌های %s', $item['name'] ) ); ?>">
										<span aria-hidden="true">+</span>
									</button>
								<?php endif; ?>
							</div>
							<?php if ( ! empty( $item['has_children'] ) ) : ?>
								<div class="qp-desktop-nav__panel">
									<div class="qp-desktop-nav__panel-title"><?php echo esc_html( $item['name'] ); ?></div>
									<ul class="qp-desktop-nav__sublist">
										<?php foreach ( $item['children'] as $child ) : ?>
											<li><a href="<?php echo esc_url( $child['url'] ); ?>"><?php echo esc_html( $child['name'] ); ?></a></li>
										<?php endforeach; ?>
									</ul>
								</div>
							<?php endif; ?>
						</li>
					<?php endforeach; ?>
				</ul>
			<?php endif; ?>
		</nav>
	</div>
</header>
