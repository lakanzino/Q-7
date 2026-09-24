<?php
/**
 * Plugin Name: Qpedia Glossary 200 Replacement (One-Time)
 * Description: جایگزینی یک‌بارهٔ همه اصطلاحات واژه‌نامه با نسخهٔ کامل‌تر و گسترش فهرست به ۲۰۰ اصطلاح، همراه با کلیدواژهٔ کانونی، عنوان و توضیح متا، لینک داخلی و اسکیمای DefinedTerm.
 * Version: 1.0.0
 * Author: Qpedia Editorial
 */

defined( 'ABSPATH' ) || exit;

const QPG200_OPTION    = 'qpedia_glossary_200_replacement_completed_v1';
const QPG200_POST_TYPE = 'qp_glossary';
const QPG200_EXPECTED  = 200;
const QPG200_MIN_TITLE = 50;
const QPG200_MAX_TITLE = 60;
const QPG200_MIN_DESC  = 120;
const QPG200_MAX_DESC  = 155;

add_action( 'admin_menu', 'qpg200_admin_menu' );
function qpg200_admin_menu() {
	add_management_page(
		'جایگزینی واژه‌نامه با ۲۰۰ اصطلاح',
		'واژه‌نامهٔ ۲۰۰ اصطلاحی',
		'manage_options',
		'qpedia-glossary-200-replacement',
		'qpg200_render_page'
	);
}

function qpg200_render_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	$done = get_option( QPG200_OPTION );
	echo '<div class="wrap" dir="rtl"><h1>جایگزینی واژه‌نامه با ۲۰۰ اصطلاح Qpedia</h1>';

	if ( isset( $_POST['qpg200_run'] ) ) {
		check_admin_referer( 'qpg200_run_replacement' );
		$force        = isset( $_POST['qpg200_force'] );
		$draft_others = isset( $_POST['qpg200_draft_others'] );
		$with_article = isset( $_POST['qpg200_article_schema'] );
		if ( $done && ! $force ) {
			echo '<div class="notice notice-warning"><p>این بسته قبلاً در ' . esc_html( $done ) . ' اجرا شده است. برای اجرای دوباره گزینهٔ «اجرای دوباره» را تیک بزنید.</p></div>';
		} else {
			$result = qpg200_run( $draft_others, $with_article );
			$class  = $result['ok'] ? 'notice-success' : 'notice-error';
			echo '<div class="notice ' . esc_attr( $class ) . '"><p>' . esc_html( $result['message'] ) . '</p></div>';
			if ( ! empty( $result['rows'] ) ) {
				echo '<ul style="max-height:340px;overflow:auto;background:#fff;border:1px solid #dcdcde;padding:8px 24px">';
				foreach ( $result['rows'] as $row ) {
					echo '<li>' . esc_html( $row ) . '</li>';
				}
				echo '</ul>';
			}
			if ( $result['ok'] ) {
				update_option( QPG200_OPTION, current_time( 'mysql' ), false );
				echo '<p><strong>جایگزینی کامل شد؛ افزونه را غیرفعال و حذف کنید. اصطلاحات و متادیتا باقی می‌مانند.</strong></p></div>';
				return;
			}
		}
	}

	$total = qpg200_data_count();
	echo '<p>این ابزار ' . (int) $total . ' اصطلاح تأییدشدهٔ واژه‌نامه را ثبت می‌کند: اصطلاح‌های موجود با همین عنوان به‌روزرسانی می‌شوند و اصطلاح‌های تازه ساخته می‌شوند.</p>';
	echo '<p>برای هر اصطلاح، کلیدواژهٔ کانونی، عنوان متا، توضیح متا، تعریف روان با مثال و کاربرد و ارتباط با مفاهیم دیگر، یک لینک داخلی قطعی و اسکیمای <code>DefinedTerm</code> ثبت می‌شود. مقاله‌ها و برگه‌ها دست‌نخورده می‌مانند.</p>';
	echo '<p>پیش از اجرا از سایت پشتیبان بگیرید. تاریخ انتشار، نویسنده و تصویر شاخص اصطلاحات موجود تغییر نمی‌کند.</p>';
	echo '<form method="post">';
	wp_nonce_field( 'qpg200_run_replacement' );
	echo '<p><label><input type="checkbox" name="qpg200_draft_others" value="1"> اصطلاح‌های قدیمی که در فهرست تازه نیستند به پیش‌نویس منتقل شوند (پیش‌فرض: دست‌نخورده می‌مانند)</label></p>';
	echo '<p><label><input type="checkbox" name="qpg200_article_schema" value="1"> افزودن اسکیمای <code>Article</code> در کنار <code>DefinedTerm</code></label></p>';
	echo '<p><label><input type="checkbox" name="qpg200_force" value="1"> اجرای دوباره، حتی اگر این بسته قبلاً اجرا شده باشد</label></p>';
	submit_button( 'جایگزینی و به‌روزرسانی واژه‌نامه', 'primary', 'qpg200_run' );
	echo '</form></div>';
}

function qpg200_data_count() {
	$file  = plugin_dir_path( __FILE__ ) . 'terms.json';
	$items = is_readable( $file ) ? json_decode( file_get_contents( $file ), true ) : null;
	return is_array( $items ) ? count( $items ) : 0;
}

function qpg200_load_items() {
	$file = plugin_dir_path( __FILE__ ) . 'terms.json';
	if ( ! is_readable( $file ) ) {
		return new WP_Error( 'qpg200_file', 'فایل terms.json خوانده نشد.' );
	}
	$items = json_decode( file_get_contents( $file ), true );
	if ( ! is_array( $items ) || count( $items ) !== QPG200_EXPECTED ) {
		return new WP_Error( 'qpg200_count', 'فایل داده باید دقیقاً ' . QPG200_EXPECTED . ' اصطلاح داشته باشد.' );
	}
	return $items;
}

function qpg200_validate( $items ) {
	$problems = array();
	$seen     = array();
	foreach ( $items as $item ) {
		$term = isset( $item['term'] ) ? trim( (string) $item['term'] ) : '';
		$key  = preg_replace( '/\s+/u', '', $term );
		if ( '' === $term ) {
			$problems[] = 'اصطلاح بی‌نام در ردیف ' . (int) ( $item['sequence'] ?? 0 );
			continue;
		}
		if ( isset( $seen[ $key ] ) ) {
			$problems[] = 'اصطلاح تکراری: ' . $term;
		}
		$seen[ $key ] = true;

		$focus  = isset( $item['focus_keyword'] ) ? trim( (string) $item['focus_keyword'] ) : '';
		$title  = isset( $item['seo_title'] ) ? trim( (string) $item['seo_title'] ) : '';
		$desc   = isset( $item['meta_description'] ) ? trim( (string) $item['meta_description'] ) : '';
		$body   = isset( $item['content'] ) ? (string) $item['content'] : '';
		$schema = isset( $item['schema']['@type'] ) ? (string) $item['schema']['@type'] : '';

		if ( $focus !== $term ) {
			$problems[] = 'کلیدواژهٔ کانونی با نام اصطلاح یکی نیست: ' . $term;
		}
		$title_len = function_exists( 'mb_strlen' ) ? mb_strlen( $title, 'UTF-8' ) : strlen( $title );
		if ( $title_len < QPG200_MIN_TITLE || $title_len > QPG200_MAX_TITLE ) {
			$problems[] = 'طول عنوان متا خارج از محدوده است (' . $title_len . '): ' . $term;
		}
		if ( 0 !== strpos( $title, $term ) ) {
			$problems[] = 'نام اصطلاح در ابتدای عنوان متا نیست: ' . $term;
		}
		$desc_len = function_exists( 'mb_strlen' ) ? mb_strlen( $desc, 'UTF-8' ) : strlen( $desc );
		if ( $desc_len < QPG200_MIN_DESC || $desc_len > QPG200_MAX_DESC ) {
			$problems[] = 'طول توضیح متا خارج از محدوده است (' . $desc_len . '): ' . $term;
		}
		if ( false === strpos( $desc, $term ) ) {
			$problems[] = 'نام اصطلاح در توضیح متا نیست: ' . $term;
		}
		if ( ! preg_match( '#<a\s+href="https://qpedia\.ir/[^"]+/"#', $body ) ) {
			$problems[] = 'لینک داخلی قطعی ندارد: ' . $term;
		}
		foreach ( array( '<h2>تعریف ساده</h2>', '<h2>مثال روزمره و مرز تمثیل</h2>', '<h2>کاربرد</h2>', '<h2>اشتباه رایج</h2>', '<h2>ارتباط با مفاهیم دیگر</h2>' ) as $heading ) {
			if ( false === strpos( $body, $heading ) ) {
				$problems[] = 'بخش محتوایی ناقص است (' . $heading . '): ' . $term;
			}
		}
		if ( false === strpos( $body, 'مرز شکست' ) ) {
			$problems[] = 'مرز شکست تمثیل توضیح داده نشده است: ' . $term;
		}
		if ( strlen( (string) $item['definition'] ) < 60 ) {
			$problems[] = 'تعریف کوتاه‌تر از حد لازم است: ' . $term;
		}
		if ( 'DefinedTerm' !== $schema ) {
			$problems[] = 'اسکیمای DefinedTerm ثبت نشده است: ' . $term;
		}
	}
	if ( $problems ) {
		return new WP_Error( 'qpg200_validation', implode( ' | ', array_slice( $problems, 0, 8 ) ) . ( count( $problems ) > 8 ? ' | و ' . ( count( $problems ) - 8 ) . ' ایراد دیگر.' : '' ) );
	}
	return true;
}

function qpg200_find_term( $term ) {
	$query = new WP_Query(
		array(
			'post_type'              => QPG200_POST_TYPE,
			'post_status'            => array( 'publish', 'draft', 'pending', 'private', 'future' ),
			'title'                  => $term,
			'posts_per_page'         => 1,
			'no_found_rows'          => true,
			'update_post_meta_cache' => false,
			'update_post_term_cache' => false,
		)
	);
	return $query->posts ? $query->posts[0] : null;
}

function qpg200_run( $draft_others = false, $with_article = false ) {
	if ( ! post_type_exists( QPG200_POST_TYPE ) ) {
		return array( 'ok' => false, 'message' => 'نوع محتوای واژه‌نامه فعال نیست. ابتدا قالب فرزند Qpedia را فعال کنید.', 'rows' => array() );
	}
	$items = qpg200_load_items();
	if ( is_wp_error( $items ) ) {
		return array( 'ok' => false, 'message' => $items->get_error_message(), 'rows' => array() );
	}
	$valid = qpg200_validate( $items );
	if ( is_wp_error( $valid ) ) {
		return array( 'ok' => false, 'message' => 'داده‌ها از فیلتر شش‌گزینه‌ای عبور نکردند و وارد سایت نشدند: ' . $valid->get_error_message(), 'rows' => array() );
	}

	$created = 0;
	$updated = 0;
	$kept    = array();
	$rows    = array();

	foreach ( $items as $item ) {
		$term       = trim( (string) $item['term'] );
		$definition = trim( (string) $item['definition'] );
		$body       = (string) $item['content'];
		$existing   = qpg200_find_term( $term );

		$postarr = array(
			'post_type'    => QPG200_POST_TYPE,
			'post_title'   => $term,
			'post_excerpt' => $definition,
			'post_content' => $body,
		);
		if ( $existing ) {
			$postarr['ID'] = (int) $existing->ID;
		} else {
			$postarr['post_status'] = 'publish';
			$postarr['post_name']   = sanitize_title( $term );
		}

		$post_id = wp_insert_post( wp_slash( $postarr ), true );
		if ( is_wp_error( $post_id ) ) {
			$rows[] = 'خطا در «' . $term . '»: ' . $post_id->get_error_message();
			continue;
		}
		$post_id = (int) $post_id;
		if ( $existing ) {
			$updated++;
		} else {
			$created++;
		}
		$kept[] = $post_id;

		$schema = array(
			'@context'          => 'https://schema.org',
			'@type'             => 'DefinedTerm',
			'name'              => $term,
			'description'       => $definition,
			'inDefinedTermSet'  => array(
				'@type' => 'DefinedTermSet',
				'name'  => 'واژه‌نامهٔ کوانتوم Qpedia',
				'url'   => home_url( '/glossary/' ),
			),
			'url'               => get_permalink( $post_id ),
		);

		$meta = array(
			'rank_math_focus_keyword'   => $term,
			'rank_math_title'           => (string) $item['seo_title'],
			'rank_math_description'     => (string) $item['meta_description'],
			'_qpedia_focus_keyphrase'   => $term,
			'_qpedia_seo_title'         => (string) $item['seo_title'],
			'_qpedia_meta_description'  => (string) $item['meta_description'],
			'_yoast_wpseo_focuskw'      => $term,
			'_yoast_wpseo_title'        => (string) $item['seo_title'],
			'_yoast_wpseo_metadesc'     => (string) $item['meta_description'],
			'rank_math_schema_DefinedTerm' => $schema,
			'_qpedia_defined_term_schema'  => wp_json_encode( $schema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ),
		);
		if ( $with_article ) {
			$meta['rank_math_schema_Article'] = array(
				'@context'      => 'https://schema.org',
				'@type'         => 'Article',
				'headline'      => (string) $item['seo_title'],
				'description'   => (string) $item['meta_description'],
				'about'         => array( '@type' => 'DefinedTerm', 'name' => $term ),
				'url'           => get_permalink( $post_id ),
				'inLanguage'    => 'fa-IR',
			);
		}
		foreach ( $meta as $meta_key => $meta_value ) {
			update_post_meta( $post_id, $meta_key, $meta_value );
		}
		if ( ! empty( $item['aliases'] ) && is_array( $item['aliases'] ) ) {
			$aliases = array();
			foreach ( $item['aliases'] as $alias ) {
				$alias = trim( sanitize_text_field( $alias ) );
				if ( '' !== $alias && $alias !== $term ) {
					$aliases[] = $alias;
				}
			}
			if ( $aliases ) {
				update_post_meta( $post_id, '_qpedia_glossary_aliases', implode( "\n", $aliases ) );
			}
		}
		clean_post_cache( $post_id );
	}

	if ( $created + $updated !== QPG200_EXPECTED ) {
		return array(
			'ok'    => false,
			'message' => 'جایگزینی کامل نشد: ' . $created . ' ساخته و ' . $updated . ' به‌روزرسانی شد؛ انتظار ' . QPG200_EXPECTED . ' مورد بود.',
			'rows'  => $rows,
		);
	}

	$leftovers = get_posts(
		array(
			'post_type'      => QPG200_POST_TYPE,
			'post_status'    => 'publish',
			'posts_per_page' => -1,
			'post__not_in'   => $kept,
			'fields'         => 'ids',
		)
	);
	$drafted = 0;
	if ( $draft_others ) {
		foreach ( $leftovers as $leftover_id ) {
			wp_update_post( array( 'ID' => (int) $leftover_id, 'post_status' => 'draft' ) );
			$drafted++;
		}
	}
	delete_transient( 'qpedia_glossary_terms_v1' );

	$rows[] = 'اصطلاح‌های قدیمی خارج از فهرست تازه: ' . count( $leftovers ) . ' مورد — ' . ( $draft_others ? $drafted . ' مورد به پیش‌نویس منتقل شد.' : 'دست‌نخورده ماندند.' );
	$message = sprintf( 'واژه‌نامه به‌روزرسانی شد: %d اصطلاح تازه ساخته و %d اصطلاح موجود بازنویسی شد. کلیدواژهٔ کانونی، عنوان و توضیح متا، لینک داخلی و اسکیمای DefinedTerm برای همه ثبت شد.', $created, $updated );

	return array( 'ok' => true, 'message' => $message, 'rows' => $rows );
}
