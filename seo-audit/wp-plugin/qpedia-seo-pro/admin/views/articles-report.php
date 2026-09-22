<?php
/**
 * Articles report.
 *
 * @package QpediaSEO
 *
 * @var bool  $has_scan
 * @var array $articles
 * @var array $categories
 * @var array $filters
 * @var array $pagination
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$has_scan   = ! empty( $has_scan );
$articles   = isset( $articles ) && is_array( $articles ) ? $articles : array();
$categories = isset( $categories ) && is_array( $categories ) ? $categories : array();
$filters    = isset( $filters ) && is_array( $filters ) ? $filters : array();
$pagination = isset( $pagination ) && is_array( $pagination ) ? $pagination : array( 'paged' => 1, 'pages' => 1, 'total' => 0 );
$grade      = isset( $filters['grade'] ) ? $filters['grade'] : '';
$category   = isset( $filters['category'] ) ? (int) $filters['category'] : 0;
$min_score  = isset( $filters['min_score'] ) ? (int) $filters['min_score'] : 0;
$base       = admin_url( 'admin.php?page=qpedia-seo-articles' );
?>
<div class="wrap qpedia-wrap">
	<div class="qpedia-header">
		<h1><?php echo esc_html__( 'Articles', 'qpedia-seo-pro' ); ?></h1>
		<?php if ( $has_scan ) : ?>
			<button type="button" class="button qpedia-rescan" id="qpedia-start-scan" data-confirm="rescan">
				<span class="dashicons dashicons-update"></span> <?php echo esc_html__( 'Rescan', 'qpedia-seo-pro' ); ?>
			</button>
		<?php endif; ?>
	</div>

	<?php if ( ! $has_scan ) : ?>
		<div class="qpedia-hero qpedia-empty">
			<span class="dashicons dashicons-media-document"></span>
			<h2><?php echo esc_html__( 'No scan data yet', 'qpedia-seo-pro' ); ?></h2>
			<p><?php echo esc_html__( 'Run a scan to score every published quantum_article.', 'qpedia-seo-pro' ); ?></p>
			<button type="button" class="button button-primary" id="qpedia-start-scan"><?php echo esc_html__( 'Initial scan', 'qpedia-seo-pro' ); ?></button>
		</div>
	<?php else : ?>
		<form method="get" class="qpedia-filters" action="<?php echo esc_url( admin_url( 'admin.php' ) ); ?>">
			<input type="hidden" name="page" value="qpedia-seo-articles" />
			<label>
				<span><?php echo esc_html__( 'Grade', 'qpedia-seo-pro' ); ?></span>
				<select name="qpedia_grade">
					<option value=""><?php echo esc_html__( 'All grades', 'qpedia-seo-pro' ); ?></option>
					<?php foreach ( array( 'A', 'B', 'C', 'D', 'F' ) as $g ) : ?>
						<option value="<?php echo esc_attr( $g ); ?>" <?php selected( $grade, $g ); ?>><?php echo esc_html( $g ); ?></option>
					<?php endforeach; ?>
				</select>
			</label>
			<label>
				<span><?php echo esc_html__( 'Category', 'qpedia-seo-pro' ); ?></span>
				<select name="qpedia_category">
					<option value="0"><?php echo esc_html__( 'All categories', 'qpedia-seo-pro' ); ?></option>
					<?php foreach ( $categories as $cid => $cname ) : ?>
						<option value="<?php echo esc_attr( (string) $cid ); ?>" <?php selected( $category, (int) $cid ); ?>><?php echo esc_html( $cname ); ?></option>
					<?php endforeach; ?>
				</select>
			</label>
			<label>
				<span><?php echo esc_html__( 'Min score', 'qpedia-seo-pro' ); ?></span>
				<input type="number" min="0" max="100" name="qpedia_min_score" value="<?php echo esc_attr( (string) $min_score ); ?>" />
			</label>
			<button type="submit" class="button"><?php echo esc_html__( 'Filter', 'qpedia-seo-pro' ); ?></button>
		</form>

		<p class="qpedia-count">
			<?php
			echo esc_html(
				sprintf(
					/* translators: count */
					__( '%s articles', 'qpedia-seo-pro' ),
					number_format_i18n( isset( $pagination['total'] ) ? (int) $pagination['total'] : count( $articles ) )
				)
			);
			?>
		</p>

		<table class="wp-list-table widefat striped qpedia-table">
			<thead>
				<tr>
					<th><?php echo esc_html__( 'Title', 'qpedia-seo-pro' ); ?></th>
					<th><?php echo esc_html__( 'Slug', 'qpedia-seo-pro' ); ?></th>
					<th><?php echo esc_html__( 'Score', 'qpedia-seo-pro' ); ?></th>
					<th><?php echo esc_html__( 'Grade', 'qpedia-seo-pro' ); ?></th>
					<th><?php echo esc_html__( 'Words', 'qpedia-seo-pro' ); ?></th>
					<th><?php echo esc_html__( 'Keyword', 'qpedia-seo-pro' ); ?></th>
					<th><?php echo esc_html__( 'Category', 'qpedia-seo-pro' ); ?></th>
					<th><?php echo esc_html__( 'Modified', 'qpedia-seo-pro' ); ?></th>
					<th><?php echo esc_html__( 'Issues', 'qpedia-seo-pro' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php if ( empty( $articles ) ) : ?>
					<tr><td colspan="9"><?php echo esc_html__( 'No articles match these filters.', 'qpedia-seo-pro' ); ?></td></tr>
				<?php else : ?>
					<?php foreach ( $articles as $row ) : ?>
						<?php
						$id    = isset( $row['id'] ) ? (int) $row['id'] : 0;
						$edit  = isset( $row['edit_link'] ) ? $row['edit_link'] : ( $id ? get_edit_post_link( $id, 'raw' ) : '' );
						$title = isset( $row['title'] ) ? $row['title'] : '';
						$g     = isset( $row['grade'] ) ? $row['grade'] : 'F';
						?>
						<tr>
							<td>
								<?php if ( $edit ) : ?>
									<a href="<?php echo esc_url( $edit ); ?>"><?php echo esc_html( $title ); ?></a>
								<?php else : ?>
									<?php echo esc_html( $title ); ?>
								<?php endif; ?>
							</td>
							<td><code><?php echo esc_html( isset( $row['slug'] ) ? $row['slug'] : '' ); ?></code></td>
							<td><?php echo esc_html( (string) ( isset( $row['score'] ) ? (int) $row['score'] : 0 ) ); ?></td>
							<td><?php echo \QpediaSEO\Admin::grade_badge( $g ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></td>
							<td><?php echo esc_html( number_format_i18n( isset( $row['words'] ) ? (int) $row['words'] : 0 ) ); ?></td>
							<td><?php echo esc_html( isset( $row['keyword'] ) ? $row['keyword'] : '' ); ?></td>
							<td><?php echo esc_html( isset( $row['category'] ) ? $row['category'] : '' ); ?></td>
							<td><?php echo esc_html( isset( $row['modified'] ) ? $row['modified'] : '' ); ?></td>
							<td><?php echo esc_html( number_format_i18n( isset( $row['issues_count'] ) ? (int) $row['issues_count'] : 0 ) ); ?></td>
						</tr>
					<?php endforeach; ?>
				<?php endif; ?>
			</tbody>
		</table>

		<?php
		$pages = isset( $pagination['pages'] ) ? (int) $pagination['pages'] : 1;
		$paged = isset( $pagination['paged'] ) ? (int) $pagination['paged'] : 1;
		if ( $pages > 1 ) :
			$args = array(
				'qpedia_grade'     => $grade,
				'qpedia_category'  => $category,
				'qpedia_min_score' => $min_score,
			);
			?>
			<div class="tablenav"><div class="tablenav-pages qpedia-pages">
				<?php
				echo wp_kses_post(
					paginate_links(
						array(
							'base'      => add_query_arg( array_merge( $args, array( 'paged' => '%#%' ) ), $base ),
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
