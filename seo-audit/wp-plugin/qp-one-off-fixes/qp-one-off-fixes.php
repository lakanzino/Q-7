<?php
/**
 * Plugin Name:       QP One-Off Fixes — ابزار اصلاح یک‌بارمصرف qpedia.ir
 * Description:       اصلاحات مرحله‌ای و قابل‌بازگشت بر اساس ممیزی واقعی محتوا (Q-7 / seo-audit). هر بخش جداگانه، ابتدا «پیش‌نمایش خشک» بزنید و سپس «اعمال» کنید. پس از اتمام کار، افزونه را غیرفعال و حذف کنید.
 * Version:           1.0.0
 * Author:            Arena Agent for qpedia.ir
 * License:           GPL-2.0+
 * Text Domain:       qpof
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

require_once plugin_dir_path( __FILE__ ) . 'qp-fix-data.php';

define( 'QPOF_TABLE', 'qpof_backup' );
define( 'QPOF_OPT_REDIRECTS', 'qpof_redirects_enabled' );

/* ------------------------------------------------------------------ */
/*  جدول پشتیبان                                                       */
/* ------------------------------------------------------------------ */

function qpof_maybe_install() {
	global $wpdb;
	$table = $wpdb->prefix . QPOF_TABLE;
	if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) === $table ) { return; }
	require_once ABSPATH . 'wp-admin/includes/upgrade.php';
	$charset = $wpdb->get_charset_collate();
	$sql = "CREATE TABLE {$table} (
		id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
		pass VARCHAR(40) NOT NULL,
		object_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
		object_field VARCHAR(60) NOT NULL,
		old_value LONGTEXT,
		new_value LONGTEXT,
		created DATETIME NOT NULL,
		PRIMARY KEY (id),
		KEY pass (pass)
	) {$charset};";
	dbDelta( $sql );
}

/* ------------------------------------------------------------------ */
/*  جست‌وجوی پست بر اساس اسلاگ                                         */
/* ------------------------------------------------------------------ */

function qpof_find_post_id( $slug ) {
	$posts = get_posts( array(
		'name'           => $slug,
		'post_type'      => 'any',
		'post_status'    => 'publish',
		'posts_per_page' => 1,
		'fields'         => 'ids',
	) );
	return $posts ? (int) $posts[0] : 0;
}

function qpof_backup( $pass, $object_id, $field, $old, $new ) {
	global $wpdb;
	$wpdb->insert(
		$wpdb->prefix . QPOF_TABLE,
		array(
			'pass'         => $pass,
			'object_id'    => (int) $object_id,
			'object_field' => $field,
			'old_value'    => $old,
			'new_value'    => $new,
			'created'      => current_time( 'mysql' ),
		),
		array( '%s', '%d', '%s', '%s', '%s', '%s' )
	);
}

/* ------------------------------------------------------------------ */
/*  بخش ۱: اصلاح لینک‌های شکسته                                         */
/* ------------------------------------------------------------------ */

function qpof_pass_links( $apply ) {
	$data  = qpof_data();
	$log   = array();
	$total = 0;
	foreach ( $data['link_fixes'] as $slug => $map ) {
		$post_id = qpof_find_post_id( $slug );
		if ( ! $post_id ) { $log[] = "⚠️ {$slug}: پست منتشرشده یافت نشد"; continue; }
		$content = get_post_field( 'post_content', $post_id );
		$new     = $content;
		$hits    = 0;
		foreach ( $map as $old_href => $new_href ) {
			foreach ( array( '"', "'" ) as $q ) {
				$find  = 'href=' . $q . $old_href . $q;
				$rep   = 'href=' . $q . $new_href . $q;
				$count = substr_count( $new, $find );
				if ( $count > 0 ) { $new = str_replace( $find, $rep, $new ); $hits += $count; }
			}
		}
		if ( $hits > 0 ) {
			$total += $hits;
			$log[] = "✔ {$slug} (#{$post_id}): {$hits} لینک جایگزین می‌شود";
			if ( $apply ) {
				$GLOBALS['wpdb']->update( $GLOBALS['wpdb']->posts, array( 'post_content' => $new ), array( 'ID' => $post_id ), array( '%s' ), array( '%d' ) );
				clean_post_cache( $post_id );
				qpof_backup( 'links', $post_id, 'post_content', $content, $new );
			}
		} else {
			$log[] = "— {$slug}: رشتهٔ لینک در محتوا پیدا نشد (شاید قبلاً اصلاح شده)";
		}
	}
	array_unshift( $log, "جمع جایگزینی‌ها: {$total}");
	return $log;
}

/* ------------------------------------------------------------------ */
/*  بخش ۲: بازکردن لینک به صفحات غیرمنتشر (متن بماند، تگ برود)          */
/* ------------------------------------------------------------------ */

function qpof_pass_unlink( $apply ) {
	$data  = qpof_data();
	$log   = array();
	$total = 0;
	foreach ( $data['draft_unlinks'] as $slug => $hrefs ) {
		$post_id = qpof_find_post_id( $slug );
		if ( ! $post_id ) { $log[] = "⚠️ {$slug}: پست منتشرشده یافت نشد"; continue; }
		$content = get_post_field( 'post_content', $post_id );
		$new     = $content;
		$hits    = 0;
		foreach ( $hrefs as $href ) {
			$pattern = '/<a\b[^>]*href\s*=\s*(["\'])' . preg_quote( $href, '/' ) . '\\1[^>]*>(.*?)<\/a\s*>/is';
			$new2    = preg_replace( $pattern, '$2', $new, -1, $count );
			if ( $count > 0 ) { $new = $new2; $hits += $count; }
		}
		if ( $hits > 0 ) {
			$total += $hits;
			$log[] = "✔ {$slug} (#{$post_id}): {$hits} لینکِ به‌غیرمنتشر باز می‌شود (متن حفظ می‌ماند)";
			if ( $apply ) {
				$GLOBALS['wpdb']->update( $GLOBALS['wpdb']->posts, array( 'post_content' => $new ), array( 'ID' => $post_id ), array( '%s' ), array( '%d' ) );
				clean_post_cache( $post_id );
				qpof_backup( 'unlink', $post_id, 'post_content', $content, $new );
			}
		} else {
			$log[] = "— {$slug}: موردی یافت نشد";
		}
	}
	array_unshift( $log, "جمع لینک‌های بازشده: {$total}");
	return $log;
}

/* ------------------------------------------------------------------ */
/*  بخش ۳: تکمیل توضیحات متا (Rank Math) — فقط جاهای خالی              */
/* ------------------------------------------------------------------ */

function qpof_pass_meta( $apply ) {
	$data  = qpof_data();
	$log   = array();
	$total = 0;
	foreach ( $data['meta_fill'] as $slug => $desc ) {
		$post_id = qpof_find_post_id( $slug );
		if ( ! $post_id ) { continue; }
		$current = get_post_meta( $post_id, 'rank_math_description', true );
		if ( $current !== '' ) { $log[] = "— {$slug}: متا از قبل وجود دارد، رد شد"; continue; }
		$total++;
		$log[] = "✔ {$slug} (#{$post_id}): ⩽ " . mb_substr( $desc, 0, 70 ) . '…';
		if ( $apply ) {
			qpof_backup( 'meta', $post_id, 'rank_math_description', $current, $desc );
			update_post_meta( $post_id, 'rank_math_description', $desc );
		}
	}
	array_unshift( $log, "جمع متا پرشده: {$total} — لطفاً پس از اعمال، چندتایی را دستی بازبینی کنید.");
	return $log;
}

/* ------------------------------------------------------------------ */
/*  بخش ۴: تکمیل کلیدواژهٔ کانونی — فقط جاهای خالی                       */
/* ------------------------------------------------------------------ */

function qpof_pass_kw( $apply ) {
	$data  = qpof_data();
	$log   = array();
	$total = 0;
	foreach ( $data['kw_fill'] as $slug => $kw ) {
		$post_id = qpof_find_post_id( $slug );
		if ( ! $post_id ) { continue; }
		$current = get_post_meta( $post_id, 'rank_math_focus_keyword', true );
		if ( $current !== '' ) { continue; }
		$total++;
		$log[] = "✔ {$slug} → «{$kw}»";
		if ( $apply ) {
			qpof_backup( 'kw', $post_id, 'rank_math_focus_keyword', $current, $kw );
			update_post_meta( $post_id, 'rank_math_focus_keyword', $kw );
		}
	}
	array_unshift( $log, "جمع کلیدواژهٔ پرشده: {$total}");
	return $log;
}

/* ------------------------------------------------------------------ */
/*  بخش ۵: ALT تصویر شاخص                                               */
/* ------------------------------------------------------------------ */

function qpof_pass_alt( $apply ) {
	$data  = qpof_data();
	$log   = array();
	$total = 0;
	foreach ( $data['feat_alt'] as $att_id => $alt ) {
		$att_id  = (int) $att_id;
		$current = get_post_meta( $att_id, '_wp_attachment_image_alt', true );
		if ( $current !== '' ) { continue; }
		$total++;
		$log[] = "✔ پیوست #{$att_id}: ⩽ {$alt}";
		if ( $apply ) {
			qpof_backup( 'alt', $att_id, '_wp_attachment_image_alt', $current, $alt );
			update_post_meta( $att_id, '_wp_attachment_image_alt', $alt );
		}
	}
	array_unshift( $log, "جمع ALT پرشده: {$total}");
	return $log;
}

/* ------------------------------------------------------------------ */
/*  بخش ۶: ریدایرکت‌ها (داخل خودِ وردپرس — فقط وقتی صفحه ۴۰۴ است)        */
/* ------------------------------------------------------------------ */

function qpof_pass_redirects_toggle( $apply ) {
	$data = qpof_data();
	$log  = array();
	$log[] = 'قوانین ۳۰۱: ' . count( $data['redirects_301'] ) . ' مورد · قوانین ۴۱۰: ' . count( $data['redirects_410'] ) . ' مورد';
	foreach ( $data['redirects_301'] as $from => $to ) { $log[] = "۳۰۱: {$from} → {$to}"; }
	foreach ( $data['redirects_410'] as $i => $p )      { $log[] = "۴۱۰: {$p}"; }
	if ( $apply ) {
		update_option( QPOF_OPT_REDIRECTS, $data['redirects_301'] );
		update_option( 'qpof_gone_410', array_values( $data['redirects_410'] ) );
		$log[] = '✔ ریدایرکت‌ها فعال شدند. از امروز، هر نشانی که ۴۰۴ شود ابتدا با این نقشه چک می‌شود.';
	}
	return $log;
}

add_action( 'template_redirect', 'qpof_template_redirect', 1 );
function qpof_template_redirect() {
	if ( is_admin() ) { return; }
	$rules = get_option( QPOF_OPT_REDIRECTS );
	$gone  = get_option( 'qpof_gone_410' );
	if ( empty( $rules ) && empty( $gone ) ) { return; }
	if ( ! is_404() ) { return; } // اگر بعداً مقاله‌ای واقعاً منتشر شد، ریدایرکت خودکار قطع می‌شود.
	$path = isset( $_SERVER['REQUEST_URI'] ) ? wp_parse_url( wp_unslash( $_SERVER['REQUEST_URI'] ), PHP_URL_PATH ) : '';
	if ( ! $path ) { return; }
	$path = '/' . trim( $path, '/' ) . '/';
	if ( $path === '//' ) { $path = '/'; }
	if ( ! empty( $rules ) && isset( $rules[ $path ] ) ) {
		wp_safe_redirect( $rules[ $path ], 301 );
		exit;
	}
	if ( ! empty( $gone ) && in_array( $path, $gone, true ) ) {
		status_header( 410 );
		nocache_headers();
		echo '<!doctype html><html lang="fa" dir="rtl"><head><meta charset="utf-8"><title>۴۱۰ — صفحه حذف شده است</title></head><body style="font-family:sans-serif;text-align:center;padding:4em 1em"><h1>این صفحه دیگر وجود ندارد</h1><p>محتوای مورد نظر از کوانتوم پدیا حذف شده است. <a href="https://qpedia.ir/">بازگشت به صفحهٔ اصلی</a></p></body></html>';
		exit;
	}
}

/* ------------------------------------------------------------------ */
/*  بازگردانی (انجام معکوس هر بخش از روی جدول پشتیبان)                 */
/* ------------------------------------------------------------------ */

function qpof_restore( $pass ) {
	global $wpdb;
	$table = $wpdb->prefix . QPOF_TABLE;
	$rows  = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} WHERE pass = %s ORDER BY id DESC", $pass ) );
	$log   = array();
	foreach ( $rows as $r ) {
		if ( $r->object_field === 'post_content' ) {
			$wpdb->update( $wpdb->posts, array( 'post_content' => $r->old_value ), array( 'ID' => (int) $r->object_id ), array( '%s' ), array( '%d' ) );
			clean_post_cache( (int) $r->object_id );
		} else {
			update_post_meta( (int) $r->object_id, $r->object_field, $r->old_value );
		}
		$log[] = "↩ برگشت: #{$r->object_id} [{$r->object_field}]";
	}
	if ( ! $rows ) { $log[] = 'پشتیبانی برای این بخش وجود ندارد.'; }
	return $log;
}

/* ------------------------------------------------------------------ */
/*  رابط مدیریت                                                         */
/* ------------------------------------------------------------------ */

add_action( 'admin_menu', 'qpof_admin_menu' );
function qpof_admin_menu() {
	add_management_page(
		'QP One-Off Fixes',
		'QP یک‌بارمصرف‌ها',
		'manage_options',
		'qpof',
		'qpof_render_admin'
	);
}

function qpof_sections() {
	$d = qpof_data();
	return array(
		'links'     => array( 'اصلاح لینک‌های شکسته',          $d['stats']['link_fix_hrefs']   . ' لینک در ' . $d['stats']['link_fix_posts'] . ' مقاله' ),
		'unlink'    => array( 'بازکردن لینک به صفحات غیرمنتشر', $d['stats']['draft_unlink_hrefs'] . ' لینک در ' . $d['stats']['draft_unlink_posts'] . ' مقاله' ),
		'meta'      => array( 'تکمیل توضیحات متا',              $d['stats']['meta_fill'] . ' مقاله' ),
		'kw'        => array( 'تکمیل کلیدواژهٔ کانونی',          $d['stats']['kw_fill'] . ' مقاله' ),
		'alt'       => array( 'ALT تصویر شاخص',                 $d['stats']['feat_alt'] . ' تصویر' ),
		'redirects' => array( 'فعال‌سازی ریدایرکت‌ها',           $d['stats']['redirect_301'] . ' قانون ۳۰۱ + ' . $d['stats']['redirect_410'] . ' قانون ۴۱۰' ),
	);
}

function qpof_run_pass( $pass, $apply ) {
	switch ( $pass ) {
		case 'links':     return qpof_pass_links( $apply );
		case 'unlink':    return qpof_pass_unlink( $apply );
		case 'meta':      return qpof_pass_meta( $apply );
		case 'kw':        return qpof_pass_kw( $apply );
		case 'alt':       return qpof_pass_alt( $apply );
		case 'redirects': return qpof_pass_redirects_toggle( $apply );
	}
	return array( 'بخش ناشناخته' );
}

function qpof_render_admin() {
	if ( ! current_user_can( 'manage_options' ) ) { wp_die( 'دسترسی ندارید.' ); }
	qpof_maybe_install();

	$result_lines = array();
	if ( isset( $_POST['qpof_action'], $_POST['qpof_pass'] ) && check_admin_referer( 'qpof_do' ) ) {
		$pass   = sanitize_key( wp_unslash( $_POST['qpof_pass'] ) );
		$action = sanitize_key( wp_unslash( $_POST['qpof_action'] ) );
		if ( $action === 'dry' )            { $result_lines = array_merge( array( '«پیش‌نمایش خشک» — هیچ تغییری ذخیره نشد:' ), qpof_run_pass( $pass, false ) ); }
		elseif ( $action === 'apply' )      { $result_lines = array_merge( array( '«اعمال شد» — نتایج:' ), qpof_run_pass( $pass, true ) ); }
		elseif ( $action === 'restore' )    { $result_lines = array_merge( array( '«بازگردانی» — نتایج:' ), qpof_restore( $pass ) ); }
	}
	?>
	<div class="wrap" dir="rtl" style="font-family:tahoma">
		<h1>QP One-Off Fixes — اصلاح یک‌بارمصرف qpedia.ir</h1>
		<p style="max-width:72ch">هر بخش را جداگانه اجرا کنید: اول <strong>پیش‌نمایش خشک</strong> (فقط گزارش، بدون تغییر)، بعد <strong>اعمال</strong> (با ثبت پشتیبان خودکار در جدول <code><?php echo esc_html( $GLOBALS['wpdb']->prefix . QPOF_TABLE ); ?></code>). هر بخش دکمهٔ <strong>بازگردانی</strong> هم دارد.</p>
		<?php if ( $result_lines ) : ?>
			<div style="background:#fff;border:1px solid #ccd0d4;padding:12px 16px;margin:12px 0;max-height:420px;overflow:auto">
				<?php foreach ( $result_lines as $line ) : ?>
					<div style="border-bottom:1px dashed #eee;padding:4px 0"><?php echo esc_html( $line ); ?></div>
				<?php endforeach; ?>
			</div>
		<?php endif; ?>

		<?php foreach ( qpof_sections() as $key => $meta ) : ?>
			<div style="background:#fff;border:1px solid #ccd0d4;padding:12px 16px;margin:12px 0;display:flex;align-items:center;gap:16px;flex-wrap:wrap">
				<div style="flex:1;min-width:280px">
					<strong><?php echo esc_html( $meta[0] ); ?></strong><br>
					<span style="color:#666"><?php echo esc_html( $meta[1] ); ?></span>
				</div>
				<form method="post" action="">
					<?php wp_nonce_field( 'qpof_do' ); ?>
					<input type="hidden" name="qpof_pass" value="<?php echo esc_attr( $key ); ?>">
					<button class="button" name="qpof_action" value="dry">پیش‌نمایش خشک</button>
					<button class="button button-primary" name="qpof_action" value="apply" onclick="return confirm('اعمال شود؟ پشتیبان خودکار ثبت می‌شود.');">اعمال (با پشتیبان)</button>
					<?php if ( $key !== 'redirects' ) : ?>
						<button class="button" name="qpof_action" value="restore" onclick="return confirm('تغییرات این بخش برگردانده شود؟');">بازگردانی</button>
					<?php endif; ?>
				</form>
			</div>
		<?php endforeach; ?>

		<p style="color:#666;max-width:72ch">نکتهٔ مهم: این افزونه فقط روی محتوای «منتشرشده» کار می‌کند و چیزی را حذف نمی‌کند. دادهٔ اصلاحات از ممیزی واقعی سایت (ریپوی Q-7 / پوشهٔ seo-audit) تولید شده است. بعد از اتمام همهٔ بخش‌ها، افزونه را غیرفعال و حذف کنید (ریدایرکت‌ها با حذف افزونه قطع می‌شوند — اگر می‌خواهید دائمی بمانند، قوانین .htaccess موجود در پوشهٔ redirects ریپو را در هاست کپی کنید).</p>
	</div>
	<?php
}
