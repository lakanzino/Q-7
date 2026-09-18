<?php
/**
 * Plugin Name: QPedia — تصاویر شاخص ۳۶ مقاله (WebP)
 * Description: ۳۶ تصویر ۱۶:۹ وب‌پی را به کتابخانهٔ رسانه وارد می‌کند، ALT فارسی می‌نویسد و با یک دکمه در پیشخوان، تصویر شاخص مقاله را اجباراً جایگزین می‌کند. هر آیتم جدا تیک می‌خورد.
 * Version: 1.0.0
 * Author: QPedia
 * License: GPL-2.0-or-later
 * Text Domain: qpedia-featured-images
 *
 * چرا؟ نگاشتِ تصویر شاخص در ایمپورت قبلی خراب بود (شناسۀ پیوست ≠ شناسۀ واقعی) و هیچ
 * ALT فارسی‌ای ثبت نشده بود. این افزونه هر ۳۶ تصویر را با نام انگلیسیِ همان اسلاگ
 * وارد می‌کند، ALT/عنوان/توضیح می‌نویسد و تصویر شاخص را (حتی اگر چیزی ست شده باشد)
 * جایگزین می‌کند. همه‌چیز از داخل خود افزونه خوانده می‌شود؛ نیازی به آپلود جدا نیست.
 *
 * پس از نصب: پیشخوان → «تصاویر شاخص QPedia» → «واردکردن و جایگذاری همه» — یا دانه‌دانه
 * با دکمۀ هر ردیف. تیک‌ها در گزینهٔ qpedia_fi_state می‌مانند و با Sync بازسازی می‌شوند.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'QPEDIA_FI_VERSION', '1.0.0' );
define( 'QPEDIA_FI_DIR', plugin_dir_path( __FILE__ ) );
define( 'QPEDIA_FI_URL', plugin_dir_url( __FILE__ ) );
define( 'QPEDIA_FI_STATE', 'qpedia_fi_state' );
define( 'QPEDIA_FI_CAP', 'manage_options' );

/* ─── داده ──────────────────────────────────────────────────────── */

/** نقشۀ ۳۶ تصویر (اسلاگ ⇒ فایل، ALT، عنوان، توضیح). */
function qpedia_fi_items() {
	static $items = null;

	if ( null === $items ) {
		$map   = QPEDIA_FI_DIR . 'qpedia-map.php';
		$items = is_readable( $map ) ? (array) include $map : array();
	}

	return $items;
}

/** شمارش نویسه (برای گزارش طول ALT). */
function qpedia_fi_len( $text ) {
	if ( function_exists( 'mb_strlen' ) ) {
		return (int) mb_strlen( (string) $text, 'UTF-8' );
	}
	return (int) strlen( (string) $text );
}

/** وضعیت تیک‌خورده‌ها: اسلاگ ⇒ array( attachment, post, replaced, time ) */
function qpedia_fi_state() {
	$state = get_option( QPEDIA_FI_STATE, array() );
	return is_array( $state ) ? $state : array();
}

/** مقالهٔ هدف را با اسلاگ پیدا می‌کند (اول quantum_article، هر نوع دیگری بعد). */
function qpedia_fi_target( $slug ) {
	$page = get_page_by_path( $slug, OBJECT, 'quantum_article' );
	if ( $page instanceof WP_Post ) {
		return (int) $page->ID;
	}

	$q = new WP_Query(
		array(
			'name'          => $slug,
			'post_type'     => array( 'quantum_article', 'post', 'page' ),
			'post_status'   => 'any',
			'posts_per_page'=> 1,
			'fields'        => 'ids',
			'no_found_rows' => true,
		)
	);

	return $q->posts ? (int) $q->posts[0] : 0;
}

/** پیوستی که قبلاً برای همین اسلاگ ساخته‌ایم (یا همنامِ فایلش). */
function qpedia_fi_find_attachment( $slug ) {
	$q = new WP_Query(
		array(
			'post_type'      => 'attachment',
			'post_status'    => 'inherit',
			'meta_key'       => '_qpedia_img_slug',
			'meta_value'     => $slug,
			'posts_per_page' => 1,
			'fields'         => 'ids',
			'no_found_rows'  => true,
		)
	);

	if ( ! empty( $q->posts ) ) {
		return (int) $q->posts[0];
	}

	foreach ( array( $slug . '-qp', $slug ) as $name ) {
		$q2 = new WP_Query(
			array(
				'post_type'      => 'attachment',
				'post_status'    => 'inherit',
				'name'           => $name,
				'posts_per_page' => 1,
				'fields'         => 'ids',
				'no_found_rows'  => true,
			)
		);
		if ( ! empty( $q2->posts ) ) {
			return (int) $q2->posts[0];
		}
	}

	return 0;
}

/* ─── کار اصلی ──────────────────────────────────────────────────── */

/** یک پیوست تازه از فایل داخل افزونه می‌سازد. */
function qpedia_fi_create_attachment( $slug, $item, $file ) {
	require_once ABSPATH . 'wp-admin/includes/file.php';
	require_once ABSPATH . 'wp-admin/includes/media.php';
	require_once ABSPATH . 'wp-admin/includes/image.php';

	$upload = wp_upload_dir();
	if ( ! empty( $upload['error'] ) || empty( $upload['path'] ) || ! is_dir( $upload['path'] ) ) {
		return new WP_Error( 'qp_fi_upload', 'پوشۀ آپلود در دسترس نیست: ' . ( isset( $upload['error'] ) ? $upload['error'] : '' ) );
	}

	$dest = trailingslashit( $upload['path'] ) . $slug . '-qp.webp';
	if ( ! copy( $file, $dest ) ) {
		return new WP_Error( 'qp_fi_copy', 'کپی تصویر به ' . $dest . ' ممکن نشد (مجوز پوشه؟)' );
	}
	clearstatcache( true, $dest );

	$att = wp_insert_attachment(
		array(
			'post_type'      => 'attachment',
			'post_status'    => 'inherit',
			'post_mime_type' => 'image/webp',
			'post_title'     => $item['title'],
			'post_excerpt'   => $item['caption'],
			'post_content'   => $item['caption'],
			'post_name'      => sanitize_title( $slug . '-qp' ),
		),
		$dest
	);

	if ( is_wp_error( $att ) ) {
		return $att;
	}
	if ( ! $att ) {
		return new WP_Error( 'qp_fi_insert', 'درج پیوست بی‌نتیجه ماند' );
	}

	$att = (int) $att;

	qpedia_fi_meta( $att, $item, $slug );

	return $att;
}

/** متای تصویر: ALT، اسلاگ، اندازه‌ها. */
function qpedia_fi_meta( $att, $item, $slug ) {
	update_post_meta( $att, '_wp_attachment_image_alt', $item['alt'] );
	update_post_meta( $att, '_qpedia_img_slug', $slug );
	update_post_meta( $att, '_qpedia_fi_ver', QPEDIA_FI_VERSION );

	$file = get_attached_file( $att );
	if ( $file && file_exists( $file ) && function_exists( 'wp_generate_attachment_metadata' ) ) {
		wp_update_attachment_metadata( $att, wp_generate_attachment_metadata( $att, $file ) );
	}
}

/**
 * یک آیتم را کامل اعمال می‌کند: واردکردن پیوست + ALT + جایگذاری اجباری تصویر شاخص.
 *
 * @return array|WP_Error array( attachment, post, replaced )
 */
function qpedia_fi_apply( $slug ) {
	$items = qpedia_fi_items();

	if ( empty( $items[ $slug ] ) ) {
		return new WP_Error( 'qp_fi_item', 'این اسلاگ در نقشۀ افزونه نیست' );
	}

	$item = $items[ $slug ];
	$file = QPEDIA_FI_DIR . $item['file'];

	if ( ! is_readable( $file ) ) {
		return new WP_Error( 'qp_fi_file', 'فایل تصویر پیدا نشد: ' . $item['file'] );
	}

	$pid = qpedia_fi_target( $slug );
	if ( ! $pid ) {
		return new WP_Error( 'qp_fi_post', 'مقاله‌ای با اسلاگ «' . $slug . '» پیدا نشد' );
	}

	$att = qpedia_fi_find_attachment( $slug );

	if ( $att ) {
		// فایل موجود را با نسخهٔ تازهٔ افزونه هم‌سازی کن (اگر عوض شده بود).
		$path = get_attached_file( $att );
		if ( $path && file_exists( $path ) && md5_file( $path ) !== md5_file( $file ) && is_writable( $path ) ) {
			copy( $file, $path );
			clearstatcache( true, $path );
		}
		wp_update_post(
			array(
				'ID'          => $att,
				'post_title'  => $item['title'],
				'post_excerpt'=> $item['caption'],
			)
		);
		qpedia_fi_meta( $att, $item, $slug );
	} else {
		$att = qpedia_fi_create_attachment( $slug, $item, $file );
		if ( is_wp_error( $att ) ) {
			return $att;
		}
	}

	$prev = (int) get_post_thumbnail_id( $pid );

	if ( $prev === $att ) {
		$replaced = 0;
	} else {
		set_post_thumbnail( $pid, $att );
		$replaced = $prev;
	}

	$state          = qpedia_fi_state();
	$state[ $slug ] = array(
		'attachment' => (int) $att,
		'post'       => (int) $pid,
		'replaced'   => (int) $replaced,
		'time'       => time(),
		'alt_len'    => qpedia_fi_len( $item['alt'] ),
	);
	update_option( QPEDIA_FI_STATE, $state, false );

	qpedia_fi_purge( $pid );

	return array(
		'attachment' => (int) $att,
		'post'       => (int) $pid,
		'replaced'   => (int) $replaced,
	);
}

/** پاک‌کردن کش لایت‌اسپید برای یک نشانی (اگر افزونه‌اش فعال باشد). */
function qpedia_fi_purge( $pid ) {
	if ( ! function_exists( 'litespeed_purge_by_url' ) ) {
		return;
	}

	$url = get_permalink( $pid );
	if ( $url ) {
		litespeed_purge_by_url( $url );
	}
}

/** تیک‌ها را از دیتابیس بازسازی می‌کند (بعد از نصب/جابه‌جایی سایت). */
function qpedia_fi_sync_state() {
	$found = 0;
	$state = qpedia_fi_state();

	$ids = get_posts(
		array(
			'post_type'      => 'attachment',
			'post_status'    => 'inherit',
			'numberposts'    => 300,
			'fields'         => 'ids',
			'meta_key'       => '_qpedia_img_slug',
		)
	);

	foreach ( (array) $ids as $att ) {
		$slug = (string) get_post_meta( $att, '_qpedia_img_slug', true );
		if ( '' === $slug ) {
			continue;
		}

		$users = get_posts(
			array(
				'post_type'      => array( 'quantum_article', 'post', 'page' ),
				'post_status'    => 'any',
				'numberposts'    => 1,
				'fields'         => 'ids',
				'meta_key'       => '_thumbnail_id',
				'meta_value'     => (int) $att,
			)
		);

		$state[ $slug ] = array(
			'attachment' => (int) $att,
			'post'       => $users ? (int) $users[0] : 0,
			'replaced'   => 0,
			'time'       => strtotime( (string) get_post_time( 'Y-m-d H:i:s', false, $att ) ),
			'alt_len'    => 0,
		);
		$found++;
	}

	update_option( QPEDIA_FI_STATE, $state, false );

	return $found;
}

/* ─── پیشخوان ───────────────────────────────────────────────────── */

add_action(
	'admin_menu',
	function () {
		add_menu_page(
			'تصاویر شاخص QPedia',
			'تصاویر شاخص QPedia',
			QPEDIA_FI_CAP,
			'qpedia-featured-images',
			'qpedia_fi_render_page',
			'dashicons-images-alt2',
			3
		);
	}
);

/** وضعیت یک ردیف: ready · done · other · nopost */
function qpedia_fi_row_status( $slug, $state ) {
	if ( ! isset( $state[ $slug ] ) ) {
		$pid = qpedia_fi_target( $slug );
		if ( ! $pid ) {
			return 'nopost';
		}
		return (int) get_post_thumbnail_id( $pid ) > 0 ? 'other' : 'ready';
	}

	$row = $state[ $slug ];
	$pid = (int) $row['post'];
	if ( ! $pid || (int) get_post_thumbnail_id( $pid ) !== (int) $row['attachment'] ) {
		return 'ready';
	}

	return 'done';
}

function qpedia_fi_render_page() {
	if ( ! current_user_can( QPEDIA_FI_CAP ) ) {
		wp_die( 'اجازهٔ دسترسی نداری.' );
	}

	$items = qpedia_fi_items();
	$state = qpedia_fi_state();
	$nonce = wp_create_nonce( 'qp_fi_nonce' );
	$done  = 0;

	echo '<div class="wrap" id="qpedia-fi">';
	echo '<h1>تصاویر شاخص QPedia <span class="qp-ver">v' . esc_html( QPEDIA_FI_VERSION ) . '</span></h1>';

	if ( isset( $_GET['qp_fi_msg'] ) ) {
		echo '<div class="notice notice-success is-dismissible"><p>' . esc_html( sanitize_text_field( wp_unslash( $_GET['qp_fi_msg'] ) ) ) . '</p></div>';
	}
	if ( isset( $_GET['qp_fi_err'] ) ) {
		echo '<div class="notice notice-error is-dismissible"><p>' . esc_html( sanitize_text_field( wp_unslash( $_GET['qp_fi_err'] ) ) ) . '</p></div>';
	}

	$url = admin_url( 'admin.php?page=qpedia-featured-images' );
	echo '<p class="qp-tools">'
		. '<a class="button button-primary" href="' . esc_url( add_query_arg( array( 'qp_fi_action' => 'all', 'qp_fi_nonce' => $nonce ), $url ) ) . '">واردکردن و جایگذاری همه (' . count( $items ) . ')</a> '
		. '<a class="button" href="' . esc_url( add_query_arg( array( 'qp_fi_action' => 'sync', 'qp_fi_nonce' => $nonce ), $url ) ) . '">بازخوانی تیک‌ها از دیتابیس</a> '
		. '<a class="button" href="' . esc_url( add_query_arg( array( 'qp_fi_action' => 'purge', 'qp_fi_nonce' => $nonce ), $url ) ) . '">پاک‌کردن کش سایت</a>'
		. '</p>';
	echo '<p class="desc">هر ردیف یک دکمه دارد: تصویر از داخل افزونه به کتابخانۀ رسانه اضافه می‌شود، ALT فارسی و عنوان و توضیح نوشته می‌شود و تصویر شاخصِ مقاله <strong>اجباراً</strong> جایگزین می‌گردد. اگر مقاله تصویر شاخص دیگری داشته باشد، شناسۀ آن در ستون وضعیت ذکر می‌شود.</p>';

	echo '<table class="widefixed striped"><thead><tr>'
		. '<th style="width:34px">#</th><th style="width:150px">پیش‌نمایش</th><th>مقاله و ALT</th>'
		. '<th style="width:190px">وضعیت</th><th style="width:170px">عمل</th></tr></thead><tbody>';

	$idx = 0;
	foreach ( $items as $slug => $item ) {
		$idx++;
		$status = qpedia_fi_row_status( $slug, $state );
		if ( 'done' === $status ) {
			$done++;
		}

		$pid  = isset( $state[ $slug ]['post'] ) ? (int) $state[ $slug ]['post'] : qpedia_fi_target( $slug );
		$file = QPEDIA_FI_DIR . $item['file'];
		$src  = file_exists( $file ) ? QPEDIA_FI_URL . $item['file'] : '';

		echo '<tr id="qp-row-' . esc_attr( $slug ) . '">';
		echo '<td>' . (int) $idx . '</td>';
		echo '<td>' . ( $src ? '<img src="' . esc_url( $src ) . '" alt="" style="width:145px;height:auto;border:1px solid #dcdcde;border-radius:4px">' : '<em>فایل نبود</em>' ) . '</td>';
		echo '<td><strong>' . esc_html( $item['title'] ) . '</strong><br><code>' . esc_html( $slug ) . '</code>';
		if ( $pid ) {
			echo ' <a href="' . esc_url( admin_url( 'post.php?post=' . $pid . '&action=edit' ) ) . '">ویرایش مقاله</a>';
		}
		echo '<p class="alt" style="margin:4px 0 0;color:#50575e">' . esc_html( $item['alt'] ) . ' <span class="len">(' . (int) qpedia_fi_len( $item['alt'] ) . ' نویسه)</span></p></td>';

		$label = array(
			'done'   => '<span class="badge ok">✓ جایگذاری شد</span>',
			'other'  => '<span class="badge warn">تصویر شاخص دیگری دارد</span>',
			'ready'  => '<span class="badge">آماده</span>',
			'nopost' => '<span class="badge err">مقاله پیدا نشد</span>',
		);
		$extra = '';
		if ( isset( $state[ $slug ]['replaced'] ) && (int) $state[ $slug ]['replaced'] > 0 ) {
			$extra = ' <small>جایگزین شد: #' . (int) $state[ $slug ]['replaced'] . '</small>';
		}
		if ( isset( $state[ $slug ]['time'] ) ) {
			$extra .= ' <small>' . esc_html( date_i18n( 'Y-m-d H:i', (int) $state[ $slug ]['time'] ) ) . '</small>';
		}
		echo '<td>' . ( isset( $label[ $status ] ) ? $label[ $status ] : $status ) . $extra . '</td>';

		echo '<td>';
		echo '<a class="button button-small' . ( 'done' === $status ? '' : ' button-primary' ) . '" href="'
			. esc_url( add_query_arg( array( 'qp_fi_action' => 'set', 'qp_fi_slug' => $slug, 'qp_fi_nonce' => $nonce ), $url ) ) . '">'
			. ( 'done' === $status ? 'دوباره جایگزین کن' : 'جایگذاری اجباری' ) . '</a> ';
		if ( isset( $state[ $slug ] ) ) {
			echo '<a class="button button-small" href="'
				. esc_url( add_query_arg( array( 'qp_fi_action' => 'unset', 'qp_fi_slug' => $slug, 'qp_fi_nonce' => $nonce ), $url ) ) . '">لغو تیک</a> ';
		}
		echo '</td></tr>';
	}

	echo '</tbody></table>';
	echo '<p class="qp-progress"><strong>' . (int) $done . '</strong> از ' . count( $items ) . ' ردیف تیک خورده است.</p>';
	echo '<style>#qpedia-fi .badge{background:#f0f0f1;padding:2px 8px;border-radius:10px;font-size:12px}#qpedia-fi .badge.ok{background:#0674404d;color:#067440}#qpedia-fi .badge.warn{background:#dba6174d}#qpedia-fi .badge.err{background:#d6363833;color:#d63638}#qpedia-fi .qp-ver{font-size:12px;color:#787c82;font-weight:400}#qpedia-fi table{background:#fff}#qpedia-fi td{vertical-align:top}#qpedia-fi code{color:#2271b1}</style>';
	echo '</div>';
}

/* ─── دستیاره‌ها ─────────────────────────────────────────────────── */

add_action(
	'admin_init',
	function () {
		if ( ! isset( $_GET['page'] ) || 'qpedia-featured-images' !== $_GET['page'] ) {
			return;
		}
		if ( empty( $_GET['qp_fi_action'] ) || ! current_user_can( QPEDIA_FI_CAP ) ) {
			return;
		}

		check_admin_referer( 'qp_fi_nonce', 'qp_fi_nonce' );

		$action = sanitize_key( wp_unslash( $_GET['qp_fi_action'] ) );
		$slug   = isset( $_GET['qp_fi_slug'] ) ? sanitize_title( wp_unslash( $_GET['qp_fi_slug'] ) ) : '';
		$url    = admin_url( 'admin.php?page=qpedia-featured-images' );

		if ( 'sync' === $action ) {
			$n = qpedia_fi_sync_state();
			wp_safe_redirect( add_query_arg( 'qp_fi_msg', rawurlencode( 'تیک‌ها بازخوانی شد: ' . $n . ' ردیف از دیتابیس.' ), $url ) );
			exit;
		}

		if ( 'purge' === $action ) {
			if ( function_exists( 'litespeed_purge_all' ) ) {
				litespeed_purge_all();
				$msg = 'کش LiteSpeed پاک شد.';
			} else {
				$msg = 'افزونۀ LiteSpeed در دسترس نبود؛ کش پاک نشد.';
			}
			wp_safe_redirect( add_query_arg( 'qp_fi_msg', rawurlencode( $msg ), $url ) );
			exit;
		}

		if ( 'unset' === $action && $slug ) {
			$state = qpedia_fi_state();
			unset( $state[ $slug ] );
			update_option( QPEDIA_FI_STATE, $state, false );
			wp_safe_redirect( add_query_arg( 'qp_fi_msg', rawurlencode( 'تیکِ این ردیف برداشته شد (هیچ فایلی حذف نشد).' ), $url ) );
			exit;
		}

		$items  = qpedia_fi_items();
		$ok     = 0;
		$failed = array();

		if ( 'set' === $action && $slug ) {
			$r = qpedia_fi_apply( $slug );
			if ( is_wp_error( $r ) ) {
				$failed[] = $slug . ': ' . $r->get_error_message();
			} else {
				$ok++;
			}
		} elseif ( 'all' === $action ) {
			foreach ( array_keys( $items ) as $s ) {
				$r = qpedia_fi_apply( $s );
				if ( is_wp_error( $r ) ) {
					$failed[] = $s . ': ' . $r->get_error_message();
				} else {
					$ok++;
				}
			}
		}

		$msg = 'جایگذاری انجام شد: ' . $ok . ' ردیف از ' . count( $items ) . '.';
		$url = add_query_arg( 'qp_fi_msg', rawurlencode( $msg ), $url );
		if ( $failed ) {
			$url = add_query_arg( 'qp_fi_err', rawurlencode( 'خطاها — ' . implode( ' | ', array_slice( $failed, 0, 6 ) ) ), $url );
		}

		wp_safe_redirect( $url );
		exit;
	}
);

register_activation_hook(
	__FILE__,
	function () {
		qpedia_fi_sync_state();
	}
);
