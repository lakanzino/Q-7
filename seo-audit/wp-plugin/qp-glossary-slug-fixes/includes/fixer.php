<?php
/**
 * موتور اصلاح — فقط مدخل‌های فهرست را جابه‌جا می‌کند.
 *
 * @package QP_Glossary_Slug_Fixes
 */

defined( 'ABSPATH' ) || exit;

/**
 * یافتن مدخل متناظر یک ردیف فهرست.
 *
 * تطبیق به دو روش: شناسه، و (اگر شناسه عوض شده بود) اسلاگ فعلی.
 *
 * @param array $row ردیف فهرست.
 * @return WP_Post|null
 */
function qpgsf_find_post( $row ) {
	$id = (int) $row['id'];

	if ( $id ) {
		$post = get_post( $id );

		if ( $post instanceof WP_Post && in_array( $post->post_type, qpgsf_post_types(), true ) ) {
			return $post;
		}
	}

	// شناسه عوض شده — با اسلاگ فعلی پیدا کن.
	$slug = strtolower( urldecode( (string) $row['old'] ) );

	foreach ( qpgsf_post_types() as $type ) {
		$found = get_page_by_path( $row['old'], OBJECT, $type );

		if ( ! $found instanceof WP_Post ) {
			$found = get_page_by_path( $slug, OBJECT, $type );
		}

		if ( $found instanceof WP_Post ) {
			return $found;
		}
	}

	return null;
}

/**
 * وضعیت یک ردیف.
 *
 * @param array $row ردیف.
 * @return array
 */
function qpgsf_row_status( $row ) {
	$post = qpgsf_find_post( $row );

	if ( ! $post instanceof WP_Post ) {
		return array( 'missing', 'پیدا نشد', '#b32d2e', null );
	}

	$current = urldecode( (string) $post->post_name );

	if ( $current === $row['new'] ) {
		return array( 'done', 'قبلاً اصلاح شده', '#008a20', $post );
	}

	if ( $current === $row['old'] ) {
		return array( 'ready', 'آمادهٔ اصلاح', '#2271b1', $post );
	}

	// اسلاگ چیز دیگری است؛ اگر لاتین و معنادار باشد دست نمی‌زنیم.
	return array( 'changed', 'اسلاگ تغییر کرده: ' . $current, '#996800', $post );
}

/**
 * اصلاح یک ردیف.
 *
 * @param array $row     ردیف فهرست.
 * @param bool  $dry_run فقط بررسی.
 * @return array گزارش.
 */
function qpgsf_fix_row( $row, $dry_run = false ) {
	$status = qpgsf_row_status( $row );
	$post   = $status[3];

	$report = array(
		'id'     => (int) $row['id'],
		'title'  => $row['title'],
		'old'    => $row['old'],
		'new'    => $row['new'],
		'en'     => $row['en'],
		'status' => $status[0],
		'note'   => '',
	);

	if ( ! $post instanceof WP_Post ) {
		$report['note'] = 'مدخل در سایت پیدا نشد — رد شد.';
		return $report;
	}

	if ( 'done' === $status[0] ) {
		$report['note'] = 'از قبل درست است.';
		return $report;
	}

	if ( 'changed' === $status[0] ) {
		$report['note'] = 'اسلاگ فعلی با اسلاگ مورد انتظار فهرست نمی‌خواند؛ برای امنیت دست نزدم.';
		return $report;
	}

	// نام انگلیسی.
	if ( ! empty( $row['en'] ) ) {
		$current_en = trim( (string) get_post_meta( $post->ID, '_qp_term_en', true ) );

		if ( $current_en !== $row['en'] && ! $dry_run ) {
			update_post_meta( $post->ID, '_qp_term_en', $row['en'] );
		}
	}

	$new_slug = sanitize_title( $row['new'] );
	$old_slug = (string) $post->post_name;

	if ( $new_slug === urldecode( $old_slug ) ) {
		$report['status'] = 'done';
		$report['note']   = 'اسلاگ از قبل درست بود؛ فقط نام انگلیسی ثبت شد.';
		return $report;
	}

	if ( $dry_run ) {
		$report['note'] = sprintf( '%s ← %s (آزمایشی)', $row['old'], $new_slug );
		return $report;
	}

	// ثبت آدرس‌های قبلی برای ۳۰۱.
	qpgsf_remember_old_slug( $post, $old_slug );
	qpgsf_remember_old_slug( $post, $row['old'] );

	$unique = wp_unique_post_slug( $new_slug, $post->ID, $post->post_status, $post->post_type, $post->post_parent );

	$updated = wp_update_post(
		array(
			'ID'        => $post->ID,
			'post_name' => $unique,
		),
		true
	);

	if ( is_wp_error( $updated ) ) {
		$report['status'] = 'error';
		$report['note']   = $updated->get_error_message();
		return $report;
	}

	qpgsf_remember_old_slug( get_post( $post->ID ), $old_slug );
	qpgsf_remember_old_slug( get_post( $post->ID ), $row['old'] );

	$report['status'] = 'fixed';
	$report['note']   = ( $unique === $new_slug )
		? sprintf( '%s ← %s', $old_slug, $unique )
		: sprintf( '%s ← %s (اسلاگ از قبل اشغال بود)', $old_slug, $unique );

	return $report;
}

/**
 * ثبت اسلاگ قدیمی برای ریدایرکت ۳۰۱ هستهٔ وردپرس.
 *
 * @param WP_Post $post     مدخل.
 * @param string  $old_slug اسلاگ قدیمی.
 * @return void
 */
function qpgsf_remember_old_slug( $post, $old_slug ) {
	if ( ! $post instanceof WP_Post ) {
		return;
	}

	$old_slug = (string) $old_slug;

	if ( '' === $old_slug ) {
		return;
	}

	$candidates = array_unique(
		array_filter(
			array(
				$old_slug,
				urldecode( $old_slug ),
				rawurlencode( urldecode( $old_slug ) ),
				sanitize_title( $post->post_title ),
				urldecode( (string) sanitize_title( $post->post_title ) ),
			)
		)
	);

	$existing = (array) get_post_meta( $post->ID, '_wp_old_slug' );

	foreach ( $candidates as $candidate ) {
		if ( '' === $candidate || strlen( $candidate ) > 200 ) {
			continue;
		}

		$found = false;

		foreach ( $existing as $known ) {
			if ( 0 === strcasecmp( (string) $known, $candidate ) ) {
				$found = true;
				break;
			}
		}

		if ( ! $found ) {
			add_post_meta( $post->ID, '_wp_old_slug', $candidate );
			$existing[] = $candidate;
		}
	}
}

/**
 * پاک‌سازی کش‌های واژه‌نامه.
 *
 * @return void
 */
function qpgsf_clear_caches() {
	foreach ( array( 'qpedia_glossary_terms_v1', 'qpedia_glossary_terms_v2', 'qpedia_glossary_archive_index_v1' ) as $key ) {
		delete_transient( $key );
	}

	if ( function_exists( 'qpedia_glossary_clear_cache' ) ) {
		qpedia_glossary_clear_cache();
	}
}

/**
 * اجرای دسته‌ای.
 *
 * @param int   $offset  شروع.
 * @param int   $limit   تعداد.
 * @param bool  $dry_run آزمایشی.
 * @param array $only_ids فقط این شناسه‌ها.
 * @return array
 */
function qpgsf_run_batch( $offset, $limit, $dry_run = false, $only_ids = array() ) {
	$list = qpgsf_fix_list();

	if ( ! empty( $only_ids ) ) {
		$list = array_values(
			array_filter(
				$list,
				function ( $row ) use ( $only_ids ) {
					return in_array( (int) $row['id'], $only_ids, true );
				}
			)
		);
	}

	$total = count( $list );
	$slice = array_slice( $list, $offset, $limit );
	$out   = array();

	foreach ( $slice as $row ) {
		$out[] = $q = qpgsf_fix_row( $row, $dry_run );

		if ( ! $dry_run && in_array( $q['status'], array( 'fixed', 'done', 'error' ), true ) ) {
			$log   = get_option( QPGSF_LOG_OPTION, array() );
			$log   = is_array( $log ) ? $log : array();
			$log[ (int) $row['id'] ] = $q;
			update_option( QPGSF_LOG_OPTION, $log, false );
		}
	}

	return array(
		'reports' => $out,
		'offset'  => $offset,
		'limit'   => $limit,
		'total'   => $total,
		'next'    => $offset + $limit,
		'has_more' => ( $offset + $limit ) < $total,
	);
}

/**
 * نشانی صفحهٔ افزونه.
 *
 * @param array $args پارامترها.
 * @return string
 */
function qpgsf_page_url( $args = array() ) {
	$parent = post_type_exists( 'qp_glossary' ) ? 'edit.php?post_type=qp_glossary' : 'tools.php';

	return add_query_arg( array_merge( array( 'page' => 'qpgsf' ), $args ), admin_url( $parent ) );
}
