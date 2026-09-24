<?php
/**
 * پنل مدیریت — فهرست ۶۰ اصطلاحی که اسلاگ انگلیسی درست ندارند.
 *
 * @package QP_Glossary_Slug_Fixes
 */

defined( 'ABSPATH' ) || exit;

/**
 * افزودن زیرمنو.
 *
 * @return void
 */
function qpgsf_admin_menu() {
	$parent = post_type_exists( 'qp_glossary' ) ? 'edit.php?post_type=qp_glossary' : 'tools.php';

	add_submenu_page(
		$parent,
		'اصلاح اسلاگ اصطلاحات',
		'اصلاح اسلاگ (سئو)',
		'manage_options',
		'qpgsf',
		'qpgsf_render_page'
	);
}
add_action( 'admin_menu', 'qpgsf_admin_menu' );

/**
 * رندر صفحه.
 *
 * @return void
 */
function qpgsf_render_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( 'دسترسی کافی ندارید.' );
	}

	$action  = isset( $_POST['qpgsf_action'] ) ? sanitize_key( $_POST['qpgsf_action'] ) : '';
	$notices = array();
	$report  = null;
	$dry     = false;

	if ( '' !== $action ) {
		if ( ! isset( $_POST['qpgsf_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['qpgsf_nonce'] ) ), 'qpgsf_run' ) ) {
			$notices[] = array( 'error', 'نشانهٔ امنیتی معتبر نبود.' );
		} else {
			$only = array();

			if ( 'selected' === $action && ! empty( $_POST['qpgsf_ids'] ) ) {
				$only = array_filter( array_map( 'absint', explode( ',', (string) wp_unslash( $_POST['qpgsf_ids'] ) ) ) );
			}

			$dry = ( 'check' === $action );

			$result = qpgsf_run_batch( 0, 200, $dry, $only );

			if ( $dry ) {
				$notices[] = array( 'info', 'بررسی انجام شد — هیچ تغییری در سایت ثبت نشد.' );
				$report    = $result['reports'];
			} else {
				qpgsf_clear_caches();
				flush_rewrite_rules( false );

				$fixed = 0;

				foreach ( $result['reports'] as $row ) {
					if ( 'fixed' === $row['status'] ) {
						$fixed++;
					}
				}

				$notices[] = array( 'success', sprintf( '%s مدخل جابه‌جا شد و آدرس قبلی‌شان ۳۰۱ شد. کش سایت تازه شد.', number_format_i18n( $fixed ) ) );
				$report    = $result['reports'];
			}
		}
	} elseif ( isset( $_GET['qpgsf_report'] ) && isset( $_GET['_wpnonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'qpgsf_report' ) ) {
		$stored = get_option( QPGSF_LOG_OPTION, array() );
		$report = is_array( $stored ) ? array_values( $stored ) : array();
	}

	// فهرست زنده.
	$rows = array();

	foreach ( qpgsf_fix_list() as $row ) {
		$status       = qpgsf_row_status( $row );
		$row['state'] = $status;
		$rows[]       = $row;
	}

	$counters = array(
		'ready'   => 0,
		'done'    => 0,
		'changed' => 0,
		'missing' => 0,
	);

	foreach ( $rows as $row ) {
		if ( isset( $counters[ $row['state'][0] ] ) ) {
			$counters[ $row['state'][0] ]++;
		}
	}

	$labels = array(
		'transliteration'              => 'اسلاگ ترانسلیریت بی‌معنا',
		'transliteration+duplicate-risk' => 'ترانسلیریت + خطر محتوای تکراری',
		'wrong-english'                => 'انگلیسیِ اشتباه',
		'broken-english'               => 'انگلیسیِ شکسته',
		'slug-en-mismatch'             => 'ناسازگار با نام انگلیسی',
		'meta-fix-only'                => 'اسلاگ درست، فقط نام انگلیسی',
	);
	?>
	<div class="wrap" dir="rtl">
		<h1>اصلاح اسلاگ اصطلاحات کوانتومی</h1>

		<?php foreach ( $notices as $notice ) : ?>
			<div class="notice notice-<?php echo esc_attr( $notice[0] ); ?>"><p><?php echo esc_html( $notice[1] ); ?></p></div>
		<?php endforeach; ?>

		<?php if ( ! post_type_exists( 'qp_glossary' ) ) : ?>
			<div class="notice notice-warning"><p>نوع محتوای <code>qp_glossary</code> فعال نیست. ابتدا قالب فرزند ۱٫۸٫۱ را فعال کنید.</p></div>
		<?php endif; ?>

		<div style="display:flex;gap:16px;flex-wrap:wrap;margin:18px 0">
			<div style="flex:1;min-width:190px;padding:14px 16px;border:1px solid #c3c4c7;border-radius:8px;background:#fff">
				<div style="color:#646970;font-size:12px">آمادهٔ اصلاح</div>
				<div style="font-size:26px;font-weight:700;color:#2271b1"><?php echo esc_html( number_format_i18n( $counters['ready'] ) ); ?></div>
			</div>
			<div style="flex:1;min-width:190px;padding:14px 16px;border:1px solid #c3c4c7;border-radius:8px;background:#fff">
				<div style="color:#646970;font-size:12px">قبلاً اصلاح‌شده</div>
				<div style="font-size:26px;font-weight:700;color:#008a20"><?php echo esc_html( number_format_i18n( $counters['done'] ) ); ?></div>
			</div>
			<div style="flex:1;min-width:190px;padding:14px 16px;border:1px solid #c3c4c7;border-radius:8px;background:#fff">
				<div style="color:#646970;font-size:12px">اسلاگ متفاوت (دست نزدم)</div>
				<div style="font-size:26px;font-weight:700;color:#996800"><?php echo esc_html( number_format_i18n( $counters['changed'] ) ); ?></div>
			</div>
			<div style="flex:1;min-width:190px;padding:14px 16px;border:1px solid #c3c4c7;border-radius:8px;background:#fff">
				<div style="color:#646970;font-size:12px">پیدا نشد</div>
				<div style="font-size:26px;font-weight:700;color:#b32d2e"><?php echo esc_html( number_format_i18n( $counters['missing'] ) ); ?></div>
			</div>
		</div>

		<form method="post" id="qpgsf-form">
			<?php wp_nonce_field( 'qpgsf_run', 'qpgsf_nonce' ); ?>
			<input type="hidden" name="qpgsf_ids" id="qpgsf-ids" value="" />

			<p>
				<button type="submit" name="qpgsf_action" value="run" class="button button-primary" <?php disabled( 0 === $counters['ready'] ); ?> onclick="return confirm('۵۹ اصطلاح با اسلاگ انگلیسی صحیح جابه‌جا شوند؟ آدرس قبلی هرکدام ۳۰۱ می‌شود.');">
					جابه‌جا کردن <?php echo esc_html( number_format_i18n( $counters['ready'] ) ); ?> مدخل آماده
				</button>
				<button type="submit" name="qpgsf_action" value="check" class="button">فقط بررسی (بدون تغییر)</button>
				<button type="submit" name="qpgsf_action" value="selected" class="button">جابه‌جا کردن ردیف‌های تیک‌خورده</button>
			</p>

			<table class="widefat striped">
				<thead>
					<tr>
						<th style="width:32px"><input type="checkbox" id="qpgsf-all" /></th>
						<th style="width:60px">ID</th>
						<th style="width:190px">عنوان</th>
						<th style="width:210px">اسلاگ فعلی (نادرست)</th>
						<th style="width:220px">اسلاگ انگلیسی درست</th>
						<th>نام انگلیسی</th>
						<th style="width:150px">نوع ایراد</th>
						<th style="width:150px">وضعیت</th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $rows as $row ) : ?>
						<tr>
							<td>
								<input type="checkbox" class="qpgsf-row" value="<?php echo esc_attr( $row['id'] ); ?>" <?php checked( 'ready' === $row['state'][0] ); ?> />
							</td>
							<td><?php echo esc_html( $row['id'] ); ?></td>
							<td><?php echo esc_html( $row['title'] ); ?></td>
							<td><code style="color:#b32d2e"><?php echo esc_html( $row['old'] ); ?></code></td>
							<td><code style="color:#008a20"><?php echo esc_html( $row['new'] ); ?></code></td>
							<td><?php echo esc_html( $row['en'] ); ?></td>
							<td style="font-size:12px;color:#646970"><?php echo esc_html( isset( $labels[ $row['why'] ] ) ? $labels[ $row['why'] ] : $row['why'] ); ?></td>
							<td><strong style="color:<?php echo esc_attr( $row['state'][2] ); ?>"><?php echo esc_html( $row['state'][1] ); ?></strong></td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</form>

		<?php if ( is_array( $report ) ) : ?>
			<h2 class="title">گزارش اجرا</h2>
			<table class="widefat striped">
				<thead>
					<tr><th>عنوان</th><th>اسلاگ قبلی</th><th>اسلاگ تازه</th><th>نتیجه</th></tr>
				</thead>
				<tbody>
					<?php foreach ( $report as $row ) : ?>
						<tr>
							<td><?php echo esc_html( $row['title'] ); ?></td>
							<td><code><?php echo esc_html( $row['old'] ); ?></code></td>
							<td><code><?php echo esc_html( $row['new'] ); ?></code></td>
							<td>
								<?php
								$map = array(
									'fixed'   => array( '#008a20', 'جابه‌جا شد' ),
									'done'    => array( '#008a20', 'از قبل درست' ),
									'ready'   => array( '#2271b1', 'آماده' ),
									'changed' => array( '#996800', 'دست نزدم' ),
									'missing' => array( '#b32d2e', 'پیدا نشد' ),
									'error'   => array( '#b32d2e', 'خطا' ),
								);
								$m = isset( $map[ $row['status'] ] ) ? $map[ $row['status'] ] : array( '#646970', $row['status'] );
								?>
								<span style="color:<?php echo esc_attr( $m[0] ); ?>"><?php echo esc_html( $m[1] ); ?></span>
								<?php if ( ! empty( $row['note'] ) ) : ?>
									<br /><small><?php echo esc_html( $row['note'] ); ?></small>
								<?php endif; ?>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		<?php endif; ?>

		<p style="margin-top:14px">
			<a class="button" href="<?php echo esc_url( home_url( '/glossary/' ) ); ?>" target="_blank" rel="noopener">مشاهدهٔ واژه‌نامه</a>
			<a class="button" href="<?php echo esc_url( wp_nonce_url( qpgsf_page_url( array( 'qpgsf_report' => 1 ) ), 'qpgsf_report' ) ); ?>">نمایش گزارش ذخیره‌شده</a>
		</p>

		<h2 class="title">چطور تست کنم؟</h2>
		<p>پس از اجرا، این آدرس‌ها باید درست باز شوند و آدرس قبلی‌شان ۳۰۱ بدهد:</p>
		<ul style="list-style:disc;padding-inline-start:22px">
			<li><code><?php echo esc_url( home_url( '/glossary/quantization/' ) ); ?></code> ← <code>/glossary/kvantsh/</code></li>
			<li><code><?php echo esc_url( home_url( '/glossary/electron/' ) ); ?></code> ← <code>/glossary/alktrvn/</code></li>
			<li><code><?php echo esc_url( home_url( '/glossary/schrodinger-equation/' ) ); ?></code></li>
			<li><code><?php echo esc_url( home_url( '/glossary/superfluidity/' ) ); ?></code> ← <code>/glossary/shargi-cloud/</code></li>
		</ul>
	</div>

	<script>
	(function(){
		var all = document.getElementById('qpgsf-all');
		var rows = Array.prototype.slice.call(document.querySelectorAll('.qpgsf-row'));
		var form = document.getElementById('qpgsf-form');

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

				var ids = rows.filter(function(b){ return b.checked; }).map(function(b){ return b.value; });

				if (!ids.length) {
					event.preventDefault();
					window.alert('هیچ ردیفی تیک نخورده است.');
					return;
				}

				document.getElementById('qpgsf-ids').value = ids.join(',');
			});
		}
	})();
	</script>
	<?php
}
