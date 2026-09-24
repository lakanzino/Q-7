<?php
/**
 * ریدایرکت ۳۰۱ آدرس‌های قبلی اصطلاح‌ها به اسلاگ انگلیسی تازه.
 *
 * - /glossary/<اسلاگ قدیمی>/ ، /terms/<...>/ ، /vocabulary/<...>/
 * - اسلاگ فارسی (درصدکد شده یا خام) و اسلاگ ترانسلیریت قبلی
 * - مستقل از قالب؛ فقط وقتی صفحه واقعاً ۴۰۴ است اجرا می‌شود
 *
 * @package QP_Glossary_English_Slugs
 */

defined( 'ABSPATH' ) || exit;

/**
 * بخش‌های مجاز ابتدای مسیر واژه‌نامه.
 *
 * @return string[]
 */
function qpgsl_base_segments() {
	$bases = array( 'glossary', 'great', 'terms', 'term', 'vocabulary' );

	if ( defined( 'QPEDIA_GLOSSARY_BASE' ) ) {
		$bases[] = QPEDIA_GLOSSARY_BASE;
	}

	return array_unique( array_filter( $bases ) );
}

/**
 * ریدایرکت ۳۰۱ سبک — پیش از تصمیم‌گیری وردپرس دربارهٔ ۴۰۴.
 *
 * @return void
 */
function qpgsl_old_slug_redirect() {
	if ( is_admin() || wp_doing_ajax() || ! is_404() ) {
		return;
	}

	$request = isset( $_SERVER['REQUEST_URI'] ) ? wp_unslash( $_SERVER['REQUEST_URI'] ) : '';

	if ( '' === $request ) {
		return;
	}

	$path = (string) wp_parse_url( $request, PHP_URL_PATH );
	$path = trim( $path, '/' );

	if ( '' === $path ) {
		return;
	}

	$segments = array_values( array_filter( explode( '/', $path ) ) );

	if ( empty( $segments ) ) {
		return;
	}

	$last = (string) end( $segments );

	// نامزدهای تطبیق: خام، درصدکدگشایی‌شده و سالم‌سازی‌شده.
	$candidates = array( $last, rawurldecode( $last ), urldecode( $last ), sanitize_title( rawurldecode( $last ) ) );
	$candidates = array_values( array_unique( array_filter( array_map( 'strval', $candidates ) ) ) );

	$post = null;

	// ۱) تطبیق با اسلاگ‌های قبلی ثبت‌شده.
	foreach ( $candidates as $candidate ) {
		$post = qpgsl_find_by_old_slug( $candidate );

		if ( $post instanceof WP_Post ) {
			break;
		}
	}

	// ۲) اگر مسیر با پایهٔ واژه‌نامه شروع شده، شاید خود اسلاگ فعلی درصدکد شده باشد.
	if ( ! $post instanceof WP_Post && count( $segments ) >= 2 ) {
		$base = strtolower( $segments[0] );

		if ( in_array( $base, qpgsl_base_segments(), true ) ) {
			foreach ( qpgsl_post_types() as $type ) {
				$found = get_page_by_path( rawurldecode( $last ), OBJECT, $type );

				if ( $found instanceof WP_Post && 'publish' === $found->post_status ) {
					$post = $found;
					break;
				}
			}
		}
	}

	// ۳) تطبیق با نگاشت انگلیسی: اگر مسیر همان اسلاگ تازهٔ انگلیسی است، ۴۰۴ نیست؛ ولی
	//    حالت‌هایی مثل تفاوت بزرگی/کوچکی حروف یا خط تیره را همین‌جا می‌گیریم.
	if ( ! $post instanceof WP_Post ) {
		$needle = strtolower( rawurldecode( $last ) );

		foreach ( qpgsl_get_posts( 'publish' ) as $candidate_post ) {
			if ( strtolower( $candidate_post->post_name ) === $needle ) {
				$post = $candidate_post;
				break;
			}
		}
	}

	if ( ! $post instanceof WP_Post || 'publish' !== $post->post_status ) {
		return;
	}

	// اگر آدرس فعلی همان آدرس درست است، کاری لازم نیست.
	$target = get_permalink( $post );
	$target_path = trim( (string) wp_parse_url( $target, PHP_URL_PATH ), '/' );

	if ( $target_path === $path ) {
		return;
	}

	/**
	 * اجازهٔ تغییر مقصد ریدایرکت.
	 *
	 * @param string  $target مقصد.
	 * @param WP_Post $post   مدخل.
	 * @param string  $path   مسیر درخواست.
	 */
	$target = apply_filters( 'qpgsl_redirect_target', $target, $post, $path );

	wp_safe_redirect( $target, 301, 'Qpedia Glossary English Slugs' );
	exit;
}
add_action( 'template_redirect', 'qpgsl_old_slug_redirect', 4 );

/**
 * یافتن مدخل با اسلاگ قدیمی (با تحمل تفاوت کدگذاری).
 *
 * @param string $slug نامزد.
 * @return WP_Post|null
 */
function qpgsl_find_by_old_slug( $slug ) {
	global $wpdb;

	$slug = (string) $slug;

	if ( '' === $slug || strlen( $slug ) > 200 ) {
		return null;
	}

	$types = qpgsl_post_types();

	$ids = $wpdb->get_col(
		$wpdb->prepare(
			"SELECT pm.post_id FROM {$wpdb->postmeta} pm
			 INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
			 WHERE pm.meta_key = '_wp_old_slug'
			 AND ( pm.meta_value = %s OR pm.meta_value = %s )
			 AND p.post_type IN ('" . implode( "','", array_map( 'esc_sql', $types ) ) . "')
			 AND p.post_status = 'publish'
			 LIMIT 1",
			$slug,
			urldecode( $slug )
		)
	);

	if ( empty( $ids ) ) {
		return null;
	}

	$post = get_post( (int) $ids[0] );

	return ( $post instanceof WP_Post ) ? $post : null;
}

/**
 * اگر وردپرس خودش ۳۰۱ ساخت، آدرس‌های قدیمی واژه‌نامه را از حدس ۴۰۴ کنار بگذار.
 *
 * @param bool     $preempt  مقدار پیش‌دست.
 * @param WP_Query $wp_query کوئری اصلی.
 * @return bool
 */
function qpgsl_pre_handle_404( $preempt, $wp_query ) {
	if ( true === $preempt || is_admin() ) {
		return $preempt;
	}

	if ( ! $wp_query instanceof WP_Query || ! $wp_query->is_404 ) {
		return $preempt;
	}

	$request = isset( $_SERVER['REQUEST_URI'] ) ? wp_unslash( $_SERVER['REQUEST_URI'] ) : '';
	$path    = trim( (string) wp_parse_url( $request, PHP_URL_PATH ), '/' );

	if ( '' === $path ) {
		return $preempt;
	}

	$segments = array_values( array_filter( explode( '/', $path ) ) );

	if ( count( $segments ) < 2 ) {
		return $preempt;
	}

	if ( ! in_array( strtolower( $segments[0] ), qpgsl_base_segments(), true ) ) {
		return $preempt;
	}

	// در این حالت به تابع ریدایرکت خودمان فرصت می‌دهیم و از حدس خودکار وردپرس جلوگیری می‌کنیم.
	remove_action( 'template_redirect', 'redirect_guess_404_permalink' );
	remove_filter( 'pre_handle_404', 'redirect_guess_404_permalink' );

	return $preempt;
}
add_filter( 'pre_handle_404', 'qpgsl_pre_handle_404', 9, 2 );
