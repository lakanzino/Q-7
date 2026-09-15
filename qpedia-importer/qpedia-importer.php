<?php
/**
 * Plugin Name: Qpedia Importer
 * Plugin URI:  https://qpedia.ir
 * Description: ایمپورتر یک‌بارمصرف برای ۱۰ مقالهٔ بازنویسی‌شده + ۱۸ تصویر + اصلاح ALT تصویر شاخص. بعد از اجرای موفق، این افزونه را غیرفعال و حذف کنید.
 * Version:     2.0.0
 * Author:      Qpedia
 * License:     GPL-2.0+
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once plugin_dir_path( __FILE__ ) . 'images-map.php';

/* ------------------------------------------------------------------ */
/* صفحهٔ ادمین                                                         */
/* ------------------------------------------------------------------ */

add_action( 'admin_menu', 'qpedia_imp_menu' );
function qpedia_imp_menu() {
	add_menu_page(
		'Qpedia Importer',
		'Qpedia Importer',
		'manage_options',
		'qpedia-importer',
		'qpedia_imp_page',
		'dashicons-migration',
		80
	);
}

add_action( 'admin_init', 'qpedia_imp_handle' );
function qpedia_imp_handle() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	if ( ! isset( $_POST['qpedia_imp_action'] ) || 'run' !== $_POST['qpedia_imp_action'] ) {
		return;
	}
	check_admin_referer( 'qpedia_imp_run' );
	$report = qpedia_imp_run();
	set_transient( 'qpedia_imp_report', $report, 3600 );
}

function qpedia_imp_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	$report = get_transient( 'qpedia_imp_report' );
	?>
	<div class="wrap">
		<h1>Qpedia Importer — به‌روزرسانی ۱۰ مقالهٔ بازنویسی‌شده</h1>
		<p>
			مقالات: qubit, decoherence, observer, quantum-state, wave-function,
			quantum-superposition, quantum-entanglement-explained, double-slit-experiment,
			quantum-tunneling, quantum-measurement.
		</p>
		<p>
			اعمال: عنوان، متن، فیلدهای <code>_qpedia_*</code>، دسته/برچسب،
			۱۳ دیاگرام آموزشی (با کپشن هایلایت آبی)، تصویر شاخص ۵ مقالهٔ اول
			(تصویر جدید) و <b>اصلاح ALT تصویر شاخصِ ۵ مقالهٔ دوم</b> (تصویر فعلی دست نمی‌خورد).
			URL مقالات تغییر نمی‌کند؛ فقط آپدیت می‌شود، مقالهٔ جدید ساخته نمی‌شود.
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
			<?php wp_nonce_field( 'qpedia_imp_run' ); ?>
			<input type="hidden" name="qpedia_imp_action" value="run" />
			<p><button type="submit" class="button button-primary button-hero">اجرای به‌روزرسانی (Import/Update)</button></p>
		</form>
		<hr/>
		<p style="color:#666;">
			اجرا را می‌توان چند بار تکرار کرد (idempotent). بعد از اطمینان از گزارش (۱۰ خط با ✅):
			افزونه را <b>غیرفعال و حذف</b> کنید. تصاویر در کتابخانهٔ رسانه می‌مانند.
			نسخه‌های قدیمیِ این افزونه (v1/v2) را اگر در فهرست افزونه‌ها می‌بینید، هم حذف کنید.
		</p>
	</div>
	<?php
}

/* ------------------------------------------------------------------ */
/* موتور ایمپورت                                                       */
/* ------------------------------------------------------------------ */

function qpedia_imp_run() {
	require_once ABSPATH . 'wp-admin/includes/media.php';
	require_once ABSPATH . 'wp-admin/includes/file.php';
	require_once ABSPATH . 'wp-admin/includes/image.php';

	if ( function_exists( 'set_time_limit' ) ) {
		@set_time_limit( 300 );
	}

	$report      = array();
	$content_dir = plugin_dir_path( __FILE__ ) . 'content';
	$articles    = glob( $content_dir . '/*.json' );
	sort( $articles );

	if ( empty( $articles ) ) {
		return array( 'خطا: هیچ فایل محتوایی در پوشهٔ content پیدا نشد.' );
	}

	$inline   = qpedia_imp_inline_images();
	$featured = qpedia_imp_featured();

	foreach ( $articles as $file ) {
		$raw  = file_get_contents( $file );
		$data = json_decode( (string) $raw, true );
		if ( ! is_array( $data ) || empty( $data['slug'] ) ) {
			$report[] = 'خطا: فایل JSON نامعتبر: ' . basename( $file );
			continue;
		}

		$post = get_page_by_path( $data['slug'], OBJECT, 'quantum_article' );
		if ( ! $post ) {
			$report[] = 'خطا: مقاله‌ای با اسلاگ «' . $data['slug'] . '» پیدا نشد (فقط آپدیت می‌کنیم، چیزی ساخته نمی‌شود).';
			continue;
		}

		$slug     = $data['slug'];
		$html     = $data['html'];
		$uploaded = 0;
		$failed   = 0;

		// --- دیاگرام‌های درون‌متنی: فقط کلیدهایی که مارکشان در این متن هست ---
		foreach ( $inline as $key => $meta ) {
			if ( false === strpos( $html, '<!--QIMG:' . $key . '-->' ) ) {
				continue;
			}
			$full = plugin_dir_path( __FILE__ ) . 'assets/images/' . $meta['file'];
			if ( ! file_exists( $full ) ) {
				$report[] = 'خطا: فایل تصویر یافت نشد: ' . $meta['file'];
				$failed++;
				continue;
			}
			$attach_id = qpedia_imp_ensure_attachment( $meta['file'], $full, (int) $post->ID );
			if ( ! $attach_id ) {
				$report[] = 'خطا: آپلود تصویر «' . $meta['file'] . '» ناموفق بود.';
				$failed++;
				continue;
			}
			$existing = get_post( $attach_id );
			if ( $existing ) {
				$cur = (string) $existing->post_excerpt;
				if ( '' === trim( $cur ) ) {
					wp_update_post( array( 'ID' => (int) $attach_id, 'post_excerpt' => $meta['alt'] ) );
				}
			}
			$src    = wp_get_attachment_image_url( $attach_id, 'full' );
			$figure = '<figure style="margin:28px 0;">'
				. '<img src="' . esc_url( $src ) . '" alt="' . esc_attr( $meta['alt'] ) . '" loading="lazy" style="max-width:100%;height:auto;border-radius:12px;display:block;" />'
				. '<div style="background:#e8f0fe;border-inline-start:4px solid #1a73e8;color:#174ea6;padding:10px 16px;border-radius:0 8px 8px 0;margin-top:10px;font-size:0.95em;line-height:2;">' . wp_kses_post( $meta['caption'] ) . '</div>'
				. '</figure>';
			$html     = str_replace( '<!--QIMG:' . $key . '-->', $figure, $html );
			$uploaded++;
		}
		// باقی‌مانده‌های جایگزین‌نشده
		$html = preg_replace( '/<!--QIMG:[^>]*-->/u', '', $html );

		// --- تصویر شاخص -------------------------------------------------
		$thumb_note = '';
		if ( isset( $featured[ $slug ] ) ) {
			$fmeta = $featured[ $slug ];
			if ( ! empty( $fmeta['file'] ) ) {
				// دستهٔ اول: آپلود تصویر شاخصِ جدید و تنظیم
				$full = plugin_dir_path( __FILE__ ) . 'assets/images/' . $fmeta['file'];
				if ( ! file_exists( $full ) ) {
					$thumb_note = ' + (فایل تصویر شاخص پیدا نشد)';
				} else {
					$attach_id = qpedia_imp_ensure_attachment( $fmeta['file'], $full, (int) $post->ID );
					if ( $attach_id ) {
						update_post_meta( $post->ID, '_thumbnail_id', $attach_id );
						$existing = get_post( $attach_id );
						if ( $existing ) {
							wp_update_post( array( 'ID' => (int) $attach_id, 'post_excerpt' => $fmeta['alt'] ) );
						}
						$thumb_note = ' + تصویر شاخص جدید';
					} else {
						$thumb_note = ' + (آپلود تصویر شاخص ناموفق بود)';
					}
				}
			} else {
				// دستهٔ دوم: تصویر شاخص فعلی می‌ماند؛ فقط ALT اصلاح می‌شود
				$thumb_id = get_post_thumbnail_id( $post->ID );
				if ( $thumb_id ) {
					wp_update_post( array( 'ID' => (int) $thumb_id, 'post_excerpt' => $fmeta['alt'] ) );
					$thumb_note = ' + ALT تصویر شاخص اصلاح شد';
				} else {
					$thumb_note = ' + (تصویر شاخصی پیدا نشد)';
				}
			}
		}

		// --- آپدیت پست ----------------------------------------------------
		$update = wp_update_post(
			array(
				'ID'           => (int) $post->ID,
				'post_title'   => $data['title'],
				'post_content' => $html,
				'post_status'  => 'publish',
			),
			true
		);
		if ( is_wp_error( $update ) ) {
			$report[] = 'خطا در آپدیت «' . $slug . '»: ' . $update->get_error_message();
			continue;
		}

		update_post_meta( $post->ID, '_qpedia_seo_title', $data['seo_title'] );
		update_post_meta( $post->ID, '_qpedia_meta_description', $data['meta_description'] );
		update_post_meta( $post->ID, '_qpedia_focus_keyphrase', $data['focus_keyphrase'] );
		wp_set_post_categories( $post->ID, (array) $data['categories'], 'quantum_category' );
		wp_set_post_tags( $post->ID, (array) $data['tags'] );

		$report[] = '✅ «' . $slug . '» به‌روزرسانی شد (دیاگرام: ' . $uploaded . ', خطا: ' . $failed . ')' . $thumb_note . ' — ' . $data['title'];
	}

	return $report;
}

/* ------------------------------------------------------------------ */
/* آپلود تصویر (idempotent)                                            */
/* ------------------------------------------------------------------ */

function qpedia_imp_ensure_attachment( $file_rel, $full_path, $post_id ) {
	$saved = get_option( 'qpedia_imp_attachments', array() );
	if ( ! empty( $saved[ $file_rel ] ) ) {
		$existing = get_post( (int) $saved[ $file_rel ] );
		if ( $existing && 'attachment' === $existing->post_type ) {
			return (int) $saved[ $file_rel ];
		}
	}

	$upload  = wp_upload_dir();
	$base    = basename( $full_path );
	$dest    = $upload['basedir'] . '/' . $base;
	$counter = 1;
	while ( file_exists( $dest ) ) {
		$dest    = $upload['basedir'] . '/' . preg_replace( '/\.webp$/i', '', $base ) . '-' . $counter . '.webp';
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
	update_option( 'qpedia_imp_attachments', $saved );
	return (int) $attach_id;
}
