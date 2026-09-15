<?php
/**
 * Plugin Name: Qpedia Importer 2 (موقت)
 * Plugin URI:  https://qpedia.ir
 * Description: ایمپورتر یک‌بارمصرف برای به‌روزرسانی ۵ مقالهٔ دوم (برهم‌نهی، درهم‌تنیدگی، دو شکاف، تونل‌زنی، اندازه‌گیری) + ۵ دیاگرام آموزشی + اصلاح ALT تصویر شاخص. بعد از اجرای موفق حذف کنید.
 * Version:     2.0.0
 * Author:      Qpedia
 * License:     GPL-2.0+
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once plugin_dir_path( __FILE__ ) . 'images-map.php';

add_action( 'admin_menu', 'qpedia_imp2_menu' );
function qpedia_imp2_menu() {
	add_menu_page(
		'Qpedia Importer 2',
		'Qpedia Importer 2',
		'manage_options',
		'qpedia-importer-2',
		'qpedia_imp2_page',
		'dashicons-migration',
		81
	);
}

add_action( 'admin_init', 'qpedia_imp2_handle' );
function qpedia_imp2_handle() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	if ( isset( $_POST['qpedia_imp2_action'] ) && 'run' === $_POST['qpedia_imp2_action'] ) {
		check_admin_referer( 'qpedia_imp2_run' );
		$report = qpedia_imp2_run();
		set_transient( 'qpedia_imp2_report', $report, 3600 );
	}
}

function qpedia_imp2_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	$report = get_transient( 'qpedia_imp2_report' );
	?>
	<div class="wrap">
		<h1>Qpedia Importer 2 — به‌روزرسانی ۵ مقالهٔ دوم</h1>
		<p>
			مقالات: quantum-superposition, quantum-entanglement-explained, double-slit-experiment,
			quantum-tunneling, quantum-measurement. اعمال: عنوان، متن، فیلدهای <code>_qpedia_*</code>،
			دسته/برچسب، ۵ دیاگرام آموزشی (با کپشن هایلایت آبی) و <b>اصلاح ALT تصویر شاخصِ فعلی</b>
			(تصویر شاخص فعلی دست نمی‌خورد؛ فقط متن جایگزینش به‌روز می‌شود).
		</p>
		<?php if ( is_array( $report ) ) : ?>
			<div class="notice notice-info" style="padding:12px 16px;">
				<h2>گزارش اجرای آخر</h2>
				<ul>
					<?php foreach ( $report as $line ) : ?>
						<li><?php echo esc_html( $line ); ?></li>
					<?php endforeach; ?>
				</ul>
			</div>
		<?php endif; ?>
		<form method="post">
			<?php wp_nonce_field( 'qpedia_imp2_run' ); ?>
			<input type="hidden" name="qpedia_imp2_action" value="run" />
			<p><button type="submit" class="button button-primary button-hero">اجرای به‌روزرسانی (Import/Update)</button></p>
		</form>
		<hr/>
		<p style="color:#666;">پس از اطمینان: افزونه را غیرفعال و حذف کنید. (این افزونه با نسخهٔ ۱ تداخلی ندارد و می‌تواند هم‌زمان فعال باشد.)</p>
	</div>
	<?php
}

function qpedia_imp2_run() {
	require_once ABSPATH . 'wp-admin/includes/media.php';
	require_once ABSPATH . 'wp-admin/includes/file.php';
	require_once ABSPATH . 'wp-admin/includes/image.php';

	$report      = array();
	$content_dir = plugin_dir_path( __FILE__ ) . 'content';
	$articles    = glob( $content_dir . '/*.json' );
	sort( $articles );

	$featured_alts = qpedia_imp2_featured_alts();

	foreach ( $articles as $file ) {
		$data = json_decode( file_get_contents( $file ), true );
		if ( ! is_array( $data ) || empty( $data['slug'] ) ) {
			$report[] = 'خطا: فایل JSON نامعتبر: ' . basename( $file );
			continue;
		}

		$post = get_page_by_path( $data['slug'], OBJECT, 'quantum_article' );
		if ( ! $post ) {
			$report[] = 'خطا: مقاله‌ای با اسلاگ «' . $data['slug'] . '» پیدا نشد.';
			continue;
		}

		// --- ALT تصویر شاخصِ فعلی (آپلود نمی‌کنیم) -------------------
		$thumb_note = '';
		if ( ! empty( $featured_alts[ $data['slug'] ] ) ) {
			$thumb_id = get_post_thumbnail_id( $post->ID );
			if ( $thumb_id ) {
				wp_update_post(
					array(
						'ID'           => $thumb_id,
						'post_excerpt' => $featured_alts[ $data['slug'] ],
					)
				);
				$thumb_note = ' + ALT تصویر شاخص اصلاح شد';
			} else {
				$thumb_note = ' + (تصویر شاخصی پیدا نشد؛ ALT تنظیم نشد)';
			}
		}

		// --- دیاگرام‌ها -----------------------------------------------
		$map      = qpedia_imp2_diagrams();
		$by_key   = array();
		$uploaded = 0;
		foreach ( array_keys( $map ) as $key ) {
			$meta = $map[ $key ];
			$full = trailingslashit( plugin_dir_path( __FILE__ ) ) . 'assets/images/' . $meta['file'];
			if ( ! file_exists( $full ) ) {
				$report[] = 'خطا: فایل تصویر یافت نشد: ' . $meta['file'];
				continue;
			}
			$attach_id = qpedia_imp2_ensure_attachment( $meta['file'], $full, $post->ID );
			if ( ! $attach_id ) {
				$report[] = 'خطا: آپلود دیاگرام «' . $meta['file'] . '» ناموفق بود.';
				continue;
			}
			$by_key[ $key ] = $attach_id;
			$uploaded++;
		}

		$html = $data['html'];
		foreach ( $by_key as $key => $attach_id ) {
			$meta   = $map[ $key ];
			$src    = wp_get_attachment_image_url( $attach_id, 'full' );
			$figure = '<figure style="margin:28px 0;">'
				. '<img src="' . esc_url( $src ) . '" alt="' . esc_attr( $meta['alt'] ) . '" loading="lazy" style="max-width:100%;height:auto;border-radius:12px;display:block;" />'
				. '<div style="background:#e8f0fe;border-inline-start:4px solid #1a73e8;color:#174ea6;padding:10px 16px;border-radius:0 8px 8px 0;margin-top:10px;font-size:0.95em;line-height:2;">' . wp_kses_post( $meta['caption'] ) . '</div>'
				. '</figure>';
			$html = str_replace( '<!--QIMG:' . $key . '-->', $figure, $html );
		}
		$html = preg_replace( '/<!--QIMG:[^>]*-->/u', '', $html );

		$update = wp_update_post(
			array(
				'ID'           => $post->ID,
				'post_title'   => $data['title'],
				'post_content' => $html,
				'post_status'  => 'publish',
			),
			true
		);
		if ( is_wp_error( $update ) ) {
			$report[] = 'خطا در آپدیت «' . $data['slug'] . '»: ' . $update->get_error_message();
			continue;
		}

		update_post_meta( $post->ID, '_qpedia_seo_title', $data['seo_title'] );
		update_post_meta( $post->ID, '_qpedia_meta_description', $data['meta_description'] );
		update_post_meta( $post->ID, '_qpedia_focus_keyphrase', $data['focus_keyphrase'] );
		wp_set_post_categories( $post->ID, (array) $data['categories'], 'quantum_category' );
		wp_set_post_tags( $post->ID, (array) $data['tags'] );

		$report[] = '✅ «' . $data['slug'] . '» به‌روزرسانی شد (دیاگرام: ' . $uploaded . ')' . $thumb_note . ' — ' . $data['title'];
	}

	delete_transient( 'qpedia_imp2_report' );
	set_transient( 'qpedia_imp2_report', $report, 3600 );
	return $report;
}

function qpedia_imp2_ensure_attachment( $file_rel, $full_path, $post_id ) {
	$saved = get_option( 'qpedia_imp2_attachments', array() );
	if ( ! empty( $saved[ $file_rel ] ) ) {
		$existing = get_post( (int) $saved[ $file_rel ] );
		if ( $existing && 'attachment' === $existing->post_type ) {
			return (int) $saved[ $file_rel ];
		}
	}

	$upload = wp_upload_dir();
	$base   = basename( $full_path );
	$dest   = $upload['basedir'] . '/' . $base;
	$counter = 1;
	while ( file_exists( $dest ) ) {
		$dest = $upload['basedir'] . '/' . preg_replace( '/\.webp$/i', '', $base ) . '-' . $counter . '.webp';
		$counter++;
	}
	if ( ! copy( $full_path, $dest ) ) {
		return 0;
	}

	$attach_id = media_handle_sideload(
		array(
			'name'      => $base,
			'tmp_name'  => $dest,
			'overwrite' => false,
		),
		$post_id
	);

	if ( is_wp_error( $attach_id ) ) {
		@unlink( $dest );
		return 0;
	}

	$saved[ $file_rel ] = (int) $attach_id;
	update_option( 'qpedia_imp2_attachments', $saved );
	return (int) $attach_id;
}
