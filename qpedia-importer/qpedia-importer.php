<?php
/**
 * Plugin Name: Qpedia Importer (موقت)
 * Plugin URI:  https://qpedia.ir
 * Description: ایمپورتر یک‌بارمصرف برای به‌روزرسانی ۵ مقالهٔ بازنویسی‌شده + ۱۳ تصویر. بعد از اجرای موفق، این افزونه را غیرفعال و حذف کنید.
 * Version:     1.0.0
 * Author:      Qpedia
 * License:     GPL-2.0+
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'QPEDIA_IMPORTER_VERSION', '1.0.0' );

require_once plugin_dir_path( __FILE__ ) . 'images-map.php';

/* ------------------------------------------------------------------ */
/* صفحهٔ ادمین                                                         */
/* ------------------------------------------------------------------ */

add_action( 'admin_menu', 'qpedia_importer_menu' );
function qpedia_importer_menu() {
	add_menu_page(
		'Qpedia Importer',
		'Qpedia Importer',
		'manage_options',
		'qpedia-importer',
		'qpedia_importer_page',
		'dashicons-migration',
		80
	);
}

add_action( 'admin_init', 'qpedia_importer_handle' );
function qpedia_importer_handle() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	if ( isset( $_POST['qpedia_importer_action'] ) && 'run' === $_POST['qpedia_importer_action'] ) {
		check_admin_referer( 'qpedia_importer_run' );
		$report = qpedia_importer_run();
		set_transient( 'qpedia_importer_report', $report, 3600 );
	}
}

function qpedia_importer_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	$report = get_transient( 'qpedia_importer_report' );
	?>
	<div class="wrap">
		<h1>Qpedia Importer — به‌روزرسانی مقالات بازنویسی‌شده</h1>
		<p>
			این افزونه ۵ مقاله (qubit, decoherence, observer, quantum-state, wave-function) را
			<b>به‌صورت به‌روزرسانی (Update)</b> اعمال می‌کند: عنوان، متن، فیلدهای
			<code>_qpedia_seo_title</code>، <code>_qpedia_meta_description</code>،
			<code>_qpedia_focus_keyphrase</code>، دسته‌ها، برچسب‌ها، تصویر شاخص و ۱۳ تصویر
			(۵ شاخص + ۸ آموزشی با کپشن هایلایت آبی).
		</p>
		<p>
			URL مقالات <b>تغییر نمی‌کند</b> (اسلاگ‌ها حفظ می‌شوند). اگر مقاله‌ای با اسلاگ
			ذکرشده پیدا نشود، فقط گزارش خطا داده می‌شود و چیزی ساخته نمی‌شود.
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
			<?php wp_nonce_field( 'qpedia_importer_run' ); ?>
			<input type="hidden" name="qpedia_importer_action" value="run" />
			<p><button type="submit" class="button button-primary button-hero">اجرای به‌روزرسانی (Import/Update)</button></p>
		</form>
		<hr/>
		<p style="color:#666;">
			پس از اطمینان از موفقیت اجرا: افزونه را <b>غیرفعال</b> و <b>حذف</b> کنید.
			تصاویر در کتابخانهٔ رسانه باقی می‌مانند و محتوا در دیتابیس حفظ است.
		</p>
	</div>
	<?php
}

/* ------------------------------------------------------------------ */
/* موتور ایمپورت                                                       */
/* ------------------------------------------------------------------ */

function qpedia_importer_run() {
	require_once ABSPATH . 'wp-admin/includes/media.php';
	require_once ABSPATH . 'wp-admin/includes/file.php';
	require_once ABSPATH . 'wp-admin/includes/image.php';

	$report       = array();
	$content_dir  = plugin_dir_path( __FILE__ ) . 'content';
	$articles     = glob( $content_dir . '/*.json' );
	sort( $articles );

	foreach ( $articles as $file ) {
		$data = json_decode( file_get_contents( $file ), true );
		if ( ! is_array( $data ) || empty( $data['slug'] ) ) {
			$report[] = 'خطا: فایل JSON نامعتبر: ' . basename( $file );
			continue;
		}

		$post = get_page_by_path( $data['slug'], OBJECT, 'quantum_article' );
		if ( ! $post ) {
			$report[] = 'خطا: مقاله‌ای با اسلاگ «' . $data['slug'] . » پیدا نشد (فقط آپدیت می‌کنیم، مقاله جدید نساخته می‌شود).';
			continue;
		}

		// --- تصاویر -------------------------------------------------
		$map      = qpedia_importer_images();
		$keys     = array_keys( $map );
		$by_key   = array();
		$uploaded = 0;

		foreach ( $keys as $key ) {
			$meta  = $map[ $key ];
			$full  = trailingslashit( plugin_dir_path( __FILE__ ) ) . 'assets/images/' . $meta['file'];
			if ( ! file_exists( $full ) ) {
				$report[] = 'خطا: فایل تصویر یافت نشد: ' . $meta['file'];
				continue;
			}
			$attach_id = qpedia_importer_ensure_attachment( $meta['file'], $full, $post->ID );
			if ( ! $attach_id ) {
				$report[] = 'خطا: آپلود تصویر «' . $meta['file'] . '» برای «' . $data['slug'] . '» ناموفق بود.';
				continue;
			}
			$by_key[ $key ] = $attach_id;
			$uploaded++;

			// تصویر شاخص
			if ( ! empty( $meta['featured'] ) && $meta['slug'] === $data['slug'] ) {
				update_post_meta( $post->ID, '_thumbnail_id', $attach_id );
			}
		}

		// --- جایگزینی پلاک‌های تصویر در متن ------------------------
		$html = $data['html'];
		foreach ( $by_key as $key => $attach_id ) {
			$meta     = $map[ $key ];
			$src      = wp_get_attachment_image_url( $attach_id, 'full' );
			$figure   = '<figure style="margin:28px 0;">'
				. '<img src="' . esc_url( $src ) . '" alt="' . esc_attr( $meta['alt'] ) . '" loading="lazy" style="max-width:100%;height:auto;border-radius:12px;display:block;" />'
				. '<div style="background:#e8f0fe;border-inline-start:4px solid #1a73e8;color:#174ea6;padding:10px 16px;border-radius:0 8px 8px 0;margin-top:10px;font-size:0.95em;line-height:2;">' . wp_kses_post( $meta['caption'] ) . '</div>'
				. '</figure>';
			$html = str_replace( '<!--QIMG:' . $key . '-->', $figure, $html );
		}

		// باقی‌مانده‌های جایگزین‌نشده را پاک می‌کنیم
		$html = preg_replace( '/<!--QIMG:[^>]*-->/u', '', $html );

		// --- آپدیت پست ----------------------------------------------
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

		$report[] = '✅ «' . $data['slug'] . '» به‌روزرسانی شد (عنوان: ' . $data['title'] . ') — تصاویر: ' . $uploaded;
	}

	delete_transient( 'qpedia_importer_report' );
	set_transient( 'qpedia_importer_report', $report, 3600 );
	return $report;
}

/* ------------------------------------------------------------------ */
/* آپلود تصویر (idempotent)                                            */
/* ------------------------------------------------------------------ */

function qpedia_importer_ensure_attachment( $file_rel, $full_path, $post_id ) {
	$saved = get_option( 'qpedia_importer_attachments', array() );
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
		$dest = $upload['basedir'] . '/' . preg_replace( '/\.webp$/i', '', $base ) . '-' . $counter . '.webp';
		$counter++;
	}
	if ( ! copy( $full_path, $dest ) ) {
		return 0;
	}

	$attach_id = media_handle_sideload(
		array(
			'name'     => $base,
			'tmp_name' => $dest,
			'overwrite' => false,
		),
		$post_id
	);

	if ( is_wp_error( $attach_id ) ) {
		@unlink( $dest );
		return 0;
	}

	$saved[ $file_rel ] = (int) $attach_id;
	update_option( 'qpedia_importer_attachments', $saved );
	return (int) $attach_id;
}
