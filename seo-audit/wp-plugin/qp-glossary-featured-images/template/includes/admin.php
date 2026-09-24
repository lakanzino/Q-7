<?php
/**
 * پنل مدیریت — پیش‌نمایش تصویر فعلی و تازه، بررسی بدون تغییر، اجرا و گزارش.
 *
 * @package QP_Featured_Images
 */

defined( 'ABSPATH' ) || exit;

/**
 * زیرمنو.
 *
 * @return void
 */
function qpfi_admin_menu() {
	add_submenu_page(
		'tools.php',
		qpfi_part_label(),
		sprintf( 'تصاویر شاخص جدید (بستهٔ %s)', number_format_i18n( QPFI_PART ) ),
		'manage_options',
		'qpfi',
		'qpfi_render_page'
	);
}
add_action( 'admin_menu', 'qpfi_admin_menu' );

/**
 * وضعیت یک ردیف برای نمایش.
 *
 * @param array $row ردیف.
 * @return array
 */
function qpfi_preview_row( $row ) {
	$post    = qpfi_find_article( $row['slug'] );
	$current = $post ? qpfi_current_image( $post->ID ) : array( 'id' => 0, 'basename' => '', 'url' => '', 'file' => '' );

	$others = ( $current['id'] ) ? qpfi_attachment_used_elsewhere( $current['id'], $post ? $post->ID : 0 ) : array();

	if ( ! $post ) {
		$state = array( 'missing', 'مقاله پیدا نشد', '#b32d2e' );
	} elseif ( ! $current['id'] ) {
		$state = array( 'add', 'افزودن (بدون تصویر)', '#2271b1' );
	} elseif ( $current['basename'] === $row['file'] ) {
		$state = array( 'overwrite', 'بازنویسی سرجای فایل', '#008a20' );
	} elseif ( ! empty( $others ) ) {
		$state = array( 'shared', 'جانشینی؛ قبلی حذف نمی‌شود', '#996800' );
	} else {
		$state = array( 'replace', 'جانشینی + حذف قبلی', '#2271b1' );
	}

	return array(
		'post'    => $post,
		'current' => $current,
		'others'  => $others,
		'state'   => $state,
	);
}

/**
 * رندر صفحه.
 *
 * @return void
 */
function qpfi_render_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( 'دسترسی کافی ندارید.' );
	}

	$notices  = array();
	$report   = null;
	$dry      = false;
	$progress = null; // شمارهٔ مقالهٔ بعدی در اجرای مرحله‌ای، اگر نیمه‌کاره مانده باشد.

	if ( ! empty( $_POST['qpfi_action'] ) ) {
		if ( ! isset( $_POST['qpfi_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['qpfi_nonce'] ) ), 'qpfi_run' ) ) {
			$notices[] = array( 'error', 'نشانهٔ امنیتی معتبر نبود.' );
		} else {
			$action = sanitize_key( $_POST['qpfi_action'] );

			if ( 'options' === $action ) {
				update_option(
					QPFI_OPTION,
					array(
						'backup_old' => empty( $_POST['backup_old'] ) ? 0 : 1,
						'set_alt'    => empty( $_POST['set_alt'] ) ? 0 : 1,
					),
					false
				);

				$notices[] = array( 'success', 'تنظیمات ذخیره شد.' );
			} elseif ( 'batch_cancel' === $action ) {
				delete_option( 'qpfi_batch_offset' );

				$notices[] = array( 'warning', 'اجرای مرحله‌ای متوقف شد. پیش‌نمایش از ابتدا نشان داده می‌شود؛ گزارش مرحله‌های انجام‌شده از دکمهٔ «نمایش گزارش ذخیره‌شده» در دسترس است.' );
			} elseif ( 'batch' === $action ) {
				$offset = isset( $_POST['qpfi_offset'] ) ? max( 0, (int) $_POST['qpfi_offset'] ) : 0;
				$batch  = qpfi_run_batch( $offset );
				$fresh  = 0;

				foreach ( $batch['reports'] as $item ) {
					if ( in_array( $item['status'], array( 'overwritten', 'replaced', 'replaced-kept', 'added' ), true ) ) {
						$fresh++;
					}
				}

				$notices[] = array(
					'info',
					sprintf(
						'%s مقاله از %s پردازش شد (%s تصویر ثبت شد · %s مقاله در این مرحله · %s ثانیه).',
						number_format_i18n( $batch['next'] ),
						number_format_i18n( $batch['total'] ),
						number_format_i18n( $fresh ),
						number_format_i18n( $batch['processed'] ),
						number_format_i18n( (int) round( $batch['elapsed'], 1 ) )
					),
				);

				if ( $batch['done'] ) {
					$report = get_option( QPFI_LOG, array() );
					$done   = 0;

					foreach ( (array) $report as $item ) {
						if ( in_array( $item['status'], array( 'overwritten', 'replaced', 'replaced-kept', 'added' ), true ) ) {
							$done++;
						}
					}

					$notices[] = array( 'success', sprintf( 'اجرای مرحله‌ای این بسته تمام شد — %s مقاله تصویر شاخص تازه گرفت.', number_format_i18n( $done ) ) );
				} else {
					$progress = (int) $batch['next'];
				}
			} else {
				$only = array();

				if ( 'selected' === $action && ! empty( $_POST['qpfi_selected'] ) ) {
					$only = array_map( 'sanitize_title', explode( ',', (string) wp_unslash( $_POST['qpfi_selected'] ) ) );
				}

				$dry = ( 'check' === $action );

				if ( $dry ) {
					$notices[] = array( 'info', 'بررسی انجام شد — هیچ تغییری در سایت ثبت نشد.' );
					$report    = qpfi_plan();
				} else {
					$report = qpfi_run( $only );

					$counters = array();

					foreach ( $report as $row ) {
						$counters[ $row['status'] ] = ( isset( $counters[ $row['status'] ] ) ? $counters[ $row['status'] ] : 0 ) + 1;
					}

					$done = 0;

					foreach ( array( 'overwritten', 'replaced', 'replaced-kept', 'added' ) as $key ) {
						$done += isset( $counters[ $key ] ) ? $counters[ $key ] : 0;
					}

					$notices[] = array( 'success', sprintf( 'انجام شد — %s مقاله تصویر شاخص تازه گرفت.', number_format_i18n( $done ) ) );
				}
			}
		}
	} elseif ( isset( $_GET['qpfi_report'] ) && isset( $_GET['_wpnonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'qpfi_report' ) ) {
		$stored = get_option( QPFI_LOG, array() );
		$report = is_array( $stored ) ? $stored : array();
	}

	// اگر اجرای مرحله‌ای نیمه‌کاره مانده باشد، دکمهٔ «ادامه» نشان داده می‌شود.
	if ( null === $progress ) {
		$saved_offset = (int) get_option( 'qpfi_batch_offset', 0 );

		if ( $saved_offset > 0 && $saved_offset < count( qpfi_map() ) ) {
			$progress = $saved_offset;
		}
	}

	$map     = qpfi_map();
	$options = qpfi_options();

	$states = array(
		'overwrite'     => 0,
		'replace'       => 0,
		'shared'        => 0,
		'add'           => 0,
		'missing'       => 0,
	);

	$show_preview = ( null === $progress ); // در میانهٔ اجرای مرحله‌ای پیش‌نمایش ساخته نمی‌شود تا مرحله‌ها سریع باشند.
	$preview      = array();

	if ( $show_preview ) {
		foreach ( $map as $row ) {
			$p = qpfi_preview_row( $row );

			if ( isset( $states[ $p['state'][0] ] ) ) {
				$states[ $p['state'][0] ]++;
			}

			$preview[] = array( 'row' => $row, 'preview' => $p );
		}
	}

	$status_labels = array(
		'overwritten'   => array( '#008a20', 'سرجای قبلی بازنویسی شد' ),
		'replaced'      => array( '#008a20', 'جانشین شد + قبلی حذف شد' ),
		'replaced-kept' => array( '#996800', 'جانشین شد؛ قبلی مشترک بود و ماند' ),
		'added'         => array( '#2271b1', 'تصویر اضافه شد' ),
		'overwrite'     => array( '#008a20', 'بازنویسی سرجای فایل' ),
		'replace'       => array( '#2271b1', 'جانشینی + حذف قبلی' ),
		'add'           => array( '#2271b1', 'افزودن (بدون تصویر)' ),
		'missing'       => array( '#b32d2e', 'مقاله پیدا نشد' ),
		'skip'          => array( '#646970', 'رد شد' ),
		'error'         => array( '#b32d2e', 'خطا' ),
	);
	?>
	<div class="wrap" dir="rtl">
		<h1><?php echo esc_html( qpfi_part_label() ); ?></h1>

		<?php foreach ( $notices as $notice ) : ?>
			<div class="notice notice-<?php echo esc_attr( $notice[0] ); ?>"><p><?php echo esc_html( $notice[1] ); ?></p></div>
		<?php endforeach; ?>

		<?php if ( ! qpfi_other_part_active() ) : ?>
			<div class="notice notice-warning">
				<p><strong>بستهٔ دیگر فعال نیست.</strong> این افزونه «بستهٔ <?php echo esc_html( number_format_i18n( QPFI_PART ) ); ?> از <?php echo esc_html( number_format_i18n( QPFI_PARTS_TOTAL ) ); ?>» است و <strong><?php echo esc_html( number_format_i18n( count( $map ) ) ); ?> مقاله</strong> را پوشش می‌دهد. برای کامل شدن همهٔ <?php echo esc_html( number_format_i18n( QPFI_ARTICLES_TOTAL ) ); ?> مقاله، افزونهٔ بستهٔ دیگر را هم نصب و فعال کنید.</p>
			</div>
		<?php endif; ?>

		<?php if ( null !== $progress ) : ?>
			<?php
			$qpfi_total = count( $map );
			$qpfi_pct   = $qpfi_total ? (int) round( $progress / $qpfi_total * 100 ) : 0;
			?>
			<div class="notice notice-info" style="border-inline-start-color:#2271b1;padding:12px 14px">
				<p style="margin:0 0 8px"><strong>اجرای مرحله‌ای در جریان است — <?php echo esc_html( number_format_i18n( $progress ) ); ?> از <?php echo esc_html( number_format_i18n( $qpfi_total ) ); ?> مقاله (<?php echo esc_html( number_format_i18n( $qpfi_pct ) ); ?>٪)</strong></p>
				<div style="height:12px;background:#e0e0e0;border-radius:6px;overflow:hidden;max-width:620px"><div style="height:12px;width:<?php echo esc_attr( $qpfi_pct ); ?>%;background:#2271b1"></div></div>
				<form method="post" id="qpfi-continue" style="margin:10px 0 0">
					<?php wp_nonce_field( 'qpfi_run', 'qpfi_nonce' ); ?>
					<input type="hidden" name="qpfi_action" value="batch" />
					<input type="hidden" name="qpfi_offset" value="<?php echo esc_attr( $progress ); ?>" />
					<button type="submit" class="button button-primary">ادامه (حدود <?php echo esc_html( number_format_i18n( (int) get_option( 'qpfi_batch_size', QPFI_BATCH_SIZE ) ) ); ?> مقاله)</button>
					<button type="submit" class="button" name="qpfi_action" value="batch_cancel" formnovalidate>توقف</button>
					<span style="color:#646970;margin-inline-start:8px">صفحه خودش چند لحظه دیگر ادامه می‌دهد…</span>
				</form>
			</div>
			<script>
			(function(){
				var form = document.getElementById('qpfi-continue');
				if (!form) { return; }

				var timer = window.setTimeout(function(){ form.submit(); }, 1500);

				// اگر کاربر به این کادر نزدیک شد، ادامهٔ خودکار متوقف می‌شود تا فرصت «توقف» داشته باشد.
				['pointerenter', 'pointerdown', 'touchstart', 'focusin'].forEach(function(evt){
					form.addEventListener(evt, function(){ window.clearTimeout(timer); });
				});
			})();
			</script>
		<?php endif; ?>

		<?php if ( ! post_type_exists( 'quantum_article' ) ) : ?>
			<div class="notice notice-warning"><p>نوع محتوای <code>quantum_article</code> پیدا نشد. قالب فرزند باید فعال باشد.</p></div>
		<?php endif; ?>

		<?php if ( $show_preview ) : ?>
		<div style="display:flex;gap:14px;flex-wrap:wrap;margin:18px 0">
			<?php
			$cards = array(
				array( 'بازنویسی سرجای فایل', $states['overwrite'], '#008a20' ),
				array( 'جانشینی + حذف قبلی', $states['replace'], '#2271b1' ),
				array( 'قبلی مشترک است (حذف نمی‌شود)', $states['shared'], '#996800' ),
				array( 'افزودن به مقالهٔ بدون تصویر', $states['add'], '#2271b1' ),
				array( 'مقاله پیدا نشد', $states['missing'], '#b32d2e' ),
			);

			foreach ( $cards as $card ) :
				?>
				<div style="flex:1;min-width:180px;padding:14px 16px;border:1px solid #c3c4c7;border-radius:8px;background:#fff">
					<div style="color:#646970;font-size:12px"><?php echo esc_html( $card[0] ); ?></div>
					<div style="font-size:26px;font-weight:700;color:<?php echo esc_attr( $card[2] ); ?>"><?php echo esc_html( number_format_i18n( $card[1] ) ); ?></div>
				</div>
			<?php endforeach; ?>
		</div>
		<?php endif; ?>

		<form method="post">
			<?php wp_nonce_field( 'qpfi_run', 'qpfi_nonce' ); ?>
			<input type="hidden" name="qpfi_action" value="options" />
			<p>
				<label style="margin-inline-end:18px"><input type="checkbox" name="backup_old" value="1" <?php checked( $options['backup_old'], 1 ); ?> /> نسخهٔ پشتیبان تصویر قبلی در <code>uploads/qp-fi-backup/</code> نگه داشته شود</label>
				<label><input type="checkbox" name="set_alt" value="1" <?php checked( $options['set_alt'], 1 ); ?> /> متن جانشین (alt) استاندارد + کلمهٔ کلیدی روی تصویر تازه ثبت شود</label>
				<button type="submit" class="button">ذخیرهٔ تنظیمات</button>
			</p>
		</form>

		<?php if ( $show_preview ) : ?>
		<form method="post" id="qpfi-form">
			<?php wp_nonce_field( 'qpfi_run', 'qpfi_nonce' ); ?>
			<input type="hidden" name="qpfi_selected" id="qpfi-selected" value="" />

			<p>
				<button type="submit" name="qpfi_action" value="batch" class="button button-primary" onclick="return confirm('<?php echo esc_js( sprintf( 'اجرای مرحله‌ای: در هر مرحله %s مقاله تا پایان %s مقالهٔ این بسته؛ تصویر قبلی هم جایگزین یا حذف می‌شود. شروع شود؟', number_format_i18n( QPFI_BATCH_SIZE ), number_format_i18n( count( $map ) ) ) ); ?>');">شروع اجرا (مرحله‌ای و ایمن — پیشنهادی)</button>
				<button type="submit" name="qpfi_action" value="check" class="button">فقط بررسی (بدون تغییر)</button>
				<button type="submit" name="qpfi_action" value="selected" class="button">اجرای ردیف‌های تیک‌خورده</button>
				<button type="submit" name="qpfi_action" value="run" class="button" onclick="return confirm('<?php echo esc_js( sprintf( 'همهٔ %s مقالهٔ این بسته در یک درخواست اجرا شود؟ روی هاست کند ممکن است مرورگر قطع کند.', number_format_i18n( count( $map ) ) ) ); ?>');">اجرای همه در یک درخواست</button>
			</p>

			<table class="widefat striped">
				<thead>
					<tr>
						<th style="width:32px"><input type="checkbox" id="qpfi-all" /></th>
						<th style="width:120px">تصویر فعلی</th>
						<th style="width:120px">تصویر تازه</th>
						<th>مقاله</th>
						<th style="width:200px">فایل فعلی ← فایل تازه</th>
						<th style="width:190px">عملیات</th>
						<th style="width:290px">متن جانشین (alt) و کلمهٔ کلیدی</th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $preview as $item ) : ?>
						<?php
						$row   = $item['row'];
						$p     = $item['preview'];
						$state = $p['state'];
						?>
						<tr>
							<td>
								<?php if ( 'missing' !== $state[0] ) : ?>
									<input type="checkbox" class="qpfi-row" value="<?php echo esc_attr( $row['slug'] ); ?>" />
								<?php endif; ?>
							</td>
							<td>
								<?php if ( $p['current']['id'] ) : ?>
									<?php echo wp_kses_post( wp_get_attachment_image( $p['current']['id'], array( 100, 56 ), false, array( 'style' => 'width:100px;height:56px;object-fit:cover;border-radius:4px', 'loading' => 'lazy' ) ) ); ?>
									<div style="font-size:11px;color:#646970;word-break:break-all"><?php echo esc_html( $p['current']['basename'] ); ?></div>
								<?php else : ?>
									<span style="color:#996800;font-size:12px">— ندارد —</span>
								<?php endif; ?>
							</td>
							<td>
								<img src="<?php echo esc_url( QPFI_URL . 'includes/images/' . $row['file'] ); ?>" alt="" loading="lazy" style="width:100px;height:56px;object-fit:cover;border-radius:4px" />
								<div style="font-size:11px;color:#646970;word-break:break-all"><?php echo esc_html( $row['file'] ); ?></div>
							</td>
							<td>
								<?php if ( $p['post'] ) : ?>
									<a href="<?php echo esc_url( get_edit_post_link( $p['post']->ID ) ); ?>"><?php echo esc_html( $p['post']->post_title ); ?></a>
									<div style="font-size:11px;color:#646970"><code><?php echo esc_html( $row['slug'] ); ?></code></div>
								<?php else : ?>
									<code><?php echo esc_html( $row['slug'] ); ?></code>
								<?php endif; ?>
							</td>
							<td style="font-size:12px">
								<code style="word-break:break-all"><?php echo esc_html( '' !== $p['current']['basename'] ? $p['current']['basename'] : '—' ); ?></code>
								←
								<code style="word-break:break-all;color:#008a20"><?php echo esc_html( $row['file'] ); ?></code>
								<?php if ( 'shared' === $state[0] ) : ?>
									<div style="color:#996800;margin-top:4px">مشترک با: <?php echo esc_html( number_format_i18n( count( $p['others'] ) ) ); ?> مقالهٔ دیگر</div>
								<?php endif; ?>
							</td>
							<td><strong style="color:<?php echo esc_attr( $state[2] ); ?>"><?php echo esc_html( $state[1] ); ?></strong></td>
							<td style="font-size:12px;line-height:1.9">
								<?php $alt_text = qpfi_alt_for( $row ); ?>
								<div><?php echo esc_html( $alt_text ); ?></div>
								<div style="color:#646970">
									کلمهٔ کلیدی: <code><?php echo esc_html( $row['kw'] ); ?></code>
									· <?php echo esc_html( number_format_i18n( function_exists( 'mb_strlen' ) ? mb_strlen( $alt_text, 'UTF-8' ) : strlen( $alt_text ) ) ); ?> نویسه
								</div>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</form>
		<?php endif; ?>

		<?php if ( is_array( $report ) ) : ?>
			<h2 class="title">گزارش</h2>
			<table class="widefat striped">
				<thead>
					<tr><th>مقاله</th><th>فایل قبلی</th><th>فایل تازه</th><th>نتیجه</th></tr>
				</thead>
				<tbody>
					<?php foreach ( $report as $row ) : ?>
						<tr>
							<td><?php echo esc_html( $row['title'] ); ?><div style="font-size:11px;color:#646970"><code><?php echo esc_html( $row['slug'] ); ?></code></div></td>
							<td><code style="word-break:break-all"><?php echo esc_html( '' !== $row['old'] ? $row['old'] : '—' ); ?></code></td>
							<td><code style="word-break:break-all"><?php echo esc_html( $row['new'] ); ?></code><div style="font-size:11px;color:#646970;margin-top:3px">alt: <?php echo esc_html( isset( $row['alt'] ) && '' !== $row['alt'] ? $row['alt'] : '' ); ?></div></td>
							<td>
								<?php
								$label = isset( $status_labels[ $row['status'] ] ) ? $status_labels[ $row['status'] ] : array( '#646970', $row['status'] );
								?>
								<span style="color:<?php echo esc_attr( $label[0] ); ?>"><?php echo esc_html( $label[1] ); ?></span>
								<?php if ( ! empty( $row['note'] ) ) : ?>
									<br /><small><?php echo esc_html( $row['note'] ); ?></small>
								<?php endif; ?>
								<?php if ( ! empty( $row['backup'] ) ) : ?>
									<br /><small style="color:#646970">پشتیبان: qp-fi-backup/<?php echo esc_html( $row['backup'] ); ?></small>
								<?php endif; ?>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		<?php endif; ?>

		<p style="margin-top:14px">
			<a class="button" href="<?php echo esc_url( home_url( '/' ) ); ?>" target="_blank" rel="noopener">مشاهدهٔ سایت</a>
			<a class="button" href="<?php echo esc_url( wp_nonce_url( qpfi_page_url( array( 'qpfi_report' => 1 ) ), 'qpfi_report' ) ); ?>">نمایش گزارش ذخیره‌شده</a>
		</p>

		<h2 class="title">راهنمای کوتاه</h2>
		<ul style="list-style:disc;padding-inline-start:22px;line-height:2">
			<li><strong>پیشنهادی — «شروع اجرا (مرحله‌ای)»:</strong> در هر مرحله فقط <?php echo esc_html( number_format_i18n( QPFI_BATCH_SIZE ) ); ?> مقاله پردازش می‌شود و صفحه خودکار تا پایان ادامه می‌دهد؛ به‌این‌ترتیب نه نصب سنگین است و نه مرورگر/سرور قطع می‌کند. اگر وسط کار قطع شد، همین صفحه دکمهٔ «ادامه» را نشان می‌دهد و از همان‌جا ادامه می‌یابد.</li>
			<li>ابتدا «فقط بررسی» را بزنید؛ هیچ تغییری ثبت نمی‌شود و فقط پیش‌نمایش می‌بینید.</li>
			<li>در بیشتر مقاله‌ها نام فایل تازه همان نام فایل فعلی است؛ فایل سرجایش بازنویسی می‌شود و «آدرس تصویر» عوض نمی‌شود (بهترین حالت سئو).</li>
			<li>در بقیه، تصویر تازه با نام اسلاگ ساخته می‌شود و تصویر قبلی از کتابخانه و سرور حذف می‌گردد.</li>
			<li>اگر تصویر قبلی در مقالهٔ دیگری هم به‌کار رفته باشد، حذف نمی‌شود (چون آن مقاله خراب می‌شود).</li>
			<li>پس از اجرا کش سایت را پاک کنید (کش قالب/افزونه/CDN).</li>
			<li><strong>متن جانشین (alt):</strong> برای هر ۵۵ تصویر، متن alt مطابق استاندارد گوگل نوشته شده — توصیفی و طبیعی، زیر ۱۲۵ نویسه، بدون انباشت کلیدواژه و شامل کلمهٔ کلیدی کانونی همان مقاله (استخراج‌شده از Rank Math). ستون آخر جدول پیش‌نمایش، alt هر تصویر را نشان می‌دهد.</li>
			<li><strong>اجرای دوباره بی‌خطر است:</strong> اگر نسخهٔ قبلی را اجرا کرده‌اید، همین نسخه را اجرا کنید تا متن‌های alt ثبت/به‌روز شوند. فایل‌ها دوباره بازنویسی می‌شوند و تصویر اضافه‌ای ساخته نمی‌شود.</li>
			<li><strong>دو بسته:</strong> تصاویر به دو افزونهٔ سبک تقسیم شده‌اند تا نصب سریع باشد. این صفحه «بستهٔ <?php echo esc_html( number_format_i18n( QPFI_PART ) ); ?> از <?php echo esc_html( number_format_i18n( QPFI_PARTS_TOTAL ) ); ?>» است؛ بعد از اجرای این بسته، بستهٔ دیگر را نصب و فعال کنید و اجرای مرحله‌ای را برای آن هم بزنید.</li>
		</ul>
	</div>

	<script>
	(function(){
		var all = document.getElementById('qpfi-all');
		var rows = Array.prototype.slice.call(document.querySelectorAll('.qpfi-row'));
		var form = document.getElementById('qpfi-form');

		if (all) {
			all.addEventListener('change', function(){
				rows.forEach(function(box){ box.checked = all.checked; });
			});
		}

		if (form) {
			form.addEventListener('submit', function(event){
				if (!event.submitter || event.submitter.value !== 'selected') {
					return;
				}

				var picked = rows.filter(function(b){ return b.checked; }).map(function(b){ return b.value; });

				if (!picked.length) {
					event.preventDefault();
					window.alert('هیچ ردیفی تیک نخورده است.');
					return;
				}

				document.getElementById('qpfi-selected').value = picked.join(',');
			});
		}
	})();
	</script>
	<?php
}
