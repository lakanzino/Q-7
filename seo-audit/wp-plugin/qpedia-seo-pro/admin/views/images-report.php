<?php
/**
 * Images report.
 *
 * @package QpediaSEO
 *
 * @var bool  $has_scan
 * @var array $images
 * @var bool  $filter_missing_alt
 * @var array $pagination
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$has_scan  = ! empty( $has_scan );
$images    = isset( $images ) && is_array( $images ) ? $images : array();
$missing   = ! empty( $filter_missing_alt );
$pagination = isset( $pagination ) && is_array( $pagination ) ? $pagination : array( 'paged' => 1, 'pages' => 1, 'total' => 0 );
$base      = admin_url( 'admin.php?page=qpedia-seo-images' );
?>
<div class="wrap qpedia-wrap">
	<div class="qpedia-header">
		<h1><?php echo esc_html__( 'Images', 'qpedia-seo-pro' ); ?></h1>
		<?php if ( $has_scan ) : ?>
			<button type="button" class="button qpedia-rescan" id="qpedia-start-scan" data-confirm="rescan">
				<span class="dashicons dashicons-update"></span> <?php echo esc_html__( 'Rescan', 'qpedia-seo-pro' ); ?>
			</button>
		<?php endif; ?>
	</div>

	<?php if ( ! $has_scan ) : ?>
		<div class="qpedia-hero qpedia-empty">
			<span class="dashicons dashicons-format-image"></span>
			<h2><?php echo esc_html__( 'No scan data yet', 'qpedia-seo-pro' ); ?></h2>
			<p><?php echo esc_html__( 'Run a scan to audit alt text, file size, and featured-image usage.', 'qpedia-seo-pro' ); ?></p>
			<button type="button" class="button button-primary" id="qpedia-start-scan"><?php echo esc_html__( 'Initial scan', 'qpedia-seo-pro' ); ?></button>
		</div>
	<?php else : ?>
		<form method="get" class="qpedia-filters" action="<?php echo esc_url( admin_url( 'admin.php' ) ); ?>">
			<input type="hidden" name="page" value="qpedia-seo-images" />
			<label class="qpedia-check">
				<input type="checkbox" name="qpedia_missing_alt" value="1" <?php checked( $missing ); ?> />
				<?php echo esc_html__( 'Missing alt only', 'qpedia-seo-pro' ); ?>
			</label>
			<button type="submit" class="button"><?php echo esc_html__( 'Filter', 'qpedia-seo-pro' ); ?></button>
		</form>

		<p class="qpedia-count">
			<?php
			echo esc_html(
				sprintf(
					__( '%s images', 'qpedia-seo-pro' ),
					number_format_i18n( isset( $pagination['total'] ) ? (int) $pagination['total'] : count( $images ) )
				)
			);
			?>
		</p>

		<table class="wp-list-table widefat striped qpedia-table qpedia-images-table">
			<thead>
				<tr>
					<th><?php echo esc_html__( 'Thumb', 'qpedia-seo-pro' ); ?></th>
					<th><?php echo esc_html__( 'File', 'qpedia-seo-pro' ); ?></th>
					<th><?php echo esc_html__( 'Alt', 'qpedia-seo-pro' ); ?></th>
					<th><?php echo esc_html__( 'Size KB', 'qpedia-seo-pro' ); ?></th>
					<th><?php echo esc_html__( 'Dimensions', 'qpedia-seo-pro' ); ?></th>
					<th><?php echo esc_html__( 'Parent', 'qpedia-seo-pro' ); ?></th>
					<th><?php echo esc_html__( 'Featured', 'qpedia-seo-pro' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php if ( empty( $images ) ) : ?>
					<tr><td colspan="7"><?php echo esc_html__( 'No images match this filter.', 'qpedia-seo-pro' ); ?></td></tr>
				<?php else : ?>
					<?php foreach ( $images as $row ) : ?>
						<?php
						$pid   = isset( $row['parent_id'] ) ? (int) $row['parent_id'] : 0;
						$plink = $pid ? get_edit_post_link( $pid, 'raw' ) : '';
						$w     = isset( $row['width'] ) ? (int) $row['width'] : 0;
						$h     = isset( $row['height'] ) ? (int) $row['height'] : 0;
						$alt   = isset( $row['alt'] ) ? $row['alt'] : '';
						$miss  = ! empty( $row['missing_alt'] ) || '' === trim( (string) $alt );
						?>
						<tr class="<?php echo $miss ? 'qpedia-row-warn' : ''; ?>">
							<td>
								<?php if ( ! empty( $row['thumb'] ) ) : ?>
									<img src="<?php echo esc_url( $row['thumb'] ); ?>" alt="" width="48" height="48" />
								<?php else : ?>
									<span class="dashicons dashicons-format-image"></span>
								<?php endif; ?>
							</td>
							<td>
								<?php if ( ! empty( $row['edit_link'] ) ) : ?>
									<a href="<?php echo esc_url( $row['edit_link'] ); ?>"><?php echo esc_html( isset( $row['file'] ) ? $row['file'] : '' ); ?></a>
								<?php else : ?>
									<?php echo esc_html( isset( $row['file'] ) ? $row['file'] : '' ); ?>
								<?php endif; ?>
							</td>
							<td><?php echo $miss ? '<em>' . esc_html__( 'Missing', 'qpedia-seo-pro' ) . '</em>' : esc_html( $alt ); ?></td>
							<td><?php echo esc_html( number_format_i18n( isset( $row['size_kb'] ) ? (int) $row['size_kb'] : 0 ) ); ?></td>
							<td><?php echo esc_html( $w && $h ? $w . '×' . $h : '—' ); ?></td>
							<td>
								<?php if ( $plink ) : ?>
									<a href="<?php echo esc_url( $plink ); ?>"><?php echo esc_html( isset( $row['parent'] ) ? $row['parent'] : '' ); ?></a>
								<?php else : ?>
									<?php echo esc_html( isset( $row['parent'] ) && $row['parent'] ? $row['parent'] : '—' ); ?>
								<?php endif; ?>
							</td>
							<td>
								<?php if ( ! empty( $row['featured'] ) ) : ?>
									<span class="dashicons dashicons-star-filled" title="<?php echo esc_attr__( 'Featured', 'qpedia-seo-pro' ); ?>"></span>
								<?php else : ?>
									<span class="dashicons dashicons-star-empty"></span>
								<?php endif; ?>
							</td>
						</tr>
					<?php endforeach; ?>
				<?php endif; ?>
			</tbody>
		</table>

		<?php
		$pages = isset( $pagination['pages'] ) ? (int) $pagination['pages'] : 1;
		$paged = isset( $pagination['paged'] ) ? (int) $pagination['paged'] : 1;
		if ( $pages > 1 ) :
			?>
			<div class="tablenav"><div class="tablenav-pages qpedia-pages">
				<?php
				echo wp_kses_post(
					paginate_links(
						array(
							'base'      => add_query_arg(
								array(
									'qpedia_missing_alt' => $missing ? '1' : false,
									'paged'              => '%#%',
								),
								$base
							),
							'format'    => '',
							'current'   => $paged,
							'total'     => $pages,
							'prev_text' => '&raquo;',
							'next_text' => '&laquo;',
						)
					)
				);
				?>
			</div></div>
		<?php endif; ?>
	<?php endif; ?>
</div>
