<?php
/**
 * Glossary module — post type and editorial fields.
 *
 * @package Quantum_Pedia_Child
 */

defined( 'ABSPATH' ) || exit;

define( 'QPEDIA_GLOSSARY_POST_TYPE', 'qp_glossary' );

add_action( 'init', 'qpedia_register_glossary_post_type' );
function qpedia_register_glossary_post_type() {
	register_post_type(
		QPEDIA_GLOSSARY_POST_TYPE,
		array(
			'labels' => array(
				'name'               => 'اصطلاحات',
				'singular_name'      => 'اصطلاح',
				'menu_name'          => 'اصطلاحات',
				'add_new'            => 'افزودن اصطلاح',
				'add_new_item'       => 'افزودن اصطلاح تازه',
				'edit_item'          => 'ویرایش اصطلاح',
				'new_item'           => 'اصطلاح تازه',
				'view_item'          => 'مشاهده اصطلاح',
				'search_items'       => 'جست‌وجوی اصطلاحات',
				'not_found'          => 'اصطلاحی پیدا نشد.',
				'not_found_in_trash' => 'اصطلاحی در زباله‌دان نیست.',
			),
			'public'       => true,
			'show_in_rest' => true,
			'menu_icon'    => 'dashicons-book-alt',
			'has_archive'  => 'glossary',
			'rewrite'      => array( 'slug' => 'glossary', 'with_front' => false ),
			'supports'     => array( 'title', 'editor', 'excerpt' ),
		)
	);
}

add_action( 'add_meta_boxes_' . QPEDIA_GLOSSARY_POST_TYPE, 'qpedia_glossary_add_alias_box' );
function qpedia_glossary_add_alias_box() {
	add_meta_box( 'qpedia-glossary-aliases', 'صورت‌های جایگزین', 'qpedia_glossary_alias_box', QPEDIA_GLOSSARY_POST_TYPE, 'side', 'default' );
}

function qpedia_glossary_alias_box( $post ) {
	wp_nonce_field( 'qpedia_glossary_save_aliases', 'qpedia_glossary_alias_nonce' );
	$value = get_post_meta( $post->ID, '_qpedia_glossary_aliases', true );
	echo '<p><label for="qpedia_glossary_aliases">هر صورت جایگزین را در یک خط بنویسید.</label></p>';
	echo '<textarea id="qpedia_glossary_aliases" name="qpedia_glossary_aliases" rows="7" style="width:100%;direction:rtl">' . esc_textarea( $value ) . '</textarea>';
}

add_action( 'save_post_' . QPEDIA_GLOSSARY_POST_TYPE, 'qpedia_glossary_save_aliases' );
function qpedia_glossary_save_aliases( $post_id ) {
	if ( ! isset( $_POST['qpedia_glossary_alias_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['qpedia_glossary_alias_nonce'] ) ), 'qpedia_glossary_save_aliases' ) ) return;
	if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) return;
	if ( ! current_user_can( 'edit_post', $post_id ) ) return;
	$value = isset( $_POST['qpedia_glossary_aliases'] ) ? sanitize_textarea_field( wp_unslash( $_POST['qpedia_glossary_aliases'] ) ) : '';
	update_post_meta( $post_id, '_qpedia_glossary_aliases', $value );
	qpedia_glossary_clear_cache();
}

add_action( 'after_switch_theme', 'qpedia_glossary_after_theme_switch' );
function qpedia_glossary_after_theme_switch() {
	qpedia_register_glossary_post_type();
	flush_rewrite_rules();
}

add_action( 'admin_init', 'qpedia_glossary_seed_once' );
function qpedia_glossary_seed_once() {
	if ( get_option( 'qpedia_glossary_seed_version' ) === '1' ) return;
	$seed = array(
		'برهم‌نهی کوانتومی' => 'حالتی که در آن سامانه با ترکیبی از چند امکان توصیف می‌شود؛ اندازه‌گیری یکی از نتیجه‌های مجاز را ثبت می‌کند.',
		'درهم‌تنیدگی کوانتومی' => 'ویژگی حالت مشترک چند سامانه که هم‌بستگی‌های آن را نمی‌توان با حالت مستقل هر بخش توضیح داد.',
		'واهمدوسی' => 'پخش‌شدن اطلاعات فاز سامانه در محیط که مشاهده تداخل کوانتومی را دشوار می‌کند.',
		'تونل‌زنی کوانتومی' => 'امکان عبور حالت کوانتومی از سدی که عبور از آن در فیزیک کلاسیک با انرژی موجود ممکن نیست.',
		'تابع موج' => 'ساختار ریاضی‌ای که دامنه‌های احتمال و اطلاعات قابل پیش‌بینی درباره حالت کوانتومی را رمزگذاری می‌کند.',
		'کیوبیت' => 'واحد اطلاعات کوانتومی که می‌تواند در برهم‌نهی حالت‌های پایه صفر و یک قرار گیرد.',
		'فوتون' => 'کوانتوم میدان الکترومغناطیسی و حامل برهم‌کنش الکترومغناطیسی.',
		'اصل عدم قطعیت' => 'محدودیت بنیادی بر پراکندگی هم‌زمان بعضی زوج کمیت‌های ناسازگار، مانند مکان و تکانه.',
		'تراز انرژی' => 'یکی از مقدارهای مجاز انرژی برای یک سامانه کوانتومی مقید.',
		'اسپین' => 'تکانه زاویه‌ای ذاتی کوانتومی؛ ویژگی بنیادی ذره است و چرخش کلاسیکی جسم نیست.',
		'ابررسانایی' => 'فازی از ماده با مقاومت الکتریکی صفر و پاسخ مغناطیسی ویژه در شرایط مناسب.',
		'پیوند جوزفسون' => 'دو ابررسانا با مانعی نازک میان آن‌ها که جریان کوانتومی می‌تواند از مانع عبور کند.',
		'تصحیح خطای کوانتومی' => 'رمزگذاری اطلاعات در چند کیوبیت برای آشکارسازی و اصلاح خطا بدون اندازه‌گیری مستقیم محتوای منطقی.',
		'گرانش کوانتومی' => 'حوزه‌ای که می‌کوشد توصیف کوانتومی سازگاری از گرانش و فضاـزمان بسازد.',
		'خلأ کوانتومی' => 'کم‌انرژی‌ترین حالت میدان‌های کوانتومی؛ خلأ به معنی نبود کامل میدان و ساختار نیست.',
	);
	foreach ( $seed as $title => $definition ) {
		if ( get_page_by_title( $title, OBJECT, QPEDIA_GLOSSARY_POST_TYPE ) ) continue;
		wp_insert_post( array( 'post_type' => QPEDIA_GLOSSARY_POST_TYPE, 'post_status' => 'publish', 'post_title' => $title, 'post_excerpt' => $definition, 'post_content' => '<p>' . esc_html( $definition ) . '</p>' ) );
	}
	update_option( 'qpedia_glossary_seed_version', '1', false );
	qpedia_glossary_clear_cache();
	flush_rewrite_rules( false );
}
