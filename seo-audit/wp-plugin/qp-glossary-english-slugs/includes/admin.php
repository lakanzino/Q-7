<?php
/**
 * پنل مدیریت افزونه — پیش‌نمایش، اجرای دسته‌ای (هر بار ۲۰۰ مدخل) و گزارش.
 *
 * @package QP_Glossary_English_Slugs
 */

defined( 'ABSPATH' ) || exit;

const QPGSL_PAGE = 'qpgsl';

/**
 * افزودن صفحهٔ مدیریت.
 *
 * @return void
 */
function qpgsl_admin_menu() {
	$parent = post_type_exists( 'qp_glossary' ) ? 'edit.php?post_type=qp_glossary' : 'tools.php';

	add_submenu_page(
		$parent,
		'اسلاگ انگلیسی اصطلاحات',
		'اسلاگ انگلیسی (سئو)',
		'manage_options',
		QPGSL_PAGE,
		'qpgsl_render_page'
	);
}
add_action( 'admin_menu', 'qpgsl_admin_menu' );

/**
 * نشانی صفحهٔ افزونه.
 *
 * @param array $args پارامترهای اضافه.
 * @return string
 */
function qpgsl_page_url( $args = array() ) {
	$parent = post_type_exists( 'qp_glossary' ) ? 'edit.php?post_type=qp_glossary' : 'tools.php';

	return add_query_arg( array_merge( array( 'page' => QPGSL_PAGE ), $args ), admin_url( $parent ) );
}

/**
 * پردازش فرم‌ها پیش از رندر.
 *
 * @return array پیام‌ها.
 */
function qpgsl_maybe_handle_post() {
	$messages = array();

	if ( empty( $_POST['qpgsl_action'] ) ) {
		return $messages;
	}

	if ( ! isset( $_POST['qpgsl_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['qpgsl_nonce'] ) ), 'qpgsl_run' ) ) {
		$messages[] = array( 'error', 'نشانهٔ امنیتی معتبر نبود. صفحه را دوباره بارگذاری کنید.' );
		return $messages;
	}

	if ( ! current_user_can( 'manage_options' ) ) {
		$messages[] = array( 'error', 'دسترسی کافی ندارید.' );
		return $messages;
	}

	$action = sanitize_key( $_POST['qpgsl_action'] );

	if ( ! empty( $_POST['qpgsl_save_overrides'] ) ) {
		$action = 'save_overrides';
	}

	/* ── ذخیرهٔ تنظیمات ── */
	if ( 'save_options' === $action ) {
		$options = qpgsl_options();

		$options['batch_size']   = isset( $_POST['batch_size'] ) ? max( 10, min( 500, absint( $_POST['batch_size'] ) ) ) : 200;
		$options['set_en_meta']  = empty( $_POST['set_en_meta'] ) ? 0 : 1;
		$options['save_gloss']   = empty( $_POST['save_gloss'] ) ? 0 : 1;
		$options['update_latin'] = empty( $_POST['update_latin'] ) ? 0 : 1;
		$options['keep_old_301'] = empty( $_POST['keep_old_301'] ) ? 0 : 1;

		update_option( QPGSL_OPTION, $options, false );

		$messages[] = array( 'success', 'تنظیمات ذخیره شد.' );
		return $messages;
	}

	/* ── ذخیرهٔ نگاشت‌های دستی (ورودی جدول + کادر متنی) ── */
	if ( 'save_overrides' === $action || 'apply' === $action ) {
		$overrides = qpgsl_overrides();
		$saved     = 0;

		if ( ! empty( $_POST['en'] ) && is_array( $_POST['en'] ) ) {
			$post_slugs = isset( $_POST['slug'] ) ? (array) $_POST['slug'] : array();
			$post_gloss = isset( $_POST['gloss'] ) ? (array) $_POST['gloss'] : array();

			foreach ( wp_unslash( $_POST['en'] ) as $id => $value ) {
				$id    = absint( $id );
				$value = sanitize_text_field( (string) $value );

				if ( ! $id ) {
					continue;
				}

				$slug  = isset( $post_slugs[ $id ] ) ? sanitize_title( wp_unslash( (string) $post_slugs[ $id ] ) ) : '';
				$gloss = isset( $post_gloss[ $id ] ) ? sanitize_text_field( wp_unslash( (string) $post_gloss[ $id ] ) ) : '';

				if ( '' === $value && '' === $slug ) {
					unset( $overrides[ $id ] );
					continue;
				}

				$post = get_post( $id );

				// اگر مقدار جدول همان مقدار فرهنگ است، به‌عنوان نگاشت دستی ثبت نشود.
				if ( $post instanceof WP_Post ) {
					$auto = qpgsl_resolve( $post );

					if ( $auto && $auto['en'] === $value && $auto['slug'] === $slug ) {
						continue;
					}
				}

				if ( '' === $gloss ) {
					$index = qpgsl_dictionary_index();
					$norm  = qpgsl_normalize( $value );

					if ( isset( $index['fa'][ $norm ]['gloss'] ) ) {
						$gloss = (string) $index['fa'][ $norm ]['gloss'];
					}
				}

				$overrides[ $id ] = array(
					'en'    => $value,
					'slug'  => $slug,
					'gloss' => $gloss,
				);
				$saved++;
			}
		}

		if ( ! empty( $_POST['qpgsl_manual'] ) ) {
			$lines = preg_split( '/\R/u', (string) wp_unslash( $_POST['qpgsl_manual'] ) );

			foreach ( (array) $lines as $line ) {
				$line = trim( $line );

				if ( '' === $line ) {
					continue;
				}

				$parts = array_map( 'trim', explode( '|', $line ) );
				$key   = isset( $parts[0] ) ? $parts[0] : '';
				$en    = isset( $parts[1] ) ? $parts[1] : '';
				$slug  = isset( $parts[2] ) ? $parts[2] : '';

				if ( '' === $key || '' === $en ) {
					continue;
				}

				$target = qpgsl_find_post_by_key( $key );

				if ( ! $target instanceof WP_Post ) {
					continue;
				}

				$overrides[ $target->ID ] = array(
					'en'    => sanitize_text_field( $en ),
					'slug'  => sanitize_title( $slug ),
					'gloss' => '',
				);
				$saved++;
			}
		}

		update_option( 'qpgsl_overrides', $overrides, false );

		if ( 'save_overrides' === $action ) {
			$messages[] = array( 'success', sprintf( '%s نگاشت ذخیره شد.', number_format_i18n( $saved ) ) );
			return $messages;
		}
	}

	/* ── اجرای دسته‌ای ── */
	if ( 'apply' === $action ) {
		$scope = isset( $_POST['qpgsl_scope'] ) ? sanitize_key( $_POST['qpgsl_scope'] ) : 'all';
		$start = isset( $_POST['qpgsl_start'] ) ? absint( $_POST['qpgsl_start'] ) : 0;
		$limit = (int) qpgsl_options()['batch_size'];

		$all = qpgsl_get_posts();

		if ( 'selected' === $scope && ! empty( $_POST['qpgsl_ids'] ) ) {
			$ids = array_filter( array_map( 'absint', explode( ',', (string) wp_unslash( $_POST['qpgsl_ids'] ) ) ) );
			$all = array_values(
				array_filter(
					$all,
					function ( $post ) use ( $ids ) {
						return in_array( (int) $post->ID, $ids, true );
					}
				)
			);
		}

		$total = count( $all );
		$slice = array_slice( $all, $start, $limit );

		$run_log = get_option( 'qpgsl_last_run', array() );

		if ( ! is_array( $run_log ) ) {
			$run_log = array();
		}

		if ( 0 === $start ) {
			$run_log = array();
		}

		$options = qpgsl_options();

		foreach ( $slice as $post ) {
			$run_log[] = qpgsl_process_post( $post, $options );
		}

		update_option( 'qpgsl_last_run', array_slice( $run_log, -800 ), false );

		$next = $start + $limit;

		if ( $next < $total ) {
			// ادامهٔ خودکار دستهٔ بعد.
			echo '<div class="notice notice-info"><p><strong>در حال اجرا…</strong> ' . esc_html( number_format_i18n( min( $next, $total ) ) ) . ' از ' . esc_html( number_format_i18n( $total ) ) . ' مدخل پردازش شد. دستهٔ بعدی خودکار اجرا می‌شود.</p></div>';

			$fields = array(
				'qpgsl_action'   => 'apply',
				'qpgsl_nonce'    => wp_create_nonce( 'qpgsl_run' ),
				'qpgsl_scope'    => $scope,
				'qpgsl_start'    => $next,
				'qpgsl_ids'      => isset( $_POST['qpgsl_ids'] ) ? sanitize_text_field( wp_unslash( $_POST['qpgsl_ids'] ) ) : '',
				'qpgsl_continue' => '1',
			);

			echo '<form id="qpgsl-continue" method="post" action="' . esc_url( qpgsl_page_url() ) . '" style="display:none">';

			foreach ( $fields as $name => $value ) {
				echo '<input type="hidden" name="' . esc_attr( $name ) . '" value="' . esc_attr( $value ) . '" />';
			}

			echo '</form>';
			echo '<script>window.setTimeout(function(){document.getElementById("qpgsl-continue").submit();},400);</script>';
		} else {
			qpgsl_clear_caches();
			flush_rewrite_rules( false );

			$messages[] = array( 'success', sprintf( 'اجرا کامل شد — %s مدخل بررسی شد.', number_format_i18n( $total ) ) );
		}

		return $messages;
	}

	return $messages;
}

/**
 * یافتن مدخل از روی شناسه، اسلاگ فعلی یا عنوان.
 *
 * @param string $key کلید.
 * @return WP_Post|null
 */
function qpgsl_find_post_by_key( $key ) {
	$key = trim( (string) $key );

	if ( '' === $key ) {
		return null;
	}

	if ( ctype_digit( $key ) ) {
		$post = get_post( (int) $key );

		if ( $post instanceof WP_Post && in_array( $post->post_type, qpgsl_post_types(), true ) ) {
			return $post;
		}
	}

	foreach ( qpgsl_get_posts() as $post ) {
		if ( 0 === strcasecmp( urldecode( (string) $post->post_name ), $key ) || $post->post_title === $key ) {
			return $post;
		}
	}

	return null;
}

/**
 * برچسب وضعیت.
 *
 * @param WP_Post $post مدخل.
 * @return array
 */
function qpgsl_row_status( $post ) {
	$slug  = urldecode( (string) $post->post_name );
	$match = qpgsl_resolve( $post );

	if ( ! $match ) {
		return array( 'unmatched', 'بدون نگاشت', '#b32d2e' );
	}

	if ( $match['slug'] === $slug ) {
		return array( 'same', 'از قبل درست', '#008a20' );
	}

	if ( qpgsl_is_latin_slug( $slug ) && empty( qpgsl_options()['update_latin'] ) ) {
		return array( 'meta-only', 'فقط ثبت متا', '#996800' );
	}

	return array( 'ready', 'آمادهٔ جایگزینی', '#2271b1' );
}

/**
 * رندر صفحه.
 *
 * @return void
 */
function qpgsl_render_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( 'دسترسی کافی ندارید.' );
	}

	$messages = qpgsl_maybe_handle_post();

	// خروجی CSV.
	if ( isset( $_GET['qpgsl_export'] ) && isset( $_GET['_wpnonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'qpgsl_export' ) ) {
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename=glossary-slugs-' . gmdate( 'Y-m-d' ) . '.csv' );

		$out = fopen( 'php://output', 'w' );
		fwrite( $out, "\xEF\xBB\xBF" );
		fputcsv( $out, array( 'ID', 'عنوان فارسی', 'اسلاگ فعلی', 'نام انگلیسی', 'اسلاگ انگلیسی', 'معنی انگلیسی', 'وضعیت' ) );

		foreach ( qpgsl_get_posts() as $post ) {
			$match = qpgsl_resolve( $post );
			$status = qpgsl_row_status( $post );

			fputcsv(
				$out,
				array(
					$post->ID,
					$post->post_title,
					urldecode( (string) $post->post_name ),
					$match ? $match['en'] : '',
					$match ? $match['slug'] : '',
					$match && ! empty( $match['gloss'] ) ? $match['gloss'] : '',
					$status[1],
				)
			);
		}

		fclose( $out );
		exit;
	}

	$stats   = qpgsl_stats();
	$options = qpgsl_options();

	$per_page = 200;
	$paged    = isset( $_GET['paged'] ) ? max( 1, absint( $_GET['paged'] ) ) : 1;

	$posts = qpgsl_get_posts();
	$total = count( $posts );
	$pages = max( 1, (int) ceil( $total / $per_page ) );
	$slice = array_slice( $posts, ( $paged - 1 ) * $per_page, $per_page );

	$run_log = get_option( 'qpgsl_last_run', array() );
	$continuing = ! empty( $_POST['qpgsl_continue'] );
	?>
	<div class="wrap" dir="rtl">
		<h1>اسلاگ انگلیسی اصطلاحات کوانتومی</h1>

		<?php foreach ( $messages as $message ) : ?>
			<div class="notice notice-<?php echo esc_attr( $message[0] ); ?> is-dismissible"><p><?php echo esc_html( $message[1] ); ?></p></div>
		<?php endforeach; ?>

		<?php if ( ! post_type_exists( 'qp_glossary' ) ) : ?>
			<div class="notice notice-warning"><p>نوع محتوای <code>qp_glossary</code> در سایت ثبت نشده است. ابتدا قالب فرزند ۱٫۸٫۱ را فعال کنید تا بخش «اصطلاحات کوانتومی» ساخته شود.</p></div>
		<?php endif; ?>

		<?php if ( ! $continuing ) : ?>

		<h2 class="title">وضعیت کلی</h2>
		<table class="widefat striped" style="max-width:760px">
			<tbody>
				<tr><td>کل مدخل‌های واژه‌نامه</td><td><strong><?php echo esc_html( number_format_i18n( $stats['total'] ) ); ?></strong></td></tr>
				<tr><td>اسلاگ فارسی یا ترانسلیریت (باید عوض شود)</td><td><strong style="color:#b32d2e"><?php echo esc_html( number_format_i18n( $stats['persian'] ) ); ?></strong></td></tr>
				<tr><td>اسلاگ لاتین فعلی</td><td><?php echo esc_html( number_format_i18n( $stats['latin'] ) ); ?></td></tr>
				<tr><td>نگاشت انگلیسی پیدا شد</td><td><strong style="color:#008a20"><?php echo esc_html( number_format_i18n( $stats['matched'] ) ); ?></strong></td></tr>
				<tr><td>آمادهٔ جایگزینی</td><td><strong><?php echo esc_html( number_format_i18n( $stats['ready'] ) ); ?></strong></td></tr>
				<tr><td>بدون نگاشت (نیازمند ورود دستی)</td><td><?php echo esc_html( number_format_i18n( $stats['unmatched'] ) ); ?></td></tr>
			</tbody>
		</table>

		<h2 class="title">تنظیمات اجرا</h2>
		<form method="post">
			<?php wp_nonce_field( 'qpgsl_run', 'qpgsl_nonce' ); ?>
			<input type="hidden" name="qpgsl_action" value="save_options" />
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row">اندازهٔ هر دسته</th>
					<td><input type="number" name="batch_size" min="10" max="500" value="<?php echo esc_attr( $options['batch_size'] ); ?>" class="small-text" /> مدخل در هر درخواست (پیش‌فرض ۲۰۰)</td>
				</tr>
				<tr>
					<th scope="row">ثبت متا</th>
					<td>
						<label><input type="checkbox" name="set_en_meta" value="1" <?php checked( $options['set_en_meta'], 1 ); ?> /> ثبت «نام انگلیسی» در <code>_qp_term_en</code> (برای قالب و اسکیما)</label><br />
						<label><input type="checkbox" name="save_gloss" value="1" <?php checked( $options['save_gloss'], 1 ); ?> /> ثبت «معنی انگلیسی» در <code>_qp_term_en_gloss</code></label>
					</td>
				</tr>
				<tr>
					<th scope="row">اسلاگ‌های لاتین فعلی</th>
					<td>
						<label><input type="checkbox" name="update_latin" value="1" <?php checked( $options['update_latin'], 1 ); ?> /> اسلاگ لاتین نامناسب هم با اسلاگ استاندارد عوض شود (مثلاً <code>azmaysh-fkry</code> ← <code>thought-experiment</code>)</label>
					</td>
				</tr>
				<tr>
					<th scope="row">آدرس‌های قبلی</th>
					<td>
						<label><input type="checkbox" name="keep_old_301" value="1" <?php checked( $options['keep_old_301'], 1 ); ?> /> آدرس قدیمی حذف و با ۳۰۱ به آدرس تازه منتقل شود</label>
					</td>
				</tr>
			</table>
			<p><button type="submit" class="button">ذخیرهٔ تنظیمات</button></p>
		</form>

		<h2 class="title">اجرای جایگزینی</h2>
		<form method="post" id="qpgsl-apply-form">
			<?php wp_nonce_field( 'qpgsl_run', 'qpgsl_nonce' ); ?>
			<input type="hidden" name="qpgsl_action" value="apply" />
			<input type="hidden" name="qpgsl_scope" value="all" />
			<input type="hidden" name="qpgsl_start" value="0" />

			<p>
				<button type="submit" class="button button-primary" onclick="return confirm('همهٔ اصطلاح‌ها با اسلاگ انگلیسی جایگزین شوند؟ آدرس‌های قبلی ۳۰۱ می‌شوند.');">
					اعمال برای همه (<?php echo esc_html( number_format_i18n( $stats['total'] ) ); ?> مدخل، دسته‌های <?php echo esc_html( number_format_i18n( $options['batch_size'] ) ); ?> تایی)
				</button>
				<button type="submit" class="button" name="qpgsl_save_overrides" value="1">ذخیرهٔ نگاشت‌های دستی</button>
			</p>

			<p>
				<label for="qpgsl_manual"><strong>نگاشت دستی</strong> — هر خط: <code>اسلاگ فعلی یا عنوان فارسی | English Name | english-slug</code></label>
				<textarea id="qpgsl_manual" name="qpgsl_manual" rows="3" class="large-text" dir="rtl" placeholder="نمونه: آزمایش فکری | Thought Experiment | thought-experiment"></textarea>
			</p>

			<h2 class="title">فهرست مدخل‌ها — صفحهٔ <?php echo esc_html( number_format_i18n( $paged ) ); ?> از <?php echo esc_html( number_format_i18n( $pages ) ); ?></h2>

			<table class="widefat striped">
				<thead>
					<tr>
						<th style="width:28px"><input type="checkbox" id="qpgsl-check-all" checked /></th>
						<th style="width:60px">ID</th>
						<th>عنوان فارسی</th>
						<th>اسلاگ فعلی</th>
						<th style="width:190px">نام انگلیسی (نام انگلیسی را می‌توانید ویرایش کنید)</th>
						<th style="width:190px">اسلاگ انگلیسی</th>
						<th>معنی انگلیسی</th>
						<th style="width:120px">وضعیت</th>
					</tr>
				</thead>
				<tbody>
					<?php if ( empty( $slice ) ) : ?>
						<tr><td colspan="8">مدخلی پیدا نشد.</td></tr>
					<?php endif; ?>

					<?php
					foreach ( $slice as $post ) :
						$match  = qpgsl_resolve( $post );
						$status = qpgsl_row_status( $post );
						?>
						<tr>
							<td><input type="checkbox" class="qpgsl-row-check" value="<?php echo esc_attr( $post->ID ); ?>" /></td>
							<td><?php echo esc_html( $post->ID ); ?></td>
							<td>
								<a href="<?php echo esc_url( get_edit_post_link( $post->ID ) ); ?>"><?php echo esc_html( $post->post_title ); ?></a>
								<?php if ( 'publish' !== $post->post_status ) : ?>
									<br /><span style="color:#996800">(<?php echo esc_html( $post->post_status ); ?>)</span>
								<?php endif; ?>
							</td>
							<td><code><?php echo esc_html( urldecode( (string) $post->post_name ) ); ?></code></td>
							<td>
								<input type="text" dir="ltr" style="width:100%" name="en[<?php echo esc_attr( $post->ID ); ?>]" value="<?php echo esc_attr( $match ? $match['en'] : '' ); ?>" placeholder="English Term" />
							</td>
							<td>
								<input type="text" dir="ltr" style="width:100%" name="slug[<?php echo esc_attr( $post->ID ); ?>]" value="<?php echo esc_attr( $match ? $match['slug'] : '' ); ?>" placeholder="english-slug" />
							</td>
							<td style="font-size:12px;color:#555"><?php echo esc_html( $match && ! empty( $match['gloss'] ) ? $match['gloss'] : '—' ); ?></td>
							<td><strong style="color:<?php echo esc_attr( $status[2] ); ?>"><?php echo esc_html( $status[1] ); ?></strong></td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</form>

		<?php if ( $pages > 1 ) : ?>
			<div class="tablenav">
				<div class="tablenav-pages">
					<?php
					echo wp_kses_post(
						paginate_links(
							array(
								'base'      => str_replace( 999999, '%#%', esc_url( qpgsl_page_url( array( 'paged' => 999999 ) ) ) ),
								'format'    => '',
								'current'   => $paged,
								'total'     => $pages,
								'prev_text' => 'قبلی',
								'next_text' => 'بعدی',
							)
						)
					);
					?>
				</div>
			</div>
		<?php endif; ?>

		<p>
			<a class="button" href="<?php echo esc_url( wp_nonce_url( qpgsl_page_url( array( 'qpgsl_export' => 1 ) ), 'qpgsl_export' ) ); ?>">دانلود CSV نگاشت‌ها</a>
			<a class="button" href="<?php echo esc_url( home_url( '/glossary/' ) ); ?>" target="_blank" rel="noopener">مشاهدهٔ واژه‌نامه</a>
		</p>

		<?php else : ?>

			<p>در حال پردازش دسته‌ای مدخل‌ها… این صفحه را نبندید.</p>

		<?php endif; ?>

		<?php if ( ! empty( $run_log ) ) : ?>
			<h2 class="title">گزارش آخرین اجرا</h2>
			<table class="widefat striped">
				<thead>
					<tr><th>مدخل</th><th>نام انگلیسی</th><th>اسلاگ قبلی</th><th>اسلاگ تازه</th><th>نتیجه</th></tr>
				</thead>
				<tbody>
					<?php
					$counters = array(
						'renamed'   => 0,
						'same'      => 0,
						'meta-only' => 0,
						'unmatched' => 0,
						'error'     => 0,
					);

					foreach ( $run_log as $row ) {
						if ( isset( $counters[ $row['status'] ] ) ) {
							$counters[ $row['status'] ]++;
						}
					}
					?>
					<tr>
						<td colspan="5">
							جایگزین‌شده: <strong><?php echo esc_html( number_format_i18n( $counters['renamed'] ) ); ?></strong> ·
							از قبل درست: <?php echo esc_html( number_format_i18n( $counters['same'] ) ); ?> ·
							فقط متا: <?php echo esc_html( number_format_i18n( $counters['meta-only'] ) ); ?> ·
							بدون نگاشت: <strong style="color:#b32d2e"><?php echo esc_html( number_format_i18n( $counters['unmatched'] ) ); ?></strong> ·
							خطا: <?php echo esc_html( number_format_i18n( $counters['error'] ) ); ?>
						</td>
					</tr>
					<?php foreach ( array_slice( array_reverse( $run_log ), 0, 300 ) as $row ) : ?>
						<tr>
							<td><?php echo esc_html( $row['title'] ); ?></td>
							<td dir="ltr"><?php echo esc_html( $row['en'] ); ?></td>
							<td><code><?php echo esc_html( $row['old'] ); ?></code></td>
							<td><code><?php echo esc_html( $row['new'] ); ?></code></td>
							<td>
								<?php
								$labels = array(
									'renamed'   => array( '#008a20', 'جایگزین شد' ),
									'same'      => array( '#008a20', 'از قبل درست بود' ),
									'meta-only' => array( '#996800', 'فقط متا' ),
									'unmatched' => array( '#b32d2e', 'بدون نگاشت' ),
									'error'     => array( '#b32d2e', 'خطا' ),
								);
								$label  = isset( $labels[ $row['status'] ] ) ? $labels[ $row['status'] ] : array( '#555', $row['status'] );
								?>
								<span style="color:<?php echo esc_attr( $label[0] ); ?>"><?php echo esc_html( $label[1] ); ?></span>
								<?php if ( ! empty( $row['note'] ) ) : ?>
									<br /><small><?php echo esc_html( $row['note'] ); ?></small>
								<?php endif; ?>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		<?php endif; ?>
	</div>

	<script>
	(function(){
		var master = document.getElementById('qpgsl-check-all');
		var boxes  = Array.prototype.slice.call(document.querySelectorAll('.qpgsl-row-check'));
		var form   = document.getElementById('qpgsl-apply-form');

		if (master) {
			master.addEventListener('change', function(){
				boxes.forEach(function(box){ box.checked = master.checked; });
			});
		}

		// اگر کاربر بعضی ردیف‌ها را برداشت، فقط همان‌ها اعمال شوند.
		if (form) {
			form.addEventListener('submit', function(event){
				if (event.submitter && event.submitter.name === 'qpgsl_save_overrides') {
					return;
				}

				var checked = boxes.filter(function(box){ return box.checked; });
				if (checked.length && checked.length !== boxes.length) {
					var hidden = document.createElement('input');
					hidden.type = 'hidden';
					hidden.name = 'qpgsl_ids';
					hidden.value = checked.map(function(box){ return box.value; }).join(',');
					form.appendChild(hidden);

					var scope = form.querySelector('input[name="qpgsl_scope"]');
					if (scope) { scope.value = 'selected'; }
				}
			});
		}
	})();
	</script>
	<?php
}
