<?php
/**
 * Dashboard view.
 *
 * @package QpediaSEO
 *
 * @var array $data Dashboard payload from Dashboard::prepare().
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! isset( $data ) || ! is_array( $data ) ) {
	$data = array( 'has_scan' => false );
}

$has   = ! empty( $data['has_scan'] );
$score = isset( $data['site_score'] ) ? (int) $data['site_score'] : 0;
$grade = isset( $data['grade'] ) ? strtoupper( (string) $data['grade'] ) : 'F';
$circ  = isset( $data['ring_circumference'] ) ? (float) $data['ring_circumference'] : ( 2 * M_PI * 52 );
$off   = isset( $data['ring_offset'] ) ? (float) $data['ring_offset'] : $circ;
$counts = isset( $data['counts'] ) && is_array( $data['counts'] ) ? $data['counts'] : array();
$dist   = isset( $data['score_distribution'] ) && is_array( $data['score_distribution'] ) ? $data['score_distribution'] : array();
$tallies = isset( $data['issue_tallies'] ) && is_array( $data['issue_tallies'] ) ? $data['issue_tallies'] : array();
$recent  = isset( $data['recent_issues'] ) && is_array( $data['recent_issues'] ) ? $data['recent_issues'] : array();
$spark   = isset( $data['spark'] ) && is_array( $data['spark'] ) ? $data['spark'] : array();
$cards   = array(
	'articles'   => array( __( 'Articles', 'qpedia-seo-pro' ), 'dashicons-media-document', 'qpedia-seo-articles' ),
	'scientists' => array( __( 'Scientists', 'qpedia-seo-pro' ), 'dashicons-groups', 'qpedia-seo-scientists' ),
	'terms'      => array( __( 'Categories', 'qpedia-seo-pro' ), 'dashicons-category', 'qpedia-seo-taxonomy' ),
	'images'     => array( __( 'Images', 'qpedia-seo-pro' ), 'dashicons-format-image', 'qpedia-seo-images' ),
	'links'      => array( __( 'Internal links', 'qpedia-seo-pro' ), 'dashicons-admin-links', 'qpedia-seo-links' ),
);
?>
<div class="wrap qpedia-wrap">
	<div class="qpedia-header">
		<h1><?php echo esc_html__( 'Qpedia SEO', 'qpedia-seo-pro' ); ?></h1>
		<?php if ( $has ) : ?>
			<button type="button" class="button qpedia-rescan" id="qpedia-start-scan" data-confirm="rescan">
				<span class="dashicons dashicons-update"></span>
				<?php echo esc_html__( 'Rescan', 'qpedia-seo-pro' ); ?>
			</button>
		<?php endif; ?>
	</div>

	<?php if ( ! $has ) : ?>
		<div class="qpedia-hero qpedia-empty">
			<span class="dashicons dashicons-chart-area" aria-hidden="true"></span>
			<h2><?php echo esc_html__( 'Welcome to Qpedia SEO Pro', 'qpedia-seo-pro' ); ?></h2>
			<p><?php echo esc_html__( 'Run the initial scan to collect articles, scientists, categories, images, and internal links, then score the whole site.', 'qpedia-seo-pro' ); ?></p>
			<button type="button" class="button button-primary button-hero" id="qpedia-start-scan">
				<?php echo esc_html__( 'Initial scan', 'qpedia-seo-pro' ); ?>
			</button>
		</div>
	<?php else : ?>
		<div class="qpedia-dash-grid">
			<section class="qpedia-panel qpedia-score-panel">
				<div class="qpedia-score-ring-wrap">
					<svg class="qpedia-score-ring" viewBox="0 0 120 120" role="img" aria-label="<?php echo esc_attr( sprintf( /* translators: score */ __( 'Site score %d', 'qpedia-seo-pro' ), $score ) ); ?>">
						<circle class="qpedia-ring-track" cx="60" cy="60" r="52" fill="none" stroke-width="10"></circle>
						<circle class="qpedia-ring-value qpedia-grade-<?php echo esc_attr( strtolower( $grade ) ); ?>" cx="60" cy="60" r="52" fill="none" stroke-width="10" stroke-linecap="round" transform="rotate(-90 60 60)" stroke-dasharray="<?php echo esc_attr( (string) $circ ); ?>" stroke-dashoffset="<?php echo esc_attr( (string) $off ); ?>"></circle>
						<text x="60" y="56" text-anchor="middle" class="qpedia-ring-score"><?php echo esc_html( (string) $score ); ?></text>
						<text x="60" y="76" text-anchor="middle" class="qpedia-ring-label"><?php echo esc_html__( 'of 100', 'qpedia-seo-pro' ); ?></text>
					</svg>
					<div class="qpedia-grade-block">
						<?php echo \QpediaSEO\Admin::grade_badge( $grade ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
						<p class="qpedia-last-scan">
							<span class="dashicons dashicons-clock"></span>
							<?php
							echo esc_html(
								sprintf(
									/* translators: datetime */
									__( 'Last scan: %s', 'qpedia-seo-pro' ),
									isset( $data['last_scan_display'] ) ? $data['last_scan_display'] : ''
								)
							);
							?>
						</p>
					</div>
				</div>
				<?php if ( ! empty( $spark ) ) : ?>
					<?php
					$w    = 280;
					$h    = 56;
					$max  = max( 100, max( $spark ) );
					$min  = min( 0, min( $spark ) );
					$span = max( 1, $max - $min );
					$n    = count( $spark );
					$pts  = array();
					foreach ( $spark as $i => $v ) {
						$x = ( $n <= 1 ) ? $w / 2 : ( $i / ( $n - 1 ) ) * $w;
						$y = $h - ( ( $v - $min ) / $span ) * ( $h - 8 ) - 4;
						$pts[] = round( $x, 1 ) . ',' . round( $y, 1 );
					}
					?>
					<div class="qpedia-spark">
						<p><?php echo esc_html__( 'Score history', 'qpedia-seo-pro' ); ?></p>
						<svg viewBox="0 0 <?php echo esc_attr( (string) $w ); ?> <?php echo esc_attr( (string) $h ); ?>" class="qpedia-spark-svg" aria-hidden="true">
							<polyline fill="none" stroke="#1f7a72" stroke-width="2.5" points="<?php echo esc_attr( implode( ' ', $pts ) ); ?>"></polyline>
						</svg>
					</div>
				<?php endif; ?>
			</section>

			<section class="qpedia-cards">
				<?php foreach ( $cards as $key => $meta ) : ?>
					<?php
					$n    = isset( $counts[ $key ] ) ? (int) $counts[ $key ] : 0;
					$href = admin_url( 'admin.php?page=' . $meta[2] );
					?>
					<a class="qpedia-card" href="<?php echo esc_url( $href ); ?>">
						<span class="dashicons <?php echo esc_attr( $meta[1] ); ?>"></span>
						<strong><?php echo esc_html( number_format_i18n( $n ) ); ?></strong>
						<span><?php echo esc_html( $meta[0] ); ?></span>
					</a>
				<?php endforeach; ?>
			</section>
		</div>

		<div class="qpedia-dash-grid qpedia-dash-grid-2">
			<section class="qpedia-panel">
				<h2><?php echo esc_html__( 'Grade distribution', 'qpedia-seo-pro' ); ?></h2>
				<?php
				$dtotal = 0;
				foreach ( array( 'A', 'B', 'C', 'D', 'F' ) as $g ) {
					$dtotal += isset( $dist[ $g ] ) ? (int) $dist[ $g ] : 0;
				}
				$dtotal = $dtotal ? $dtotal : 1;
				$r      = 46;
				$c      = 2 * M_PI * $r;
				$acc    = 0;
				$colors = array(
					'A' => '#2f7d4a',
					'B' => '#2c6aa0',
					'C' => '#b8892d',
					'D' => '#c45c26',
					'F' => '#b42318',
				);
				?>
				<div class="qpedia-donut-wrap">
					<svg class="qpedia-donut" viewBox="0 0 120 120" role="img">
						<?php foreach ( $colors as $g => $col ) : ?>
							<?php
							$n     = isset( $dist[ $g ] ) ? (int) $dist[ $g ] : 0;
							$dash  = ( $n / $dtotal ) * $c;
							$gap   = $c - $dash;
							$rot   = -90 + 360 * ( $acc / $dtotal );
							$acc  += $n;
							?>
							<circle cx="60" cy="60" r="<?php echo esc_attr( (string) $r ); ?>" fill="none" stroke="<?php echo esc_attr( $col ); ?>" stroke-width="16" stroke-dasharray="<?php echo esc_attr( $dash . ' ' . $gap ); ?>" transform="rotate(<?php echo esc_attr( (string) $rot ); ?> 60 60)"></circle>
						<?php endforeach; ?>
						<circle cx="60" cy="60" r="30" fill="#f6f3ec"></circle>
					</svg>
					<ul class="qpedia-legend">
						<?php foreach ( $colors as $g => $col ) : ?>
							<li>
								<i style="background:<?php echo esc_attr( $col ); ?>"></i>
								<?php echo esc_html( $g ); ?>
								<strong><?php echo esc_html( number_format_i18n( isset( $dist[ $g ] ) ? (int) $dist[ $g ] : 0 ) ); ?></strong>
							</li>
						<?php endforeach; ?>
					</ul>
				</div>
			</section>

			<section class="qpedia-panel">
				<h2><?php echo esc_html__( 'Issue tallies', 'qpedia-seo-pro' ); ?></h2>
				<ul class="qpedia-tallies">
					<?php
					$labels = array(
						'critical' => __( 'Critical', 'qpedia-seo-pro' ),
						'high'     => __( 'High', 'qpedia-seo-pro' ),
						'medium'   => __( 'Medium', 'qpedia-seo-pro' ),
						'low'      => __( 'Low', 'qpedia-seo-pro' ),
						'total'    => __( 'Total', 'qpedia-seo-pro' ),
					);
					foreach ( $labels as $k => $label ) :
						?>
						<li class="qpedia-sev-<?php echo esc_attr( $k ); ?>">
							<span><?php echo esc_html( $label ); ?></span>
							<strong><?php echo esc_html( number_format_i18n( isset( $tallies[ $k ] ) ? (int) $tallies[ $k ] : 0 ) ); ?></strong>
						</li>
					<?php endforeach; ?>
				</ul>
				<h2><?php echo esc_html__( 'Recent issues', 'qpedia-seo-pro' ); ?></h2>
				<?php if ( empty( $recent ) ) : ?>
					<p><?php echo esc_html__( 'No issues recorded.', 'qpedia-seo-pro' ); ?></p>
				<?php else : ?>
					<ul class="qpedia-issue-list qpedia-recent-issues">
						<?php foreach ( $recent as $issue ) : ?>
							<?php
							$sev = isset( $issue['severity'] ) ? $issue['severity'] : 'medium';
							$msg = isset( $issue['message'] ) ? $issue['message'] : '';
							$ttl = isset( $issue['title'] ) ? $issue['title'] : '';
							$lnk = isset( $issue['edit_link'] ) ? $issue['edit_link'] : '';
							?>
							<li class="qpedia-sev-<?php echo esc_attr( $sev ); ?>">
								<?php if ( $lnk ) : ?>
									<a href="<?php echo esc_url( $lnk ); ?>"><?php echo esc_html( $ttl ? $ttl : $msg ); ?></a>
									<?php if ( $ttl && $msg ) : ?>
										<span><?php echo esc_html( $msg ); ?></span>
									<?php endif; ?>
								<?php else : ?>
									<?php echo esc_html( $msg ? $msg : $ttl ); ?>
								<?php endif; ?>
							</li>
						<?php endforeach; ?>
					</ul>
				<?php endif; ?>
			</section>
		</div>
	<?php endif; ?>
</div>
