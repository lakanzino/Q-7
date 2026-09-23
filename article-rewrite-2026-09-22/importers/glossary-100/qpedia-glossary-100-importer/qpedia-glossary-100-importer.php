<?php
/**
 * Plugin Name: Qpedia One-Time Glossary Importer — 100 Terms
 * Description: درون‌ریزی یا به‌روزرسانی یک‌باره ۱۰۰ اصطلاح با تعریف کوتاه در واژه‌نامهٔ قالب Qpedia.
 * Version: 1.0.0
 * Author: Qpedia Editorial
 */

defined( 'ABSPATH' ) || exit;

const QPEDIA_G100_OPTION = 'qpedia_glossary_100_import_completed_v1';
const QPEDIA_G100_POST_TYPE = 'qp_glossary';

add_action( 'admin_menu', 'qpedia_g100_admin_menu' );
function qpedia_g100_admin_menu() {
	add_management_page(
		'درون‌ریز ۱۰۰ اصطلاح Qpedia',
		'درون‌ریز ۱۰۰ اصطلاح',
		'manage_options',
		'qpedia-glossary-100-importer',
		'qpedia_g100_render_page'
	);
}

function qpedia_g100_render_page() {
	if ( ! current_user_can( 'manage_options' ) ) return;
	$done = get_option( QPEDIA_G100_OPTION );
	echo '<div class="wrap" dir="rtl"><h1>درون‌ریز یک‌باره ۱۰۰ اصطلاح Qpedia</h1>';
	if ( $done ) {
		echo '<div class="notice notice-success"><p>این بسته در ' . esc_html( $done ) . ' با موفقیت اجرا شده و دوباره اجرا نمی‌شود.</p></div>';
		echo '<p>اکنون می‌توانید افزونه را غیرفعال و حذف کنید. اصطلاحات باقی می‌مانند.</p></div>';
		return;
	}
	if ( isset( $_POST['qpedia_g100_run'] ) ) {
		check_admin_referer( 'qpedia_g100_run_import' );
		$result = qpedia_g100_import();
		if ( $result['ok'] ) {
			update_option( QPEDIA_G100_OPTION, current_time( 'mysql' ), false );
			echo '<div class="notice notice-success"><p>' . esc_html( $result['message'] ) . '</p></div>';
			echo '<p><strong>درون‌ریزی کامل شد؛ افزونه را غیرفعال و حذف کنید.</strong></p></div>';
			return;
		}
		echo '<div class="notice notice-error"><p>' . esc_html( $result['message'] ) . '</p></div>';
	}
	echo '<p>این ابزار ۱۰۰ اصطلاح را در بخش «اصطلاحات» قالب ایجاد یا بر اساس عنوان دقیق به‌روزرسانی می‌کند.</p>';
	echo '<p>عنوان اصطلاح، چکیدهٔ یک‌جمله‌ای و متن صفحهٔ اصطلاح ثبت می‌شود. مقاله‌های سایت تغییر نمی‌کنند.</p>';
	echo '<form method="post">';
	wp_nonce_field( 'qpedia_g100_run_import' );
	submit_button( 'درون‌ریزی ۱۰۰ اصطلاح', 'primary', 'qpedia_g100_run' );
	echo '</form></div>';
}

function qpedia_g100_import() {
	if ( ! post_type_exists( QPEDIA_G100_POST_TYPE ) ) {
		return array( 'ok' => false, 'message' => 'نوع محتوای اصطلاحات فعال نیست. ابتدا قالب فرزند Qpedia نسخه ۱.۸.۰ یا جدیدتر را فعال کنید.' );
	}
	$file = plugin_dir_path( __FILE__ ) . 'terms.json';
	if ( ! is_readable( $file ) ) return array( 'ok' => false, 'message' => 'فایل terms.json خوانده نشد.' );
	$items = json_decode( file_get_contents( $file ), true );
	if ( ! is_array( $items ) || count( $items ) !== 100 ) {
		return array( 'ok' => false, 'message' => 'فایل داده معتبر نیست یا دقیقاً ۱۰۰ اصطلاح ندارد.' );
	}
	$created = 0; $updated = 0; $errors = array();
	foreach ( $items as $index => $item ) {
		$term = isset( $item['term'] ) ? trim( sanitize_text_field( $item['term'] ) ) : '';
		$definition = isset( $item['definition'] ) ? trim( sanitize_textarea_field( $item['definition'] ) ) : '';
		if ( '' === $term || '' === $definition ) {
			$errors[] = 'ردیف ' . ( $index + 1 ) . ' ناقص است.';
			continue;
		}
		$existing = get_page_by_title( $term, OBJECT, QPEDIA_G100_POST_TYPE );
		$postarr = array(
			'post_type'    => QPEDIA_G100_POST_TYPE,
			'post_status'  => 'publish',
			'post_title'   => $term,
			'post_excerpt' => $definition,
			'post_content' => '<p>' . esc_html( $definition ) . '</p>',
		);
		if ( $existing instanceof WP_Post ) $postarr['ID'] = $existing->ID;
		$post_id = wp_insert_post( wp_slash( $postarr ), true );
		if ( is_wp_error( $post_id ) ) {
			$errors[] = $term . ': ' . $post_id->get_error_message();
			continue;
		}
		if ( $existing instanceof WP_Post ) $updated++; else $created++;
	}
	if ( $errors ) {
		return array( 'ok' => false, 'message' => 'درون‌ریزی ناقص ماند: ' . count( $errors ) . ' خطا. نخستین خطا: ' . $errors[0] );
	}
	delete_transient( 'qpedia_glossary_terms_v1' );
	flush_rewrite_rules( false );
	return array( 'ok' => true, 'message' => 'هر ۱۰۰ اصطلاح ثبت شدند؛ ' . $created . ' مورد ساخته و ' . $updated . ' مورد به‌روزرسانی شد.' );
}
