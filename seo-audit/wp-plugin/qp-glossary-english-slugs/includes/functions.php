<?php
/**
 * هستهٔ افزونه — تطبیق، ساخت اسلاگ، جایگزینی و ۳۰۱.
 *
 * @package QP_Glossary_English_Slugs
 */

defined( 'ABSPATH' ) || exit;

/* ──────────────────────────────────────────────────────────────
   ۱. نرمال‌سازی متن فارسی
   ────────────────────────────────────────────────────────────── */

/**
 * نرمال‌سازی برای تطبیق مطمئن عنوان‌های فارسی.
 *
 * @param string $text متن.
 * @return string
 */
function qpgsl_normalize( $text ) {
	$text = (string) $text;

	// نیم‌فاصله و فاصله‌های ویژه.
	$text = str_replace( array( "\xE2\x80\x8C", "\xE2\x80\x8D", "\xC2\xA0" ), ' ', $text );

	// یکدست‌سازی حروف عربی/فارسی.
	$text = str_replace(
		array( 'ي', 'ك', 'ۀ', 'ؤ', 'إ', 'أ', 'ة', '٫', '٬' ),
		array( 'ی', 'ک', 'ه', 'و', 'ا', 'ا', 'ه', '.', ',' ),
		$text
	);

	// حذف اعراب.
	$text = preg_replace( '/[\x{064B}-\x{065F}\x{0670}\x{06D6}-\x{06ED}]/u', '', $text );

	// یکدست‌سازی خط تیره‌ها.
	$text = str_replace( array( '–', '—', '−', '‐', '‑' ), '-', $text );

	// حذف نشانه‌ها و نمادها (اعداد و حروف می‌مانند).
	$text = preg_replace( '/[\p{P}\p{S}]+/u', ' ', $text );
	$text = preg_replace( '/\s+/u', ' ', $text );
	$text = trim( (string) $text );

	return function_exists( 'mb_strtolower' ) ? mb_strtolower( $text, 'UTF-8' ) : strtolower( $text );
}

/* ──────────────────────────────────────────────────────────────
   ۲. فهرست فرهنگ برای تطبیق
   ────────────────────────────────────────────────────────────── */

/**
 * ساخت فهرست جست‌وجوی فرهنگ (کلید نرمال‌شده → ردیف).
 *
 * @return array
 */
function qpgsl_dictionary_index() {
	static $index = null;

	if ( null !== $index ) {
		return $index;
	}

	$index = array(
		'fa'  => array(),
		'old' => array(),
	);

	foreach ( qpgsl_dictionary() as $entry ) {
		if ( empty( $entry['fa'] ) ) {
			continue;
		}

		$index['fa'][ qpgsl_normalize( $entry['fa'] ) ] = $entry;

		if ( ! empty( $entry['en'] ) ) {
			$index['fa'][ qpgsl_normalize( $entry['en'] ) ] = $entry;
		}

		if ( ! empty( $entry['old'] ) ) {
			$index['old'][ strtolower( $entry['old'] ) ] = $entry;
		}

		if ( ! empty( $entry['slug'] ) ) {
			$index['old'][ strtolower( $entry['slug'] ) ] = $entry;
		}
	}

	return $index;
}

/* ──────────────────────────────────────────────────────────────
   ۳. تطبیق یک مدخل
   ────────────────────────────────────────────────────────────── */

/**
 * یافتن نام و اسلاگ انگلیسی برای یک مدخل واژه‌نامه.
 *
 * ترتیب اولویت: نگاشت دستی (با شناسه یا اسلاگ) → اسلاگ/نام انگلیسی فعلی →
 * عنوان فارسی نرمال‌شده → اسلاگ ترانسلیریت شناخته‌شده.
 *
 * @param WP_Post $post مدخل.
 * @return array|null آرایه‌ای با کلیدهای en و slug و gloss.
 */
function qpgsl_resolve( $post ) {
	if ( ! $post instanceof WP_Post ) {
		return null;
	}

	$overrides = qpgsl_overrides();
	$current   = strtolower( urldecode( (string) $post->post_name ) );

	// ۱) نگاشت دستی ذخیره‌شده.
	if ( isset( $overrides[ $post->ID ] ) && ! empty( $overrides[ $post->ID ]['en'] ) ) {
		$row = $overrides[ $post->ID ];

		return array(
			'en'    => (string) $row['en'],
			'slug'  => ! empty( $row['slug'] ) ? sanitize_title( $row['slug'] ) : sanitize_title( $row['en'] ),
			'gloss' => isset( $row['gloss'] ) ? (string) $row['gloss'] : '',
			'source' => 'manual',
		);
	}

	$index = qpgsl_dictionary_index();

	// ۲) متای نام انگلیسی که قبلاً ثبت شده.
	$en_meta = trim( (string) get_post_meta( $post->ID, '_qp_term_en', true ) );
	if ( '' !== $en_meta ) {
		$found = isset( $index['fa'][ qpgsl_normalize( $en_meta ) ] ) ? $index['fa'][ qpgsl_normalize( $en_meta ) ] : null;

		return array(
			'en'     => $found ? $found['en'] : $en_meta,
			'slug'   => $found ? $found['slug'] : sanitize_title( $en_meta ),
			'gloss'  => $found && ! empty( $found['gloss'] ) ? $found['gloss'] : '',
			'source' => $found ? 'meta+dictionary' : 'meta',
		);
	}

	// ۳) عنوان فارسی.
	$title = qpgsl_normalize( $post->post_title );
	if ( isset( $index['fa'][ $title ] ) ) {
		$found = $index['fa'][ $title ];

		return array(
			'en'     => $found['en'],
			'slug'   => $found['slug'],
			'gloss'  => isset( $found['gloss'] ) ? $found['gloss'] : '',
			'source' => 'title',
		);
	}

	// ۴) اسلاگ فعلی (ترانسلیریت یا انگلیسی).
	if ( '' !== $current && isset( $index['old'][ $current ] ) ) {
		$found = $index['old'][ $current ];

		return array(
			'en'     => $found['en'],
			'slug'   => $found['slug'],
			'gloss'  => isset( $found['gloss'] ) ? $found['gloss'] : '',
			'source' => 'slug',
		);
	}

	// ۵) عنوان با حذف پسوند/پیشوندهای عمومی.
	$stripped = preg_replace( '/\b(کوانتومی|کوانتوم)\b/u', '', $title );	$stripped = trim( preg_replace( '/\s+/u', ' ', (string) $stripped ) );

	if ( '' !== $stripped && $stripped !== $title && isset( $index['fa'][ $stripped ] ) ) {
		$found = $index['fa'][ $stripped ];

		return array(
			'en'     => $found['en'],
			'slug'   => $found['slug'],
			'gloss'  => isset( $found['gloss'] ) ? $found['gloss'] : '',
			'source' => 'title-stripped',
		);
	}

	return null;
}

/**
 * اسلاگ لاتین است؟
 *
 * @param string $slug اسلاگ.
 * @return bool
 */
function qpgsl_is_latin_slug( $slug ) {
	return (bool) preg_match( '/^[a-z0-9][a-z0-9\-]*$/', urldecode( (string) $slug ) );
}

/* ──────────────────────────────────────────────────────────────
   ۴. جایگزینی اسلاگ
   ────────────────────────────────────────────────────────────── */

/**
 * ثبت اسلاگ‌های قبلی تا ۳۰۱ ساخته شود.
 *
 * @param WP_Post $post      مدخل.
 * @param string  $old_slug  اسلاگ پیشین (خام).
 * @return void
 */
function qpgsl_remember_old_slugs( $post, $old_slug ) {
	$existing = (array) get_post_meta( $post->ID, '_wp_old_slug' );

	$candidates = array(
		$old_slug,
		urldecode( (string) $old_slug ),
		sanitize_title( $post->post_title ),
		urldecode( (string) sanitize_title( $post->post_title ) ),
		rawurlencode( urldecode( (string) $old_slug ) ),
	);

	$candidates = array_filter( array_unique( array_map( 'strval', $candidates ) ) );

	foreach ( $candidates as $candidate ) {
		if ( '' === $candidate || strlen( $candidate ) > 200 ) {
			continue;
		}

		$known = false;
		foreach ( $existing as $row ) {
			if ( 0 === strcasecmp( (string) $row, $candidate ) ) {
				$known = true;
				break;
			}
		}

		if ( ! $known ) {
			add_post_meta( $post->ID, '_wp_old_slug', $candidate );
			$existing[] = $candidate;
		}
	}
}

/**
 * پاک‌سازی کش واژه‌نامه در قالب.
 *
 * @return void
 */
function qpgsl_clear_caches() {
	foreach ( array( 'qpedia_glossary_terms_v1', 'qpedia_glossary_terms_v2', 'qpedia_glossary_archive_index_v1' ) as $key ) {
		delete_transient( $key );
	}

	if ( function_exists( 'qpedia_glossary_clear_cache' ) ) {
		qpedia_glossary_clear_cache();
	}
}

/**
 * جایگزینی اسلاگ یک مدخل.
 *
 * @param WP_Post $post    مدخل.
 * @param array   $options گزینه‌های اجرا.
 * @return array گزارش.
 */
function qpgsl_process_post( $post, $options = array() ) {
	$options = array_merge( qpgsl_options(), is_array( $options ) ? $options : array() );

	$report = array(
		'id'     => (int) $post->ID,
		'title'  => $post->post_title,
		'en'     => '',
		'gloss'  => '',
		'old'    => urldecode( (string) $post->post_name ),
		'new'    => '',
		'status' => 'unmatched',
		'note'   => '',
	);

	$match = qpgsl_resolve( $post );

	if ( ! $match || empty( $match['slug'] ) ) {
		$report['note'] = 'در فرهنگ نگاشت پیدا نشد — نام انگلیسی را دستی وارد کنید.';
		return $report;
	}

	$report['en']    = $match['en'];
	$report['gloss'] = isset( $match['gloss'] ) ? $match['gloss'] : '';

	$old_slug = (string) $post->post_name;
	$new_slug = sanitize_title( $match['slug'] );

	if ( '' === $new_slug ) {
		$report['note'] = 'اسلاگ انگلیسی معتبر ساخته نشد.';
		return $report;
	}

	$new_slug = wp_unique_post_slug( $new_slug, $post->ID, $post->post_status, $post->post_type, $post->post_parent );
	$report['new'] = $new_slug;

	$already_latin = qpgsl_is_latin_slug( $old_slug );

	// ذخیرهٔ نام و معنی انگلیسی.
	if ( ! empty( $options['set_en_meta'] ) ) {
		$current_en = (string) get_post_meta( $post->ID, '_qp_term_en', true );
		if ( '' === $current_en || $current_en !== $match['en'] ) {
			update_post_meta( $post->ID, '_qp_term_en', $match['en'] );
		}
	}

	if ( ! empty( $options['save_gloss'] ) && ! empty( $match['gloss'] ) ) {
		$current_gloss = (string) get_post_meta( $post->ID, '_qp_term_en_gloss', true );
		if ( '' === $current_gloss ) {
			update_post_meta( $post->ID, '_qp_term_en_gloss', $match['gloss'] );
		}
	}

	// اگر اسلاگ لاتین است و کاربر گفته دست نزن، فقط متا ثبت می‌شود.
	if ( $already_latin && empty( $options['update_latin'] ) && $new_slug !== $old_slug ) {
		$report['status'] = 'meta-only';
		$report['note']   = 'اسلاگ لاتین فعلی دست‌نخورده ماند (طبق تنظیمات).';
		return $report;
	}

	if ( $new_slug === $old_slug ) {
		$report['status'] = 'same';
		$report['note']   = 'اسلاگ از قبل درست بود.';
		return $report;
	}

	// ذخیرهٔ تاریخچهٔ اسلاگ‌ها پیش از تغییر.
	if ( ! empty( $options['keep_old_301'] ) ) {
		qpgsl_remember_old_slugs( $post, $old_slug );
	}

	$updated = wp_update_post(
		array(
			'ID'        => $post->ID,
			'post_name' => $new_slug,
		),
		true
	);

	if ( is_wp_error( $updated ) ) {
		$report['status'] = 'error';
		$report['note']   = $updated->get_error_message();
		return $report;
	}

	// هستهٔ وردپرس معمولاً خودش ضبط می‌کند؛ برای اطمینان دوباره ثبت می‌کنیم.
	if ( ! empty( $options['keep_old_301'] ) ) {
		qpgsl_remember_old_slugs( get_post( $post->ID ), $old_slug );
	}

	$report['status'] = 'renamed';
	$report['note']   = sprintf( '%s ← %s', $old_slug, $new_slug );

	return $report;
}

/**
 * فهرست همهٔ مدخل‌های واژه‌نامه.
 *
 * @param string $status وضعیت پست.
 * @return WP_Post[]
 */
function qpgsl_get_posts( $status = 'any' ) {
	$posts = array();

	foreach ( qpgsl_post_types() as $type ) {
		$found = get_posts(
			array(
				'post_type'              => $type,
				'post_status'            => 'any' === $status ? array( 'publish', 'draft', 'pending', 'private', 'future' ) : $status,
				'posts_per_page'         => 1000,
				'orderby'                => 'title',
				'order'                  => 'ASC',
				'no_found_rows'          => true,
				'update_post_term_cache' => false,
				'suppress_filters'       => false,
			)
		);

		if ( ! empty( $found ) ) {
			$posts = array_merge( $posts, $found );
		}
	}

	return $posts;
}

/**
 * آمار کلی برای داشبورد.
 *
 * @return array
 */
function qpgsl_stats() {
	$posts = qpgsl_get_posts();

	$stats = array(
		'total'     => count( $posts ),
		'persian'   => 0,
		'latin'     => 0,
		'matched'   => 0,
		'unmatched' => 0,
		'ready'     => 0,
	);

	foreach ( $posts as $post ) {
		$slug = urldecode( (string) $post->post_name );

		if ( '' !== $slug && qpgsl_is_latin_slug( $slug ) ) {
			$stats['latin']++;
		} else {
			$stats['persian']++;
		}

		$match = qpgsl_resolve( $post );

		if ( $match ) {
			$stats['matched']++;
			if ( $match['slug'] !== $post->post_name ) {
				$stats['ready']++;
			}
		} else {
			$stats['unmatched']++;
		}
	}

	return $stats;
}
