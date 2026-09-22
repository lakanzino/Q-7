<?php
/**
 * Internal links report.
 *
 * @package QpediaSEO
 *
 * @var bool  $has_scan
 * @var array $orphans
 * @var array $opportunities
 * @var array $top_incoming
 * @var array $weak_anchors
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$has_scan      = ! empty( $has_scan );
$orphans       = isset( $orphans ) && is_array( $orphans ) ? $orphans : array();
$opportunities = isset( $opportunities ) && is_array( $opportunities ) ? $opportunities : array();
$top_incoming  = isset( $top_incoming ) && is_array( $top_incoming ) ? $top_incoming : array();
$weak_anchors  = isset( $weak_anchors ) && is_array( $weak_anchors ) ? $weak_anchors : array();
?>
<div class="wrap qpedia-wrap">
	<div class="qpedia-header">
		<h1><?php echo esc_html__( 'Internal links', 'qpedia-seo-pro' ); ?></h1>
		<?php if ( $has_scan ) : ?>
			<button type="button" class="button qpedia-rescan" id="qpedia-start-scan" data-confirm="rescan">
				<span class="dashicons dashicons-update"></span> <?php echo esc_html__( 'Rescan', 'qpedia-seo-pro' ); ?>
			</button>
		<?php endif; ?>
	</div>

	<?php if ( ! $has_scan ) : ?>
		<div class="qpedia-hero qpedia-empty">
			<span class="dashicons dashicons-admin-links"></span>
			<h2><?php echo esc_html__( 'No scan data yet', 'qpedia-seo-pro' ); ?></h2>
			<p><?php echo esc_html__( 'Run a scan to map internal links, orphans, and weak anchors.', 'qpedia-seo-pro' ); ?></p>
			<button type="button" class="button button-primary" id="qpedia-start-scan"><?php echo esc_html__( 'Initial scan', 'qpedia-seo-pro' ); ?></button>
		</div>
	<?php else : ?>

		<section class="qpedia-panel">
			<h2><?php echo esc_html__( 'Orphan pages', 'qpedia-seo-pro' ); ?></h2>
			<table class="wp-list-table widefat striped qpedia-table">
				<thead>
					<tr>
						<th><?php echo esc_html__( 'Title', 'qpedia-seo-pro' ); ?></th>
						<th><?php echo esc_html__( 'URL', 'qpedia-seo-pro' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php if ( empty( $orphans ) ) : ?>
						<tr><td colspan="2"><?php echo esc_html__( 'No orphan pages detected.', 'qpedia-seo-pro' ); ?></td></tr>
					<?php else : ?>
						<?php foreach ( $orphans as $row ) : ?>
							<?php
							$edit = isset( $row['edit_link'] ) ? $row['edit_link'] : '';
							$ttl  = isset( $row['title'] ) ? $row['title'] : ( isset( $row['name'] ) ? $row['name'] : '' );
							$url  = isset( $row['permalink'] ) ? $row['permalink'] : '';
							?>
							<tr>
								<td><?php echo $edit ? '<a href="' . esc_url( $edit ) . '">' . esc_html( $ttl ) . '</a>' : esc_html( $ttl ); ?></td>
								<td><?php echo $url ? '<a href="' . esc_url( $url ) . '">' . esc_html( $url ) . '</a>' : '—'; ?></td>
							</tr>
						<?php endforeach; ?>
					<?php endif; ?>
				</tbody>
			</table>
		</section>

		<section class="qpedia-panel">
			<h2><?php echo esc_html__( 'Link opportunities', 'qpedia-seo-pro' ); ?></h2>
			<table class="wp-list-table widefat striped qpedia-table">
				<thead>
					<tr>
						<th><?php echo esc_html__( 'From', 'qpedia-seo-pro' ); ?></th>
						<th><?php echo esc_html__( 'To', 'qpedia-seo-pro' ); ?></th>
						<th><?php echo esc_html__( 'Reason', 'qpedia-seo-pro' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php if ( empty( $opportunities ) ) : ?>
						<tr><td colspan="3"><?php echo esc_html__( 'No opportunities listed.', 'qpedia-seo-pro' ); ?></td></tr>
					<?php else : ?>
						<?php foreach ( $opportunities as $row ) : ?>
							<tr>
								<td><?php echo esc_html( isset( $row['from_title'] ) ? $row['from_title'] : '' ); ?></td>
								<td><?php echo esc_html( isset( $row['to_title'] ) ? $row['to_title'] : '' ); ?></td>
								<td><?php echo esc_html( isset( $row['reason'] ) ? $row['reason'] : '' ); ?></td>
							</tr>
						<?php endforeach; ?>
					<?php endif; ?>
				</tbody>
			</table>
		</section>

		<div class="qpedia-dash-grid qpedia-dash-grid-2">
			<section class="qpedia-panel">
				<h2><?php echo esc_html__( 'Top incoming', 'qpedia-seo-pro' ); ?></h2>
				<table class="wp-list-table widefat striped qpedia-table">
					<thead>
						<tr>
							<th><?php echo esc_html__( 'Title', 'qpedia-seo-pro' ); ?></th>
							<th><?php echo esc_html__( 'Incoming', 'qpedia-seo-pro' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php if ( empty( $top_incoming ) ) : ?>
							<tr><td colspan="2"><?php echo esc_html__( 'No incoming-link data.', 'qpedia-seo-pro' ); ?></td></tr>
						<?php else : ?>
							<?php foreach ( $top_incoming as $row ) : ?>
								<?php $edit = isset( $row['edit_link'] ) ? $row['edit_link'] : ''; ?>
								<tr>
									<td><?php echo $edit ? '<a href="' . esc_url( $edit ) . '">' . esc_html( isset( $row['title'] ) ? $row['title'] : '' ) . '</a>' : esc_html( isset( $row['title'] ) ? $row['title'] : '' ); ?></td>
									<td><?php echo esc_html( number_format_i18n( isset( $row['incoming'] ) ? (int) $row['incoming'] : 0 ) ); ?></td>
								</tr>
							<?php endforeach; ?>
						<?php endif; ?>
					</tbody>
				</table>
			</section>

			<section class="qpedia-panel">
				<h2><?php echo esc_html__( 'Weak anchors', 'qpedia-seo-pro' ); ?></h2>
				<table class="wp-list-table widefat striped qpedia-table">
					<thead>
						<tr>
							<th><?php echo esc_html__( 'From', 'qpedia-seo-pro' ); ?></th>
							<th><?php echo esc_html__( 'Anchor', 'qpedia-seo-pro' ); ?></th>
							<th><?php echo esc_html__( 'URL', 'qpedia-seo-pro' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php if ( empty( $weak_anchors ) ) : ?>
							<tr><td colspan="3"><?php echo esc_html__( 'No weak anchors found.', 'qpedia-seo-pro' ); ?></td></tr>
						<?php else : ?>
							<?php foreach ( $weak_anchors as $row ) : ?>
								<tr>
									<td><?php echo esc_html( isset( $row['from_title'] ) ? $row['from_title'] : '' ); ?></td>
									<td><em><?php echo esc_html( isset( $row['anchor_text'] ) && $row['anchor_text'] ? $row['anchor_text'] : __( '(empty)', 'qpedia-seo-pro' ) ); ?></em></td>
									<td><?php echo esc_html( isset( $row['href'] ) ? $row['href'] : '' ); ?></td>
								</tr>
							<?php endforeach; ?>
						<?php endif; ?>
					</tbody>
				</table>
			</section>
		</div>
	<?php endif; ?>
</div>
