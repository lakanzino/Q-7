<?php
/**
 * Taxonomy report: 28 categories + tags summary.
 *
 * @package QpediaSEO
 *
 * @var bool  $has_scan
 * @var array $categories
 * @var array $tags_summary
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$has_scan   = ! empty( $has_scan );
$categories = isset( $categories ) && is_array( $categories ) ? $categories : array();
$tags       = isset( $tags_summary ) && is_array( $tags_summary ) ? $tags_summary : array();
$dupes      = isset( $tags['duplicates'] ) && is_array( $tags['duplicates'] ) ? $tags['duplicates'] : array();
?>
<div class="wrap qpedia-wrap">
	<div class="qpedia-header">
		<h1><?php echo esc_html__( 'Categories', 'qpedia-seo-pro' ); ?></h1>
		<?php if ( $has_scan ) : ?>
			<button type="button" class="button qpedia-rescan" id="qpedia-start-scan" data-confirm="rescan">
				<span class="dashicons dashicons-update"></span> <?php echo esc_html__( 'Rescan', 'qpedia-seo-pro' ); ?>
			</button>
		<?php endif; ?>
	</div>

	<?php if ( ! $has_scan ) : ?>
		<div class="qpedia-hero qpedia-empty">
			<span class="dashicons dashicons-category"></span>
			<h2><?php echo esc_html__( 'No scan data yet', 'qpedia-seo-pro' ); ?></h2>
			<p><?php echo esc_html__( 'Run a scan to audit quantum_category archives and post tags.', 'qpedia-seo-pro' ); ?></p>
			<button type="button" class="button button-primary" id="qpedia-start-scan"><?php echo esc_html__( 'Initial scan', 'qpedia-seo-pro' ); ?></button>
		</div>
	<?php else : ?>
		<section class="qpedia-panel">
			<h2><?php echo esc_html__( 'quantum_category (28 topics)', 'qpedia-seo-pro' ); ?></h2>
			<table class="wp-list-table widefat striped qpedia-table">
				<thead>
					<tr>
						<th><?php echo esc_html__( 'Name', 'qpedia-seo-pro' ); ?></th>
						<th><?php echo esc_html__( 'Slug', 'qpedia-seo-pro' ); ?></th>
						<th><?php echo esc_html__( 'Count', 'qpedia-seo-pro' ); ?></th>
						<th><?php echo esc_html__( 'Description length', 'qpedia-seo-pro' ); ?></th>
						<th><?php echo esc_html__( 'Slug script', 'qpedia-seo-pro' ); ?></th>
						<th><?php echo esc_html__( 'Issues', 'qpedia-seo-pro' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php if ( empty( $categories ) ) : ?>
						<tr><td colspan="6"><?php echo esc_html__( 'No categories found.', 'qpedia-seo-pro' ); ?></td></tr>
					<?php else : ?>
						<?php foreach ( $categories as $row ) : ?>
							<?php
							$latin = ! empty( $row['is_latin'] );
							$iss   = isset( $row['issues'] ) && is_array( $row['issues'] ) ? $row['issues'] : array();
							$msgs  = array();
							foreach ( $iss as $issue ) {
								$m = \QpediaSEO\Admin::issue_message( $issue );
								if ( $m ) {
									$msgs[] = $m;
								}
							}
							?>
							<tr>
								<td><?php echo esc_html( isset( $row['name'] ) ? $row['name'] : '' ); ?></td>
								<td><code><?php echo esc_html( isset( $row['slug'] ) ? $row['slug'] : '' ); ?></code></td>
								<td><?php echo esc_html( number_format_i18n( isset( $row['count'] ) ? (int) $row['count'] : 0 ) ); ?></td>
								<td><?php echo esc_html( number_format_i18n( isset( $row['description_length'] ) ? (int) $row['description_length'] : 0 ) ); ?></td>
								<td>
									<span class="qpedia-badge <?php echo $latin ? 'qpedia-badge-latin' : 'qpedia-badge-persian'; ?>">
										<?php echo esc_html( $latin ? __( 'Latin', 'qpedia-seo-pro' ) : __( 'Persian', 'qpedia-seo-pro' ) ); ?>
									</span>
								</td>
								<td><?php echo esc_html( $msgs ? implode( ' · ', $msgs ) : '—' ); ?></td>
							</tr>
						<?php endforeach; ?>
					<?php endif; ?>
				</tbody>
			</table>
		</section>

		<section class="qpedia-panel">
			<h2><?php echo esc_html__( 'Tags summary', 'qpedia-seo-pro' ); ?></h2>
			<div class="qpedia-cards qpedia-cards-3">
				<div class="qpedia-card qpedia-card-static">
					<span class="dashicons dashicons-tag"></span>
					<strong><?php echo esc_html( number_format_i18n( isset( $tags['total'] ) ? (int) $tags['total'] : 0 ) ); ?></strong>
					<span><?php echo esc_html__( 'Total tags', 'qpedia-seo-pro' ); ?></span>
				</div>
				<div class="qpedia-card qpedia-card-static">
					<span class="dashicons dashicons-hidden"></span>
					<strong><?php echo esc_html( number_format_i18n( isset( $tags['unused'] ) ? (int) $tags['unused'] : 0 ) ); ?></strong>
					<span><?php echo esc_html__( 'Unused tags', 'qpedia-seo-pro' ); ?></span>
				</div>
				<div class="qpedia-card qpedia-card-static">
					<span class="dashicons dashicons-image-rotate"></span>
					<strong><?php echo esc_html( number_format_i18n( count( $dupes ) ) ); ?></strong>
					<span><?php echo esc_html__( 'Duplicate groups', 'qpedia-seo-pro' ); ?></span>
				</div>
			</div>
			<?php if ( ! empty( $dupes ) ) : ?>
				<h3><?php echo esc_html__( 'Possible duplicates', 'qpedia-seo-pro' ); ?></h3>
				<ul class="qpedia-dup-list">
					<?php foreach ( array_slice( $dupes, 0, 25 ) as $group ) : ?>
						<?php
						$names = array();
						if ( is_array( $group ) ) {
							foreach ( $group as $t ) {
								$names[] = is_array( $t ) && isset( $t['name'] ) ? $t['name'] : (string) $t;
							}
						}
						?>
						<li><?php echo esc_html( implode( ' / ', $names ) ); ?></li>
					<?php endforeach; ?>
				</ul>
			<?php endif; ?>
		</section>
	<?php endif; ?>
</div>
