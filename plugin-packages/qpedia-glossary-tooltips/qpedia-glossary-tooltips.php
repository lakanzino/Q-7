<?php
/**
 * Plugin Name: Qpedia Glossary Tooltips
 * Description: واژه‌نامه مدیریتی و نمایش تعریف کوتاه اصطلاحات پیچیده با کلیک، لمس یا صفحه‌کلید داخل مقاله‌های Qpedia.
 * Version: 1.0.0
 * Author: Qpedia Editorial
 * Text Domain: qpedia-glossary-tooltips
 */

defined( 'ABSPATH' ) || exit;

define( 'QPGT_VERSION', '1.0.0' );
define( 'QPGT_POST_TYPE', 'qp_glossary' );

add_action( 'init', 'qpgt_register_glossary' );
function qpgt_register_glossary() {
	register_post_type(
		QPGT_POST_TYPE,
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

add_action( 'add_meta_boxes_' . QPGT_POST_TYPE, 'qpgt_add_alias_box' );
function qpgt_add_alias_box() {
	add_meta_box( 'qpgt-aliases', 'صورت‌های جایگزین', 'qpgt_alias_box', QPGT_POST_TYPE, 'side', 'default' );
}

function qpgt_alias_box( $post ) {
	wp_nonce_field( 'qpgt_save_aliases', 'qpgt_alias_nonce' );
	$value = get_post_meta( $post->ID, '_qpgt_aliases', true );
	echo '<p><label for="qpgt_aliases">هر صورت جایگزین را در یک خط بنویسید.</label></p>';
	echo '<textarea id="qpgt_aliases" name="qpgt_aliases" rows="7" style="width:100%;direction:rtl">' . esc_textarea( $value ) . '</textarea>';
}

add_action( 'save_post_' . QPGT_POST_TYPE, 'qpgt_save_aliases' );
function qpgt_save_aliases( $post_id ) {
	if ( ! isset( $_POST['qpgt_alias_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['qpgt_alias_nonce'] ) ), 'qpgt_save_aliases' ) ) return;
	if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) return;
	if ( ! current_user_can( 'edit_post', $post_id ) ) return;
	$value = isset( $_POST['qpgt_aliases'] ) ? sanitize_textarea_field( wp_unslash( $_POST['qpgt_aliases'] ) ) : '';
	update_post_meta( $post_id, '_qpgt_aliases', $value );
	delete_transient( 'qpgt_terms_v1' );
}

foreach ( array( 'save_post_' . QPGT_POST_TYPE, 'deleted_post', 'trashed_post', 'untrashed_post' ) as $hook ) {
	add_action( $hook, 'qpgt_clear_cache' );
}
function qpgt_clear_cache() { delete_transient( 'qpgt_terms_v1' ); }

function qpgt_get_terms() {
	$cached = get_transient( 'qpgt_terms_v1' );
	if ( is_array( $cached ) ) return $cached;
	$posts = get_posts( array( 'post_type' => QPGT_POST_TYPE, 'post_status' => 'publish', 'posts_per_page' => 500, 'orderby' => 'title', 'order' => 'ASC', 'no_found_rows' => true ) );
	$terms = array();
	foreach ( $posts as $post ) {
		$definition = trim( wp_strip_all_tags( $post->post_excerpt ?: $post->post_content, true ) );
		if ( '' === $definition ) continue;
		$forms = array( $post->post_title );
		$aliases = preg_split( '/\R/u', (string) get_post_meta( $post->ID, '_qpgt_aliases', true ) );
		foreach ( $aliases as $alias ) if ( trim( $alias ) !== '' ) $forms[] = trim( $alias );
		foreach ( array_unique( $forms ) as $form ) {
			$terms[] = array( 'term' => $form, 'definition' => $definition, 'url' => get_permalink( $post ) );
		}
	}
	usort( $terms, function( $a, $b ) { return mb_strlen( $b['term'] ) <=> mb_strlen( $a['term'] ); } );
	set_transient( 'qpgt_terms_v1', $terms, DAY_IN_SECONDS );
	return $terms;
}

add_filter( 'the_content', 'qpgt_highlight_article_terms', 18 );
function qpgt_highlight_article_terms( $content ) {
	if ( is_admin() || is_feed() || ! is_singular( 'quantum_article' ) || ! in_the_loop() || ! is_main_query() ) return $content;
	$terms = qpgt_get_terms();
	if ( empty( $terms ) || ! class_exists( 'DOMDocument' ) ) return $content;

	$previous = libxml_use_internal_errors( true );
	$dom = new DOMDocument( '1.0', 'UTF-8' );
	$wrapped = '<div id="qpgt-root">' . $content . '</div>';
	$dom->loadHTML( '<?xml encoding="utf-8" ?>' . $wrapped, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD );
	$xpath = new DOMXPath( $dom );
	$nodes = $xpath->query( '//div[@id="qpgt-root"]//text()[normalize-space(.) != "" and not(ancestor::a) and not(ancestor::button) and not(ancestor::code) and not(ancestor::pre) and not(ancestor::script) and not(ancestor::style) and not(ancestor::h1) and not(ancestor::h2) and not(ancestor::h3) and not(ancestor::h4) and not(ancestor::h5) and not(ancestor::h6) and not(ancestor::*[contains(concat(" ", normalize-space(@class), " "), " qpgt-term ")])]' );
	$used = array();
	$count = 0;
	$max = (int) apply_filters( 'qpgt_max_terms_per_article', 12 );

	foreach ( iterator_to_array( $nodes ) as $node ) {
		if ( $count >= $max ) break;
		$text = $node->nodeValue;
		foreach ( $terms as $entry ) {
			$key = mb_strtolower( $entry['term'], 'UTF-8' );
			if ( isset( $used[ $key ] ) ) continue;
			$pattern = '/(?<![\p{L}\p{N}_])(' . preg_quote( $entry['term'], '/' ) . ')(?![\p{L}\p{N}_])/u';
			if ( ! preg_match( $pattern, $text, $match, PREG_OFFSET_CAPTURE ) ) continue;
			$matched = $match[1][0]; $offset = $match[1][1];
			$before = substr( $text, 0, $offset ); $after = substr( $text, $offset + strlen( $matched ) );
			$fragment = $dom->createDocumentFragment();
			if ( $before !== '' ) $fragment->appendChild( $dom->createTextNode( $before ) );
			$button = $dom->createElement( 'button' );
			$button->setAttribute( 'type', 'button' );
			$button->setAttribute( 'class', 'qpgt-term' );
			$button->setAttribute( 'aria-expanded', 'false' );
			$button->setAttribute( 'data-qpgt-definition', $entry['definition'] );
			$button->setAttribute( 'data-qpgt-url', $entry['url'] );
			$button->appendChild( $dom->createTextNode( $matched ) );
			$fragment->appendChild( $button );
			if ( $after !== '' ) $fragment->appendChild( $dom->createTextNode( $after ) );
			$node->parentNode->replaceChild( $fragment, $node );
			$used[ $key ] = true; $count++; break;
		}
	}
	$root = $dom->getElementById( 'qpgt-root' );
	$output = '';
	if ( $root ) foreach ( $root->childNodes as $child ) $output .= $dom->saveHTML( $child );
	libxml_clear_errors(); libxml_use_internal_errors( $previous );
	return $output ?: $content;
}

add_action( 'wp_enqueue_scripts', 'qpgt_enqueue_assets' );
function qpgt_enqueue_assets() {
	if ( ! is_singular( 'quantum_article' ) ) return;
	$base = plugin_dir_url( __FILE__ ); $dir = plugin_dir_path( __FILE__ );
	wp_enqueue_style( 'qpedia-glossary-tooltips', $base . 'assets/glossary.css', array(), filemtime( $dir . 'assets/glossary.css' ) );
	wp_enqueue_script( 'qpedia-glossary-tooltips', $base . 'assets/glossary.js', array(), filemtime( $dir . 'assets/glossary.js' ), true );
}

register_activation_hook( __FILE__, 'qpgt_activate' );
function qpgt_activate() {
	qpgt_register_glossary();
	qpgt_seed_terms();
	flush_rewrite_rules();
}
register_deactivation_hook( __FILE__, 'qpgt_deactivate' );
function qpgt_deactivate() { flush_rewrite_rules(); }

function qpgt_seed_terms() {
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
		'گرانش کوانتومی' => 'نام حوزه‌ای که می‌کوشد توصیف کوانتومی سازگاری از گرانش و فضاـزمان بسازد.',
		'خلأ کوانتومی' => 'کم‌انرژی‌ترین حالت میدان‌های کوانتومی؛ خلأ به معنی نبود کامل میدان و ساختار نیست.',
	);
	foreach ( $seed as $title => $definition ) {
		if ( get_page_by_title( $title, OBJECT, QPGT_POST_TYPE ) ) continue;
		wp_insert_post( array( 'post_type' => QPGT_POST_TYPE, 'post_status' => 'publish', 'post_title' => $title, 'post_excerpt' => $definition, 'post_content' => '<p>' . esc_html( $definition ) . '</p>' ) );
	}
	delete_transient( 'qpgt_terms_v1' );
}
