<?php
/**
 * Glossary module — post type, editorial fields, English slug enforcement.
 *
 * نسخهٔ ۲ (قالب فرزند 1.8.1):
 * - نوع محتوای «اصطلاحات کوانتومی» با آرشیو /glossary/
 * - اجبار اسلاگ انگلیسی (اسلاگ فارسی به‌صورت خودکار اصلاح و ۳۰۱ می‌شود)
 * - فیلدهای تحریریه و سئو: نام انگلیسی، تعریف کوتاه، سطح، مترادف‌ها،
 *   کلیدواژهٔ کانونی، متا دیسکریپشن، پرسش‌وپاسخ، منابع، پیوندهای داخلی، بازبینی علمی
 *
 * @package Quantum_Pedia_Child
 */

defined( 'ABSPATH' ) || exit;

if ( ! defined( 'QPEDIA_GLOSSARY_POST_TYPE' ) ) {
	define( 'QPEDIA_GLOSSARY_POST_TYPE', 'qp_glossary' );
}

if ( ! defined( 'QPEDIA_GLOSSARY_BASE' ) ) {
	define( 'QPEDIA_GLOSSARY_BASE', 'glossary' );
}

/* ──────────────────────────────────────────────────────────────
   ۱. ثبت نوع محتوا — هم‌سطح quantum_scientist
   ────────────────────────────────────────────────────────────── */
add_action( 'init', 'qpedia_register_glossary_post_type', 5 );
function qpedia_register_glossary_post_type() {
	register_post_type(
		QPEDIA_GLOSSARY_POST_TYPE,
		array(
			'labels' => array(
				'name'                  => 'اصطلاحات کوانتومی',
				'singular_name'         => 'اصطلاح کوانتومی',
				'menu_name'             => 'اصطلاحات',
				'all_items'             => 'همهٔ اصطلاحات',
				'add_new'               => 'افزودن اصطلاح',
				'add_new_item'          => 'افزودن اصطلاح تازه',
				'edit_item'             => 'ویرایش اصطلاح',
				'new_item'              => 'اصطلاح تازه',
				'view_item'             => 'مشاهدهٔ اصطلاح',
				'view_items'            => 'مشاهدهٔ اصطلاحات',
				'search_items'          => 'جست‌وجوی اصطلاحات',
				'not_found'             => 'اصطلاحی پیدا نشد.',
				'not_found_in_trash'    => 'اصطلاحی در زباله‌دان نیست.',
				'archives'              => 'واژه‌نامهٔ کوانتوم',
				'featured_image'        => 'تصویر شاخص اصطلاح',
				'item_published'        => 'اصطلاح منتشر شد.',
				'item_updated'          => 'اصطلاح به‌روزرسانی شد.',
			),
			'description'         => 'واژه‌نامهٔ اصطلاحات فیزیک کوانتوم — هر مدخل یک صفحهٔ مستقل با اسلاگ انگلیسی.',
			'public'              => true,
			'publicly_queryable'  => true,
			'show_ui'             => true,
			'show_in_menu'        => true,
			'show_in_nav_menus'   => true,
			'show_in_admin_bar'   => true,
			'show_in_rest'        => true,
			'rest_base'           => 'glossary',
			'menu_icon'           => 'dashicons-book-alt',
			'menu_position'       => 6,
			'exclude_from_search' => false,
			'hierarchical'        => false,
			'has_archive'         => QPEDIA_GLOSSARY_BASE,
			'rewrite'             => array(
				'slug'       => QPEDIA_GLOSSARY_BASE,
				'with_front' => false,
				'feeds'      => false,
				'pages'      => true,
			),
			'supports'            => array( 'title', 'editor', 'excerpt', 'thumbnail', 'author', 'revisions', 'custom-fields' ),
		)
	);
}

/* ──────────────────────────────────────────────────────────────
   ۲. فهرست فیلدهای متا
   ────────────────────────────────────────────────────────────── */
function qpedia_glossary_meta_fields() {
	return array(
		'_qp_term_en'                 => 'text',
		'_qp_term_abbr'               => 'text',
		'_qp_term_level'              => 'text',
		'_qp_term_short'              => 'textarea',
		'_qpedia_glossary_aliases'    => 'textarea',
		'_qp_term_focus'              => 'text',
		'_qp_term_meta_desc'          => 'textarea',
		'_qp_term_faq'                => 'textarea',
		'_qp_term_sources'            => 'textarea',
		'_qp_term_related_terms'      => 'text',
		'_qp_term_related_articles'   => 'text',
		'_qp_term_related_scientists' => 'text',
		'_qp_term_reviewer'           => 'text',
		'_qp_term_reviewed'           => 'text',
		'_qp_term_noindex'            => 'text',
	);
}

/**
 * نام انگلیسی اصطلاح.
 */
function qpedia_glossary_en_title( $post_id ) {
	return trim( (string) get_post_meta( $post_id, '_qp_term_en', true ) );
}

/**
 * تعریف کوتاه (تولتیپ و متا دیسکریپشن).
 */
function qpedia_glossary_short_definition( $post_id ) {
	$short = trim( (string) get_post_meta( $post_id, '_qp_term_short', true ) );
	if ( '' !== $short ) {
		return $short;
	}

	$post = get_post( $post_id );
	if ( ! $post instanceof WP_Post ) {
		return '';
	}

	$excerpt = trim( (string) $post->post_excerpt );
	if ( '' !== $excerpt ) {
		return wp_strip_all_tags( $excerpt );
	}

	return wp_trim_words( wp_strip_all_tags( $post->post_content ), 28, '…' );
}

/**
 * تجزیهٔ خطوط «کلید|مقدار».
 */
function qpedia_glossary_parse_pairs( $raw ) {
	$rows  = preg_split( '/\R/u', (string) $raw );
	$pairs = array();

	if ( empty( $rows ) ) {
		return $pairs;
	}

	foreach ( $rows as $row ) {
		$row = trim( (string) $row );
		if ( '' === $row ) {
			continue;
		}

		$parts = explode( '|', $row );
		$key   = trim( (string) array_shift( $parts ) );
		$value = trim( implode( '|', $parts ) );

		if ( '' === $key ) {
			continue;
		}

		$pairs[] = array(
			'key'   => $key,
			'value' => $value,
		);
	}

	return $pairs;
}

/**
 * تبدیل فهرست اسلاگ/شناسه به پست‌های واقعی.
 *
 * @param string       $raw        مقدار فیلد (با کاما جدا شده).
 * @param string|array $post_types نوع محتوا.
 * @return WP_Post[]
 */
function qpedia_glossary_resolve_posts( $raw, $post_types ) {
	$raw = trim( (string) $raw );
	if ( '' === $raw ) {
		return array();
	}

	$post_types = (array) $post_types;
	$items      = preg_split( '/[,،\n]+/u', $raw );
	$out        = array();

	foreach ( (array) $items as $item ) {
		$item = trim( (string) $item );
		if ( '' === $item ) {
			continue;
		}

		$found = null;

		if ( ctype_digit( $item ) ) {
			$candidate = get_post( (int) $item );
			if ( $candidate instanceof WP_Post && in_array( $candidate->post_type, $post_types, true ) ) {
				$found = $candidate;
			}
		}

		if ( ! $found ) {
			// اگر آدرس کامل داده شده باشد، فقط آخرین بخش مسیر را بردار.
			if ( false !== strpos( $item, '/' ) ) {
				$path  = wp_parse_url( $item, PHP_URL_PATH );
				$parts = array_values( array_filter( explode( '/', (string) $path ) ) );
				$item  = ! empty( $parts ) ? end( $parts ) : $item;
			}

			foreach ( $post_types as $type ) {
				$candidate = get_page_by_path( $item, OBJECT, $type );
				if ( $candidate instanceof WP_Post ) {
					$found = $candidate;
					break;
				}
			}
		}

		if ( $found && 'publish' === $found->post_status ) {
			$out[ $found->ID ] = $found;
		}
	}

	return array_values( $out );
}

/* ──────────────────────────────────────────────────────────────
   ۳. جعبه‌های ویرایش
   ────────────────────────────────────────────────────────────── */
add_action( 'add_meta_boxes_' . QPEDIA_GLOSSARY_POST_TYPE, 'qpedia_glossary_register_meta_boxes' );
function qpedia_glossary_register_meta_boxes() {
	add_meta_box( 'qpedia-glossary-identity', 'شناسنامهٔ اصطلاح', 'qpedia_glossary_box_identity', QPEDIA_GLOSSARY_POST_TYPE, 'normal', 'high' );
	add_meta_box( 'qpedia-glossary-links', 'پیوندهای داخلی', 'qpedia_glossary_box_links', QPEDIA_GLOSSARY_POST_TYPE, 'normal', 'default' );
	add_meta_box( 'qpedia-glossary-seo', 'سئو، پرسش‌وپاسخ و منابع', 'qpedia_glossary_box_seo', QPEDIA_GLOSSARY_POST_TYPE, 'normal', 'default' );
}

function qpedia_glossary_field_text( $post_id, $key, $label, $hint = '', $placeholder = '' ) {
	$value = (string) get_post_meta( $post_id, $key, true );
	echo '<p style="margin:0 0 14px"><label for="' . esc_attr( $key ) . '" style="display:block;font-weight:600;margin-bottom:4px">' . esc_html( $label ) . '</label>';
	echo '<input type="text" id="' . esc_attr( $key ) . '" name="' . esc_attr( $key ) . '" value="' . esc_attr( $value ) . '" placeholder="' . esc_attr( $placeholder ) . '" style="width:100%" />';
	if ( '' !== $hint ) {
		echo '<span class="description" style="display:block;margin-top:4px">' . esc_html( $hint ) . '</span>';
	}
	echo '</p>';
}

function qpedia_glossary_field_textarea( $post_id, $key, $label, $hint = '', $rows = 4, $dir = 'rtl' ) {
	$value = (string) get_post_meta( $post_id, $key, true );
	echo '<p style="margin:0 0 14px"><label for="' . esc_attr( $key ) . '" style="display:block;font-weight:600;margin-bottom:4px">' . esc_html( $label ) . '</label>';
	echo '<textarea id="' . esc_attr( $key ) . '" name="' . esc_attr( $key ) . '" rows="' . absint( $rows ) . '" style="width:100%;direction:' . esc_attr( $dir ) . '">' . esc_textarea( $value ) . '</textarea>';
	if ( '' !== $hint ) {
		echo '<span class="description" style="display:block;margin-top:4px">' . esc_html( $hint ) . '</span>';
	}
	echo '</p>';
}

function qpedia_glossary_box_identity( $post ) {
	wp_nonce_field( 'qpedia_glossary_save_meta', 'qpedia_glossary_meta_nonce' );

	$slug     = urldecode( (string) $post->post_name );
	$is_latin = qpedia_glossary_is_latin_slug( $slug );

	echo '<div style="background:#f6f7f7;border:1px solid #dcdcde;border-radius:6px;padding:10px 12px;margin:0 0 16px">';
	echo '<strong>اسلاگ فعلی:</strong> <code>' . esc_html( '' !== $slug ? $slug : '—' ) . '</code> ';
	if ( '' === $slug ) {
		echo '<span style="color:#996800">— پس از ذخیره ساخته می‌شود</span>';
	} elseif ( $is_latin ) {
		echo '<span style="color:#008a20">✓ انگلیسی و مناسب گوگل</span>';
	} else {
		echo '<span style="color:#d63638">✗ فارسی است — «نام انگلیسی» را پر کنید تا خودکار اصلاح و ۳۰۱ شود</span>';
	}
	echo '</div>';

	qpedia_glossary_field_text( $post->ID, '_qp_term_en', 'نام انگلیسی اصطلاح (اجباری — مبنای اسلاگ)', 'مثال: Quantum Entanglement → اسلاگ: quantum-entanglement', 'Quantum Entanglement' );
	qpedia_glossary_field_text( $post->ID, '_qp_term_abbr', 'مخفف یا نماد ریاضی (اختیاری)', 'مثال: QEC یا |ψ⟩', 'QEC' );

	$level   = (string) get_post_meta( $post->ID, '_qp_term_level', true );
	$choices = array(
		''             => '— انتخاب سطح —',
		'beginner'     => 'مقدماتی',
		'intermediate' => 'متوسط',
		'advanced'     => 'پیشرفته',
	);
	echo '<p style="margin:0 0 14px"><label for="_qp_term_level" style="display:block;font-weight:600;margin-bottom:4px">سطح دشواری</label>';
	echo '<select id="_qp_term_level" name="_qp_term_level">';
	foreach ( $choices as $value => $label ) {
		echo '<option value="' . esc_attr( $value ) . '"' . selected( $level, $value, false ) . '>' . esc_html( $label ) . '</option>';
	}
	echo '</select></p>';

	qpedia_glossary_field_textarea( $post->ID, '_qp_term_short', 'تعریف کوتاه (متن تولتیپ — ۱۲۰ تا ۲۰۰ نویسه)', 'همین متن در تولتیپ مقالات، کارت آرشیو و اسکیمای DefinedTerm استفاده می‌شود.', 3 );
	qpedia_glossary_field_textarea( $post->ID, '_qpedia_glossary_aliases', 'صورت‌های جایگزین (هر کدام در یک خط)', 'شکل‌های دیگر نوشتاری همین اصطلاح؛ برای تشخیص خودکار در متن مقاله‌ها.', 5 );
}

function qpedia_glossary_box_links( $post ) {
	echo '<p class="description" style="margin-top:0">اسلاگ یا شناسهٔ محتواها را با کاما جدا کنید. مثال: <code>quantum-entanglement, 1204</code></p>';
	qpedia_glossary_field_text( $post->ID, '_qp_term_related_terms', 'اصطلاحات مرتبط', 'حداقل ۲ اصطلاح دیگر از همین واژه‌نامه.', 'wave-function, qubit' );
	qpedia_glossary_field_text( $post->ID, '_qp_term_related_articles', 'مقاله‌های مرتبط', 'حداقل ۲ مقاله از quantum_article.', 'quantum-entanglement-explained' );
	qpedia_glossary_field_text( $post->ID, '_qp_term_related_scientists', 'دانشمندان مرتبط', 'اسلاگ صفحهٔ دانشمند.', 'albert-einstein, max-planck' );
}

function qpedia_glossary_box_seo( $post ) {
	qpedia_glossary_field_text( $post->ID, '_qp_term_focus', 'کلیدواژهٔ کانونی', 'باید در عنوان، ۱۰۰ کلمهٔ نخست و متا دیسکریپشن بیاید.', 'درهم‌تنیدگی کوانتومی' );
	qpedia_glossary_field_textarea( $post->ID, '_qp_term_meta_desc', 'متا دیسکریپشن (۱۲۰ تا ۱۵۸ نویسه)', 'اگر خالی بماند، از «تعریف کوتاه» ساخته می‌شود.', 2 );
	qpedia_glossary_field_textarea( $post->ID, '_qp_term_faq', 'پرسش‌وپاسخ — هر خط: پرسش | پاسخ', 'برای اسکیمای FAQPage. پرسش فارسی با «؟» پایان یابد.', 5 );
	qpedia_glossary_field_textarea( $post->ID, '_qp_term_sources', 'منابع — هر خط: عنوان | آدرس', 'فقط منابع معتبر (Nature، Nobel، Britannica، Stanford، DOI، دانشگاه‌ها).', 6, 'ltr' );
	qpedia_glossary_field_text( $post->ID, '_qp_term_reviewer', 'بازبینی علمی توسط', 'برای E-E-A-T.', 'دکتر ... ، فیزیک کوانتوم' );
	qpedia_glossary_field_text( $post->ID, '_qp_term_reviewed', 'تاریخ آخرین بازبینی', 'مثال: ۱۴۰۴/۰۷/۰۲', '' );

	$noindex = (string) get_post_meta( $post->ID, '_qp_term_noindex', true );
	echo '<p style="margin:0"><label><input type="checkbox" name="_qp_term_noindex" value="1"' . checked( $noindex, '1', false ) . ' /> این اصطلاح در گوگل ایندکس نشود (noindex)</label></p>';
}

/* ──────────────────────────────────────────────────────────────
   ۴. ذخیرهٔ فیلدها
   ────────────────────────────────────────────────────────────── */
add_action( 'save_post_' . QPEDIA_GLOSSARY_POST_TYPE, 'qpedia_glossary_save_meta', 10, 2 );
function qpedia_glossary_save_meta( $post_id, $post ) {
	if ( ! isset( $_POST['qpedia_glossary_meta_nonce'] ) ) {
		return;
	}

	if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['qpedia_glossary_meta_nonce'] ) ), 'qpedia_glossary_save_meta' ) ) {
		return;
	}

	if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
		return;
	}

	if ( ! current_user_can( 'edit_post', $post_id ) ) {
		return;
	}

	foreach ( qpedia_glossary_meta_fields() as $key => $type ) {
		if ( '_qp_term_noindex' === $key ) {
			$value = isset( $_POST[ $key ] ) ? '1' : '';
			update_post_meta( $post_id, $key, $value );
			continue;
		}

		if ( ! isset( $_POST[ $key ] ) ) {
			continue;
		}

		$raw = wp_unslash( $_POST[ $key ] );

		if ( 'textarea' === $type ) {
			$value = sanitize_textarea_field( $raw );
		} else {
			$value = sanitize_text_field( $raw );
		}

		update_post_meta( $post_id, $key, $value );
	}

	// هم‌گام‌سازی کلیدواژهٔ کانونی با Rank Math (اگر نصب بود).
	$focus = (string) get_post_meta( $post_id, '_qp_term_focus', true );
	if ( '' !== $focus && defined( 'RANK_MATH_VERSION' ) ) {
		update_post_meta( $post_id, 'rank_math_focus_keyword', $focus );
	}

	qpedia_glossary_enforce_latin_slug( $post_id );
	qpedia_glossary_clear_cache();
}

/* ──────────────────────────────────────────────────────────────
   ۵. اجبار اسلاگ انگلیسی + ۳۰۱ خودکار از اسلاگ قدیمی
   ────────────────────────────────────────────────────────────── */

/**
 * آیا اسلاگ کاملاً لاتین است؟
 */
function qpedia_glossary_is_latin_slug( $slug ) {
	$slug = urldecode( (string) $slug );
	return (bool) preg_match( '/^[a-z0-9][a-z0-9\-]*$/', $slug );
}

/**
 * نویسه‌گردانی سادهٔ فارسی به لاتین (پشتیبان، وقتی نام انگلیسی خالی است).
 */
function qpedia_glossary_transliterate( $text ) {
	$map = array(
		'آ' => 'a', 'ا' => 'a', 'أ' => 'a', 'إ' => 'e', 'ب' => 'b', 'پ' => 'p', 'ت' => 't', 'ث' => 's',
		'ج' => 'j', 'چ' => 'ch', 'ح' => 'h', 'خ' => 'kh', 'د' => 'd', 'ذ' => 'z', 'ر' => 'r', 'ز' => 'z',
		'ژ' => 'zh', 'س' => 's', 'ش' => 'sh', 'ص' => 's', 'ض' => 'z', 'ط' => 't', 'ظ' => 'z', 'ع' => 'a',
		'غ' => 'gh', 'ف' => 'f', 'ق' => 'gh', 'ک' => 'k', 'ك' => 'k', 'گ' => 'g', 'ل' => 'l', 'م' => 'm',
		'ن' => 'n', 'و' => 'v', 'ه' => 'h', 'ی' => 'y', 'ي' => 'y', 'ء' => '', 'ئ' => 'y', 'ؤ' => 'o',
		'ّ' => '', 'َ' => 'a', 'ِ' => 'e', 'ُ' => 'o', '‌' => '-', ' ' => '-', '_' => '-',
		'۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4', '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9',
	);

	$text = strtr( (string) $text, $map );
	$text = strtolower( $text );
	$text = preg_replace( '/[^a-z0-9\-]+/', '-', $text );
	$text = preg_replace( '/-+/', '-', $text );

	return trim( (string) $text, '-' );
}

/**
 * اگر اسلاگ لاتین نیست، از «نام انگلیسی» بسازش.
 * وردپرس خودش _wp_old_slug را ثبت می‌کند، پس ریدایرکت ۳۰۱ خودکار برقرار می‌شود.
 */
function qpedia_glossary_enforce_latin_slug( $post_id ) {
	$post = get_post( $post_id );
	if ( ! $post instanceof WP_Post || QPEDIA_GLOSSARY_POST_TYPE !== $post->post_type ) {
		return;
	}

	if ( in_array( $post->post_status, array( 'auto-draft', 'inherit', 'trash' ), true ) ) {
		return;
	}

	$current = urldecode( (string) $post->post_name );
	if ( '' !== $current && qpedia_glossary_is_latin_slug( $current ) ) {
		return;
	}

	$en      = qpedia_glossary_en_title( $post_id );
	$desired = '' !== $en ? sanitize_title( $en ) : '';

	if ( '' === $desired || ! qpedia_glossary_is_latin_slug( $desired ) ) {
		$desired = qpedia_glossary_transliterate( '' !== $en ? $en : $post->post_title );
	}

	if ( '' === $desired ) {
		return;
	}

	$desired = wp_unique_post_slug( $desired, $post_id, $post->post_status, $post->post_type, $post->post_parent );

	if ( $desired === $post->post_name ) {
		return;
	}

	remove_action( 'save_post_' . QPEDIA_GLOSSARY_POST_TYPE, 'qpedia_glossary_save_meta', 10 );
	wp_update_post(
		array(
			'ID'        => $post_id,
			'post_name' => $desired,
		)
	);
	add_action( 'save_post_' . QPEDIA_GLOSSARY_POST_TYPE, 'qpedia_glossary_save_meta', 10, 2 );
}

/**
 * پشتیبان ریدایرکت: /glossary/<اسلاگ-قدیمی>/ و /terms/<اسلاگ>/ → اسلاگ تازه.
 *
 * هستهٔ وردپرس خودش _wp_old_slug را ۳۰۱ می‌کند؛ این تابع فقط مسیرهای
 * قدیمی‌تر (terms/ و vocabulary/) و حالت‌های نامتعارف را پوشش می‌دهد.
 */
add_action( 'template_redirect', 'qpedia_glossary_legacy_redirect', 5 );
function qpedia_glossary_legacy_redirect() {
	if ( is_admin() || ! is_404() ) {
		return;
	}

	$request = isset( $_SERVER['REQUEST_URI'] ) ? wp_unslash( $_SERVER['REQUEST_URI'] ) : '';
	$path    = trim( (string) wp_parse_url( $request, PHP_URL_PATH ), '/' );

	if ( '' === $path ) {
		return;
	}

	$parts = explode( '/', $path );
	if ( count( $parts ) < 2 ) {
		return;
	}

	$base = strtolower( $parts[0] );
	if ( ! in_array( $base, array( QPEDIA_GLOSSARY_BASE, 'terms', 'vocabulary' ), true ) ) {
		return;
	}

	$raw  = (string) end( $parts );
	$slug = urldecode( $raw );

	if ( '' === $slug ) {
		return;
	}

	$post = get_page_by_path( $slug, OBJECT, QPEDIA_GLOSSARY_POST_TYPE );

	// پیدا نشد؟ از روی اسلاگ‌های قدیمی ثبت‌شده در _wp_old_slug جست‌وجو کن.
	if ( ! $post instanceof WP_Post ) {
		$post = qpedia_glossary_find_by_old_slug( $raw );
	}

	if ( ! $post instanceof WP_Post ) {
		return;
	}

	wp_safe_redirect( get_permalink( $post ), 301 );
	exit;
}

/**
 * یافتن مدخل از روی _wp_old_slug (با تحمل تفاوت کدگذاری حروف).
 *
 * @param string $raw_segment بخش آخر مسیر، همان‌طور که در URL آمده.
 * @return WP_Post|null
 */
function qpedia_glossary_find_by_old_slug( $raw_segment ) {
	$ids = get_posts(
		array(
			'post_type'      => QPEDIA_GLOSSARY_POST_TYPE,
			'post_status'    => array( 'publish', 'draft', 'pending', 'private' ),
			'posts_per_page' => 500,
			'meta_key'       => '_wp_old_slug',
			'no_found_rows'  => true,
			'fields'         => 'ids',
		)
	);

	if ( empty( $ids ) ) {
		return null;
	}

	$needle = urldecode( trim( (string) $raw_segment, '/' ) );

	foreach ( $ids as $id ) {
		$old = (array) get_post_meta( (int) $id, '_wp_old_slug' );

		foreach ( $old as $candidate ) {
			if ( 0 === strcasecmp( urldecode( (string) $candidate ), $needle ) ) {
				$post = get_post( (int) $id );
				if ( $post instanceof WP_Post ) {
					return $post;
				}
			}
		}
	}

	return null;
}

/* ──────────────────────────────────────────────────────────────
   ۶. ستون‌های فهرست پیشخوان
   ────────────────────────────────────────────────────────────── */
add_filter( 'manage_' . QPEDIA_GLOSSARY_POST_TYPE . '_posts_columns', 'qpedia_glossary_admin_columns' );
function qpedia_glossary_admin_columns( $columns ) {
	$new = array();
	foreach ( $columns as $key => $label ) {
		$new[ $key ] = $label;
		if ( 'title' === $key ) {
			$new['qp_en']    = 'نام انگلیسی';
			$new['qp_slug']  = 'اسلاگ';
			$new['qp_short'] = 'تعریف کوتاه';
			$new['qp_seo']   = 'سئو';
		}
	}
	return $new;
}

add_action( 'manage_' . QPEDIA_GLOSSARY_POST_TYPE . '_posts_custom_column', 'qpedia_glossary_admin_column_content', 10, 2 );
function qpedia_glossary_admin_column_content( $column, $post_id ) {
	if ( 'qp_en' === $column ) {
		$en = qpedia_glossary_en_title( $post_id );
		echo $en ? esc_html( $en ) : '<span style="color:#d63638">— خالی</span>';
		return;
	}

	if ( 'qp_slug' === $column ) {
		$post = get_post( $post_id );
		$slug = $post ? urldecode( (string) $post->post_name ) : '';
		if ( qpedia_glossary_is_latin_slug( $slug ) ) {
			echo '<code>' . esc_html( $slug ) . '</code>';
		} else {
			echo '<span style="color:#d63638">✗ ' . esc_html( $slug ) . '</span>';
		}
		return;
	}

	if ( 'qp_short' === $column ) {
		$short = qpedia_glossary_short_definition( $post_id );
		$len   = function_exists( 'mb_strlen' ) ? mb_strlen( $short, 'UTF-8' ) : strlen( $short );
		$color = ( $len >= 60 && $len <= 240 ) ? '#008a20' : '#996800';
		echo '<span style="color:' . esc_attr( $color ) . '">' . esc_html( number_format_i18n( $len ) ) . ' نویسه</span>';
		return;
	}

	if ( 'qp_seo' === $column ) {
		$issues = array();
		if ( '' === qpedia_glossary_en_title( $post_id ) ) {
			$issues[] = 'نام انگلیسی';
		}
		if ( '' === trim( (string) get_post_meta( $post_id, '_qp_term_short', true ) ) ) {
			$issues[] = 'تعریف کوتاه';
		}
		if ( '' === trim( (string) get_post_meta( $post_id, '_qp_term_sources', true ) ) ) {
			$issues[] = 'منابع';
		}
		if ( '' === trim( (string) get_post_meta( $post_id, '_qp_term_related_articles', true ) ) ) {
			$issues[] = 'لینک داخلی';
		}

		if ( empty( $issues ) ) {
			echo '<span style="color:#008a20">✓ کامل</span>';
		} else {
			echo '<span style="color:#d63638">کمبود: ' . esc_html( implode( '، ', $issues ) ) . '</span>';
		}
	}
}

/* ──────────────────────────────────────────────────────────────
   ۷. flush پس از تعویض/به‌روزرسانی قالب
   ────────────────────────────────────────────────────────────── */
add_action( 'after_switch_theme', 'qpedia_glossary_after_theme_switch' );
function qpedia_glossary_after_theme_switch() {
	qpedia_register_glossary_post_type();
	flush_rewrite_rules();
}

/* ──────────────────────────────────────────────────────────────
   ۸. داده‌های اولیه + مهاجرت اسلاگ‌های فارسیِ نسخهٔ قبل
   ────────────────────────────────────────────────────────────── */

/**
 * نگاشت اصطلاح‌های پایه: عنوان فارسی → اسلاگ/نام انگلیسی/تعریف کوتاه.
 */
function qpedia_glossary_seed_map() {
	return array(
		'برهم‌نهی کوانتومی'     => array( 'quantum-superposition', 'Quantum Superposition', 'حالتی که در آن سامانه با ترکیبی از چند امکان توصیف می‌شود؛ اندازه‌گیری یکی از نتیجه‌های مجاز را ثبت می‌کند.' ),
		'درهم‌تنیدگی کوانتومی'  => array( 'quantum-entanglement', 'Quantum Entanglement', 'ویژگی حالت مشترک چند سامانه که هم‌بستگی‌های آن را نمی‌توان با حالت مستقل هر بخش توضیح داد.' ),
		'واهمدوسی'              => array( 'quantum-decoherence', 'Quantum Decoherence', 'پخش‌شدن اطلاعات فاز سامانه در محیط که مشاهدهٔ تداخل کوانتومی را دشوار می‌کند.' ),
		'تونل‌زنی کوانتومی'     => array( 'quantum-tunneling', 'Quantum Tunneling', 'امکان عبور حالت کوانتومی از سدی که عبور از آن در فیزیک کلاسیک با انرژی موجود ممکن نیست.' ),
		'تابع موج'              => array( 'wave-function', 'Wave Function', 'ساختار ریاضی‌ای که دامنه‌های احتمال و اطلاعات قابل پیش‌بینی دربارهٔ حالت کوانتومی را رمزگذاری می‌کند.' ),
		'کیوبیت'                => array( 'qubit', 'Qubit', 'واحد اطلاعات کوانتومی که می‌تواند در برهم‌نهی حالت‌های پایهٔ صفر و یک قرار گیرد.' ),
		'فوتون'                 => array( 'photon', 'Photon', 'کوانتوم میدان الکترومغناطیسی و حامل برهم‌کنش الکترومغناطیسی.' ),
		'اصل عدم قطعیت'         => array( 'uncertainty-principle', 'Uncertainty Principle', 'محدودیت بنیادی بر پراکندگی هم‌زمان بعضی زوج کمیت‌های ناسازگار، مانند مکان و تکانه.' ),
		'تراز انرژی'            => array( 'energy-level', 'Energy Level', 'یکی از مقدارهای مجاز انرژی برای یک سامانهٔ کوانتومی مقید.' ),
		'اسپین'                 => array( 'spin', 'Spin', 'تکانهٔ زاویه‌ای ذاتی کوانتومی؛ ویژگی بنیادی ذره است و چرخش کلاسیکی جسم نیست.' ),
		'ابررسانایی'            => array( 'superconductivity', 'Superconductivity', 'فازی از ماده با مقاومت الکتریکی صفر و پاسخ مغناطیسی ویژه در شرایط مناسب.' ),
		'پیوند جوزفسون'         => array( 'josephson-junction', 'Josephson Junction', 'دو ابررسانا با مانعی نازک میان آن‌ها که جریان کوانتومی می‌تواند از مانع عبور کند.' ),
		'تصحیح خطای کوانتومی'   => array( 'quantum-error-correction', 'Quantum Error Correction', 'رمزگذاری اطلاعات در چند کیوبیت برای آشکارسازی و اصلاح خطا بدون اندازه‌گیری مستقیم محتوای منطقی.' ),
		'گرانش کوانتومی'        => array( 'quantum-gravity', 'Quantum Gravity', 'حوزه‌ای که می‌کوشد توصیف کوانتومی سازگاری از گرانش و فضا‑زمان بسازد.' ),
		'خلأ کوانتومی'          => array( 'quantum-vacuum', 'Quantum Vacuum', 'کم‌انرژی‌ترین حالت میدان‌های کوانتومی؛ خلأ به معنی نبود کامل میدان و ساختار نیست.' ),
	);
}

add_action( 'admin_init', 'qpedia_glossary_seed_once' );
function qpedia_glossary_seed_once() {
	if ( '2' === get_option( 'qpedia_glossary_seed_version' ) ) {
		return;
	}

	$seed    = qpedia_glossary_seed_map();
	$changed = false;

	foreach ( $seed as $title => $data ) {
		list( $slug, $en, $definition ) = $data;

		$existing = get_page_by_path( $slug, OBJECT, QPEDIA_GLOSSARY_POST_TYPE );

		if ( ! $existing instanceof WP_Post ) {
			$query = get_posts(
				array(
					'post_type'      => QPEDIA_GLOSSARY_POST_TYPE,
					'post_status'    => array( 'publish', 'draft', 'pending', 'private' ),
					'posts_per_page' => 1,
					'title'          => $title,
					'no_found_rows'  => true,
				)
			);

			if ( ! empty( $query ) ) {
				$existing = $query[0];
			}
		}

		if ( $existing instanceof WP_Post ) {
			// مهاجرت اسلاگ فارسی → انگلیسی (وردپرس خودش ۳۰۱ می‌سازد).
			if ( ! qpedia_glossary_is_latin_slug( urldecode( (string) $existing->post_name ) ) ) {
				wp_update_post(
					array(
						'ID'        => $existing->ID,
						'post_name' => $slug,
					)
				);
				$changed = true;
			}

			if ( '' === qpedia_glossary_en_title( $existing->ID ) ) {
				update_post_meta( $existing->ID, '_qp_term_en', $en );
			}

			if ( '' === trim( (string) get_post_meta( $existing->ID, '_qp_term_short', true ) ) ) {
				update_post_meta( $existing->ID, '_qp_term_short', $definition );
			}

			continue;
		}

		$new_id = wp_insert_post(
			array(
				'post_type'    => QPEDIA_GLOSSARY_POST_TYPE,
				'post_status'  => 'publish',
				'post_title'   => $title,
				'post_name'    => $slug,
				'post_excerpt' => $definition,
				'post_content' => '<p>' . esc_html( $definition ) . '</p>',
			)
		);

		if ( $new_id && ! is_wp_error( $new_id ) ) {
			update_post_meta( $new_id, '_qp_term_en', $en );
			update_post_meta( $new_id, '_qp_term_short', $definition );
			$changed = true;
		}
	}

	update_option( 'qpedia_glossary_seed_version', '2', false );
	qpedia_glossary_clear_cache();

	if ( $changed ) {
		flush_rewrite_rules( false );
	}
}

/* ──────────────────────────────────────────────────────────────
   ۹. اصلاح گروهی اسلاگ‌های فارسی (برای مدخل‌های غیر seed)
   ────────────────────────────────────────────────────────────── */
add_action( 'admin_init', 'qpedia_glossary_fix_persian_slugs' );
function qpedia_glossary_fix_persian_slugs() {
	if ( '1' === get_option( 'qpedia_glossary_slug_fix_version' ) ) {
		return;
	}

	$posts = get_posts(
		array(
			'post_type'      => QPEDIA_GLOSSARY_POST_TYPE,
			'post_status'    => array( 'publish', 'draft', 'pending', 'private' ),
			'posts_per_page' => 500,
			'no_found_rows'  => true,
		)
	);

	foreach ( $posts as $post ) {
		if ( qpedia_glossary_is_latin_slug( urldecode( (string) $post->post_name ) ) ) {
			continue;
		}
		qpedia_glossary_enforce_latin_slug( $post->ID );
	}

	update_option( 'qpedia_glossary_slug_fix_version', '1', false );
	qpedia_glossary_clear_cache();
	flush_rewrite_rules( false );
}
