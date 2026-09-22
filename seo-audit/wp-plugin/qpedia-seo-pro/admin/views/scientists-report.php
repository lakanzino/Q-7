<?php
/**
 * Scientists report.
 *
 * @package QpediaSEO
 *
 * @var bool  $has_scan
 * @var array $scientists
 * @var array $completeness_summary
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$has_scan = ! empty( $has_scan );
$scientists = isset( $scientists ) && is_array( $scientists ) ? $scientists : array();
$summary    = isset( $completeness_summary ) && is_array( $completeness_summary ) ? $completeness_summary : array();
?>
<div class="wrap qpedia-wrap">
	<div class="qpedia-header">
		<h1><?php echo esc_html__( 'Scientists', 'qpedia-seo-pro' ); ?></h1>
		<?php if ( $has_scan ) : ?>
			<button type="button" class="button qpedia-rescan" id="qpedia-start-scan" data-confirm="rescan">
				<span class="dashicons dashicons-update"></span> <?php echo esc_html__( 'Rescan', 'qpedia-seo-pro' ); ?>
			</button>
		<?php endif; ?>
	</div>

	<?php if ( ! $has_scan ) : ?>
		<div class="qpedia-hero qpedia-empty">
			<span class="dashicons dashicons-groups"></span>
			<h2><?php echo esc_html__( 'No scan data yet', 'qpedia-seo-pro' ); ?></h2>
			<p><?php echo esc_html__( 'Run a scan to score scientist profiles and check field completeness.', 'qpedia-seo-pro' ); ?></p>
			<button type="button" class="button button-primary" id="qpedia-start-scan"><?php echo esc_html__( 'Initial scan', 'qpedia-seo-pro' ); ?></button>
		</div>
	<?php else : ?>
		<section class="qpedia-panel">
			<h2><?php echo esc_html__( 'Completeness checklist', 'qpedia-seo-pro' ); ?></h2>
			<div class="qpedia-completeness">
				<?php if ( empty( $summary ) ) : ?>
					<p><?php echo esc_html__( 'No completeness data.', 'qpedia-seo-pro' ); ?></p>
				<?php else : ?>
					<?php foreach ( $summary as $item ) : ?>
						<?php
						$label = is_array( $item ) && isset( $item['label'] ) ? $item['label'] : (string) $item;
						$have  = is_array( $item ) && isset( $item['have'] ) ? (int) $item['have'] : 0;
						$total = is_array( $item ) && isset( $item['total'] ) ? (int) $item['total'] : 0;
						$ok    = $total > 0 && $have === $total;
						?>
						<label>
							<input type="checkbox" disabled <?php checked( $ok ); ?> />
							<?php echo esc_html( $label ); ?>
							<span><?php echo esc_html( $have . '/' . $total ); ?></span>
						</label>
					<?php endforeach; ?>
				<?php endif; ?>
			</div>
		</section>

		<table class="wp-list-table widefat striped qpedia-table">
			<thead>
				<tr>
					<th><?php echo esc_html__( 'Name', 'qpedia-seo-pro' ); ?></th>
					<th><?php echo esc_html__( 'English name', 'qpedia-seo-pro' ); ?></th>
					<th><?php echo esc_html__( 'Score', 'qpedia-seo-pro' ); ?></th>
					<th><?php echo esc_html__( 'Completeness %', 'qpedia-seo-pro' ); ?></th>
					<th><?php echo esc_html__( 'Missing fields', 'qpedia-seo-pro' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php if ( empty( $scientists ) ) : ?>
					<tr><td colspan="5"><?php echo esc_html__( 'No scientists found.', 'qpedia-seo-pro' ); ?></td></tr>
				<?php else : ?>
					<?php foreach ( $scientists as $row ) : ?>
						<?php
						$id   = isset( $row['id'] ) ? (int) $row['id'] : 0;
						$edit = isset( $row['edit_link'] ) ? $row['edit_link'] : ( $id ? get_edit_post_link( $id, 'raw' ) : '' );
						$name = isset( $row['name'] ) ? $row['name'] : '';
						$miss = isset( $row['missing_fields'] ) && is_array( $row['missing_fields'] ) ? $row['missing_fields'] : array();
						?>
						<tr>
							<td>
								<?php if ( $edit ) : ?>
									<a href="<?php echo esc_url( $edit ); ?>"><?php echo esc_html( $name ); ?></a>
								<?php else : ?>
									<?php echo esc_html( $name ); ?>
								<?php endif; ?>
							</td>
							<td><?php echo esc_html( isset( $row['en_name'] ) ? $row['en_name'] : '' ); ?></td>
							<td>
								<?php echo esc_html( (string) ( isset( $row['score'] ) ? (int) $row['score'] : 0 ) ); ?>
								<?php echo \QpediaSEO\Admin::grade_badge( isset( $row['grade'] ) ? $row['grade'] : 'F' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
							</td>
							<td><?php echo esc_html( (string) ( isset( $row['completeness'] ) ? (int) $row['completeness'] : 0 ) ); ?>%</td>
							<td><?php echo esc_html( $miss ? implode( '، ', $miss ) : '—' ); ?></td>
						</tr>
					<?php endforeach; ?>
				<?php endif; ?>
			</tbody>
		</table>
	<?php endif; ?>
</div>
