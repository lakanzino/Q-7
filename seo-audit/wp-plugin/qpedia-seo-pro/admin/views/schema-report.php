<?php
/**
 * Schema report.
 *
 * @package QpediaSEO
 *
 * @var bool  $has_scan
 * @var array $types
 * @var array $samples
 * @var array $errors
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$has_scan = ! empty( $has_scan );
$types    = isset( $types ) && is_array( $types ) ? $types : array();
$samples  = isset( $samples ) && is_array( $samples ) ? $samples : array();
$errors   = isset( $errors ) && is_array( $errors ) ? $errors : array();
?>
<div class="wrap qpedia-wrap">
	<div class="qpedia-header">
		<h1><?php echo esc_html__( 'Schema', 'qpedia-seo-pro' ); ?></h1>
		<?php if ( $has_scan ) : ?>
			<button type="button" class="button qpedia-rescan" id="qpedia-start-scan" data-confirm="rescan">
				<span class="dashicons dashicons-update"></span> <?php echo esc_html__( 'Rescan', 'qpedia-seo-pro' ); ?>
			</button>
		<?php endif; ?>
	</div>

	<?php if ( ! $has_scan ) : ?>
		<div class="qpedia-hero qpedia-empty">
			<span class="dashicons dashicons-editor-code"></span>
			<h2><?php echo esc_html__( 'No scan data yet', 'qpedia-seo-pro' ); ?></h2>
			<p><?php echo esc_html__( 'Run a scan to validate JSON-LD types and preview sample graphs.', 'qpedia-seo-pro' ); ?></p>
			<button type="button" class="button button-primary" id="qpedia-start-scan"><?php echo esc_html__( 'Initial scan', 'qpedia-seo-pro' ); ?></button>
		</div>
	<?php else : ?>
		<section class="qpedia-panel">
			<h2><?php echo esc_html__( 'Status per type', 'qpedia-seo-pro' ); ?></h2>
			<table class="wp-list-table widefat striped qpedia-table">
				<thead>
					<tr>
						<th><?php echo esc_html__( 'Type', 'qpedia-seo-pro' ); ?></th>
						<th><?php echo esc_html__( 'Status', 'qpedia-seo-pro' ); ?></th>
						<th><?php echo esc_html__( 'Count', 'qpedia-seo-pro' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php if ( empty( $types ) ) : ?>
						<tr><td colspan="3"><?php echo esc_html__( 'No schema types recorded.', 'qpedia-seo-pro' ); ?></td></tr>
					<?php else : ?>
						<?php foreach ( $types as $type => $row ) : ?>
							<?php
							$label  = is_array( $row ) && isset( $row['label'] ) ? $row['label'] : (string) $type;
							$status = is_array( $row ) && isset( $row['status'] ) ? $row['status'] : 'unknown';
							$count  = is_array( $row ) && isset( $row['count'] ) ? (int) $row['count'] : 0;
							?>
							<tr>
								<td><code><?php echo esc_html( $label ); ?></code></td>
								<td><span class="qpedia-status qpedia-status-<?php echo esc_attr( $status ); ?>"><?php echo esc_html( $status ); ?></span></td>
								<td><?php echo esc_html( number_format_i18n( $count ) ); ?></td>
							</tr>
						<?php endforeach; ?>
					<?php endif; ?>
				</tbody>
			</table>
		</section>

		<section class="qpedia-panel">
			<h2><?php echo esc_html__( 'Sample JSON-LD', 'qpedia-seo-pro' ); ?></h2>
			<?php if ( empty( $samples ) ) : ?>
				<p><?php echo esc_html__( 'No samples generated.', 'qpedia-seo-pro' ); ?></p>
			<?php else : ?>
				<?php foreach ( $samples as $name => $json ) : ?>
					<h3><?php echo esc_html( (string) $name ); ?></h3>
					<pre class="qpedia-json" dir="ltr"><?php
					if ( is_string( $json ) ) {
						echo esc_html( $json );
					} else {
						echo esc_html( wp_json_encode( $json, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) );
					}
					?></pre>
				<?php endforeach; ?>
			<?php endif; ?>
		</section>

		<section class="qpedia-panel">
			<h2><?php echo esc_html__( 'Validation errors', 'qpedia-seo-pro' ); ?></h2>
			<?php if ( empty( $errors ) ) : ?>
				<p class="qpedia-ok"><?php echo esc_html__( 'No validation errors.', 'qpedia-seo-pro' ); ?></p>
			<?php else : ?>
				<ul class="qpedia-issue-list">
					<?php foreach ( $errors as $err ) : ?>
						<li class="qpedia-sev-high"><?php echo esc_html( is_array( $err ) ? \QpediaSEO\Admin::issue_message( $err ) : (string) $err ); ?></li>
					<?php endforeach; ?>
				</ul>
			<?php endif; ?>
		</section>
	<?php endif; ?>
</div>
