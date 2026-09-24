<?php
/**
 * موتور جایگزینی تصویر شاخص.
 *
 * دو حالت:
 *  ۱) نام فایل تصویر تازه = نام فایل تصویر فعلی  →  فایل سرجایش بازنویسی می‌شود
 *     (آدرس تصویر عوض نمی‌شود؛ بهترین حالت برای سئو)
 *  ۲) نام فایل متفاوت است  →  تصویر تازه با نام اسلاگ ساخته می‌شود، شاخص مقاله
 *     می‌شود و تصویر قبلی از کتابخانه و سرور حذف می‌گردد.
 *
 * @package QP_Featured_Images_Part_2
 */

defined( 'ABSPATH' ) || exit;


/**
 * متن جانشین نهایی یک ردیف.
 *
 * طبق استاندارد گوگل: توصیفی و طبیعی، زیر ۱۲۵ نویسه، شامل کلمهٔ کلیدی کانونی،
 * بدون انباشت کلیدواژه و بدون عبارت‌هایی مثل «تصویر از».
 *
 * @param array $row ردیف نگاشت.
 * @return string
 */
function qpfb_alt_for( $row ) {
	$alt = ! empty( $row['alt'] ) ? $row['alt'] : $row['title'];

	// حذف فاصله‌های اضافه.
	$alt = trim( preg_replace( '/\s+/u', ' ', $alt ) );

	// سقف ۱۲۵ نویسه (توصیهٔ گوگل) با حفظ کلمهٔ کلیدی.
	if ( function_exists( 'mb_strlen' ) && mb_strlen( $alt, 'UTF-8' ) > 125 ) {
		$alt = mb_substr( $alt, 0, 124, 'UTF-8' );
		$alt = preg_replace( '/\s+\S*$/u', '', $alt );
	}

	/**
	 * اجازهٔ تغییر متن جانشین.
	 *
	 * @param string $alt متن جانشین.
	 * @param array  $row ردیف نگاشت.
	 */
	return apply_filters( 'qpfi_alt_text', $alt, $row );
}

/**
 * یافتن مقاله از اسلاگ.
 *
 * @param string $slug اسلاگ.
 * @return WP_Post|null
 */
function qpfb_find_article( $slug ) {
	$post = get_page_by_path( $slug, OBJECT, 'quantum_article' );

	if ( $post instanceof WP_Post ) {
		return $post;
	}

	// اگر نامک مقاله عوض شده بود، از متای اسلاگ قدیمی هم امتحان کن.
	$found = get_posts(
		array(
			'post_type'      => 'quantum_article',
			'post_status'    => array( 'publish', 'draft', 'private', 'pending' ),
			'posts_per_page' => 1,
			'meta_key'       => '_wp_old_slug',
			'meta_value'     => $slug,
			'no_found_rows'  => true,
		)
	);

	return ! empty( $found ) ? $found[0] : null;
}

/**
 * تصویر شاخص فعلی یک مقاله.
 *
 * @param int $post_id شناسهٔ مقاله.
 * @return array
 */
function qpfb_current_image( $post_id ) {
	$id = (int) get_post_thumbnail_id( $post_id );

	if ( ! $id ) {
		return array(
			'id'       => 0,
			'file'     => '',
			'basename' => '',
			'url'      => '',
		);
	}

	$file = get_attached_file( $id );

	return array(
		'id'       => $id,
		'file'     => $file ? $file : '',
		'basename' => $file ? basename( $file ) : '',
		'url'      => (string) wp_get_attachment_url( $id ),
	);
}

/**
 * آیا این پیوست در مقالهٔ دیگری هم به‌عنوان تصویر شاخص استفاده شده؟
 *
 * @param int $att_id      شناسهٔ پیوست.
 * @param int $exclude_id  مقاله‌ای که کنار گذاشته می‌شود.
 * @return array فهرست شناسهٔ مقالات دیگر.
 */
function qpfb_attachment_used_elsewhere( $att_id, $exclude_id = 0 ) {
	$att_id = (int) $att_id;

	if ( ! $att_id ) {
		return array();
	}

	$users = get_posts(
		array(
			'post_type'      => 'any',
			'post_status'    => 'any',
			'posts_per_page' => 20,
			'fields'         => 'ids',
			'no_found_rows'  => true,
			'meta_query'     => array(
				array(
					'key'   => '_thumbnail_id',
					'value' => $att_id,
				),
			),
		)
	);

	$users = array_filter( array_map( 'intval', (array) $users ), function ( $id ) use ( $exclude_id ) {
		return $id !== (int) $exclude_id;
	} );

	return array_values( $users );
}

/**
 * پوشهٔ پشتیبان.
 *
 * @return string
 */
function qpfb_backup_dir() {
	$uploads = wp_upload_dir();

	return trailingslashit( $uploads['basedir'] ) . 'qp-fi-backup';
}

/**
 * ذخیرهٔ نسخهٔ پشتیبان از تصویر قبلی.
 *
 * @param string $file مسیر فایل قبلی.
 * @return string نام فایل پشتیبان یا رشتهٔ خالی.
 */
function qpfb_backup_file( $file ) {
	if ( ! $file || ! file_exists( $file ) ) {
		return '';
	}

	$dir = qpfb_backup_dir();

	if ( ! file_exists( $dir ) && ! wp_mkdir_p( $dir ) ) {
		return '';
	}

	$name = gmdate( 'Ymd' ) . '-' . basename( $file );
	$dest = trailingslashit( $dir ) . $name;

	if ( file_exists( $dest ) ) {
		$name = gmdate( 'Ymd-His' ) . '-' . basename( $file );
		$dest = trailingslashit( $dir ) . $name;
	}

	return copy( $file, $dest ) ? $name : '';
}

/**
 * حذف فایل‌های اندازه‌های میانی یک پیوست.
 *
 * @param int $att_id شناسهٔ پیوست.
 * @return void
 */
function qpfb_delete_intermediate_files( $att_id ) {
	$meta = wp_get_attachment_metadata( $att_id );

	if ( empty( $meta['sizes'] ) || ! is_array( $meta['sizes'] ) ) {
		return;
	}

	$dir = trailingslashit( dirname( (string) get_attached_file( $att_id ) ) );

	foreach ( $meta['sizes'] as $size ) {
		if ( empty( $size['file'] ) ) {
			continue;
		}

		$path = $dir . $size['file'];

		if ( file_exists( $path ) ) {
			@unlink( $path );
		}
	}
}

/**
 * ساخت پیوست تازه از فایل داخل افزونه.
 *
 * @param string  $source مسیر فایل مبدأ.
 * @param string  $dir    پوشهٔ مقصد.
 * @param string  $file   نام فایل مقصد.
 * @param WP_Post $post   مقاله.
 * @param array   $row    ردیف نگاشت.
 * @return array
 */
function qpfb_create_attachment( $source, $dir, $file, $post, $row ) {
	if ( ! wp_mkdir_p( $dir ) ) {
		return array( 0, 'ساخت پوشهٔ مقصد ممکن نشد.' );
	}

	$filename = wp_unique_filename( $dir, $file );
	$dest     = trailingslashit( $dir ) . $filename;

	if ( ! copy( $source, $dest ) ) {
		return array( 0, 'کپی فایل ممکن نشد.' );
	}

	$title = '' !== $row['title'] ? $row['title'] : $post->post_title;

	$att_id = wp_insert_attachment(
		array(
			'post_mime_type' => 'image/webp',
			'post_title'     => $title,
			'post_name'      => $row['slug'],
			'post_content'   => '',
			'post_excerpt'   => '',
			'post_status'    => 'inherit',
			'post_parent'    => $post->ID,
			'post_author'    => $post->post_author,
		),
		$dest,
		$post->ID,
		true
	);

	if ( is_wp_error( $att_id ) || ! $att_id ) {
		@unlink( $dest );

		return array( 0, is_wp_error( $att_id ) ? $att_id->get_error_message() : 'ثبت پیوست ممکن نشد.' );
	}

	require_once ABSPATH . 'wp-admin/includes/image.php';

	$meta = wp_generate_attachment_metadata( $att_id, $dest );

	if ( ! empty( $meta ) ) {
		wp_update_attachment_metadata( $att_id, $meta );
	}

	return array( (int) $att_id, '' );
}

/**
 * اجرای یک ردیف.
 *
 * @param array $row     ردیف نگاشت.
 * @param bool  $dry_run فقط بررسی.
 * @return array گزارش.
 */
function qpfb_process_row( $row, $dry_run = false ) {
	$options = qpfb_options();

	$report = array(
		'slug'   => $row['slug'],
		'title'  => $row['title'],
		'status' => 'skip',
		'old'    => '',
		'new'    => $row['file'],
		'url'    => '',
		'backup' => '',
		'note'   => '',
		'alt'    => qpfb_alt_for( $row ),
		'kw'     => $row['kw'],
	);

	$source = qpfb_image_path( $row['file'] );

	if ( ! file_exists( $source ) ) {
		$report['status'] = 'error';
		$report['note']   = 'فایل تصویر در افزونه پیدا نشد.';

		return $report;
	}

	$post = qpfb_find_article( $row['slug'] );

	if ( ! $post instanceof WP_Post ) {
		$report['status'] = 'missing';
		$report['note']   = 'مقاله با این اسلاگ پیدا نشد.';

		return $report;
	}

	$current       = qpfb_current_image( $post->ID );
	$report['old'] = $current['basename'];

	/* ── حالت ۱: بازنویسی سرجای فایل فعلی (نام یکسان) ── */
	if ( $current['id'] && '' !== $current['file'] && $current['basename'] === $row['file'] ) {
		if ( $dry_run ) {
			$report['status'] = 'overwrite';
			$report['url']    = $current['url'];
			$report['note']   = 'فایل سرجایش بازنویسی می‌شود؛ آدرس تصویر تغییر نمی‌کند.';

			return $report;
		}

		if ( ! empty( $options['backup_old'] ) ) {
			$report['backup'] = qpfb_backup_file( $current['file'] );
		}

		// حذف فایل‌های اندازه‌های قبلی تا نسخهٔ قدیمی باقی نماند.
		qpfb_delete_intermediate_files( $current['id'] );

		if ( ! copy( $source, $current['file'] ) ) {
			$report['status'] = 'error';
			$report['note']   = 'بازنویسی فایل ممکن نشد (مجوز نوشتن را بررسی کنید).';

			return $report;
		}

		@chmod( $current['file'], 0644 );

		require_once ABSPATH . 'wp-admin/includes/image.php';

		$meta = wp_generate_attachment_metadata( $current['id'], $current['file'] );

		if ( ! empty( $meta ) ) {
			wp_update_attachment_metadata( $current['id'], $meta );
		}

		if ( ! empty( $options['set_alt'] ) ) {
			update_post_meta( $current['id'], '_wp_attachment_image_alt', qpfb_alt_for( $row ) );
			update_post_meta( $current['id'], '_qpfi_kw', $row['kw'] );
			update_post_meta( $current['id'], '_qpfi_alt_source', 'qp-fi-' . QPFB_VERSION );
		}

		if ( ! empty( $row['en'] ) ) {
			update_post_meta( $current['id'], '_qpfi_en', $row['en'] );
		}

		wp_update_post(
			array(
				'ID'         => $current['id'],
				'post_title' => $row['title'],
			)
		);

		$report['status'] = 'overwritten';
		$report['url']    = $current['url'];
		$report['note']   = 'تصویر سرجای قبلی بازنویسی شد و تصویر قبلی پاک شد.';

		return $report;
	}

	/* ── حالت ۲: پیوست تازه + حذف قبلی ── */
	if ( $dry_run ) {
		$report['status'] = $current['id'] ? 'replace' : 'add';

		if ( $current['id'] ) {
			$others = qpfb_attachment_used_elsewhere( $current['id'], $post->ID );

			$report['note'] = empty( $others )
				? sprintf( 'تصویر تازه ساخته می‌شود و «%s» حذف می‌شود.', $current['basename'] )
				: sprintf( 'تصویر تازه ساخته می‌شود؛ «%s» با %s مقالهٔ دیگر مشترک است و حذف نمی‌شود.', $current['basename'], number_format_i18n( count( $others ) ) );
		} else {
			$report['note'] = 'این مقاله تصویر شاخص نداشت؛ تصویر تازه اضافه می‌شود.';
		}

		return $report;
	}

	// پوشهٔ مقصد: همان پوشهٔ تصویر قبلی، وگرنه پوشهٔ ماه جاری.
	$uploads = wp_upload_dir();
	$dir     = ( $current['file'] && file_exists( dirname( $current['file'] ) ) ) ? dirname( $current['file'] ) : $uploads['path'];

	list( $new_id, $error ) = qpfb_create_attachment( $source, $dir, $row['file'], $post, $row );

	if ( ! $new_id ) {
		$report['status'] = 'error';
		$report['note']   = $error;

		return $report;
	}

	if ( ! empty( $options['set_alt'] ) ) {
		update_post_meta( $new_id, '_wp_attachment_image_alt', qpfb_alt_for( $row ) );
		update_post_meta( $new_id, '_qpfi_kw', $row['kw'] );
		update_post_meta( $new_id, '_qpfi_alt_source', 'qp-fi-' . QPFB_VERSION );
	}

	if ( ! empty( $row['en'] ) ) {
		update_post_meta( $new_id, '_qpfi_en', $row['en'] );
	}

	set_post_thumbnail( $post->ID, $new_id );

	$report['url'] = (string) wp_get_attachment_url( $new_id );

	// حذف تصویر قبلی — مگر اینکه جای دیگری استفاده شده باشد.
	if ( $current['id'] ) {
		$others = qpfb_attachment_used_elsewhere( $current['id'], $post->ID );

		if ( empty( $others ) ) {
			if ( ! empty( $options['backup_old'] ) ) {
				$report['backup'] = qpfb_backup_file( $current['file'] );
			}

			wp_delete_attachment( $current['id'], true );

			$report['status'] = 'replaced';
			$report['note']   = sprintf( 'تصویر تازه جانشین شد و «%s» حذف شد.', $current['basename'] );
		} else {
			$report['status'] = 'replaced-kept';
			$report['note']   = sprintf( 'تصویر تازه جانشین شد؛ «%s» حذف نشد چون در %s مقالهٔ دیگر استفاده می‌شود.', $current['basename'], number_format_i18n( count( $others ) ) );
		}
	} else {
		$report['status'] = 'added';
		$report['note']   = 'تصویر شاخص اضافه شد (مقاله قبلاً تصویری نداشت).';
	}

	return $report;
}

/**
 * پاک‌سازی کش‌های مرتبط.
 *
 * @return void
 */
function qpfb_clear_caches() {
	foreach ( array( 'qpedia_glossary_terms_v1', 'qpedia_glossary_terms_v2' ) as $key ) {
		delete_transient( $key );
	}

	wp_cache_flush();
}

/**
 * فهرست پیش‌بررسی همهٔ ردیف‌ها.
 *
 * @return array
 */
function qpfb_plan() {
	$plan = array();

	foreach ( qpfb_map() as $row ) {
		$plan[] = qpfb_process_row( $row, true );
	}

	return $plan;
}

/**
 * اجرای همهٔ ردیف‌ها.
 *
 * @param array $only_slugs فقط این اسلاگ‌ها.
 * @return array
 */
function qpfb_run( $only_slugs = array() ) {
	$reports = array();

	foreach ( qpfb_map() as $row ) {
		if ( ! empty( $only_slugs ) && ! in_array( $row['slug'], $only_slugs, true ) ) {
			continue;
		}

		$reports[] = qpfb_process_row( $row, false );
	}

	qpfb_clear_caches();
	update_option( QPFB_LOG, $reports, false );

	return $reports;
}

/**
 * اجرای مرحله‌ای (دسته‌ای).
 *
 * دلیل وجود: اجرای همهٔ تصویرها در یک درخواست روی هاست‌های اشتراکی طول می‌کشد و
 * مرورگر/سرور اتصال را قطع می‌کند. این تابع در هر درخواست فقط چند مقاله را
 * پردازش می‌کند و شمارهٔ مرحلهٔ بعد را برمی‌گرداند.
 *
 * @param int $offset شروع از چندمین ردیف.
 * @param int $step   تعداد ردیف در این مرحله.
 * @return array
 */
function qpfb_run_batch( $offset, $step = 0 ) {
	$map   = array_values( qpfb_map() );
	$total = count( $map );

	// اندازهٔ مرحله خودکار تنظیم می‌شود (هاست کند ← مرحلهٔ کوچک‌تر).
	if ( $step < 1 ) {
		$step = (int) get_option( 'qpfb_batch_size', QPFB_BATCH_SIZE );
	}

	$step  = max( 1, min( 20, (int) $step ) );
	$start = microtime( true );
	$limit = 8.0; // حداکثر ثانیهٔ کار در هر درخواست (کمتر از محدودیت رایج هاست‌ها)
	$rows  = array();

	for ( $i = 0; $i < $step; $i++ ) {
		$index = (int) $offset + $i;

		if ( $index >= $total ) {
			break;
		}

		$rows[] = qpfb_process_row( $map[ $index ], false );

		// به سقف زمانی رسیدیم؟ باقی در مرحلهٔ بعد.
		if ( microtime( true ) - $start > $limit ) {
			break;
		}
	}

	$elapsed   = microtime( true ) - $start;
	$processed = count( $rows );
	$next      = (int) $offset + $processed;
	$done      = ( $next >= $total || 0 === $processed );

	// اندازهٔ مرحلهٔ بعد: کند بود کوچک‌تر، تند بود بزرگ‌تر.
	if ( $done ) {
		$new_step = QPFB_BATCH_SIZE;
	} elseif ( $elapsed > $limit && $processed > 1 ) {
		$new_step = max( 1, (int) floor( $processed * 0.6 ) );
	} elseif ( $elapsed < 2.0 && $processed === $step ) {
		$new_step = min( 12, $step + 2 );
	} else {
		$new_step = $step;
	}

	update_option( 'qpfb_batch_size', $new_step, false );

	// از مرحلهٔ اول، گزارش قبلی پاک و از نو ساخته می‌شود.
	$stored = ( 0 === (int) $offset ) ? array() : get_option( QPFB_LOG, array() );

	if ( ! is_array( $stored ) ) {
		$stored = array();
	}

	update_option( QPFB_LOG, array_merge( $stored, $rows ), false );

	if ( $done ) {
		qpfb_clear_caches();
		delete_option( 'qpfb_batch_offset' );
	} else {
		update_option( 'qpfb_batch_offset', $next, false );
	}

	return array(
		'reports'   => $rows,
		'next'      => $next,
		'total'     => $total,
		'done'      => $done,
		'step'      => $new_step,
		'processed' => $processed,
		'elapsed'   => $elapsed,
	);
}

/**
 * نشانی صفحهٔ افزونه.
 *
 * @param array $args پارامترها.
 * @return string
 */
function qpfb_page_url( $args = array() ) {
	return add_query_arg( array_merge( array( 'page' => 'qpfb' ), $args ), admin_url( 'tools.php' ) );
}
