<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="wrap qpedia-wrap">
	<h1><?php esc_html_e( 'Technical', 'qpedia-seo-pro' ); ?></h1>
	<?php if ( empty( $has_scan ) ) : ?>
		<div class="qpedia-empty">
			<p><?php esc_html_e( 'Run an initial scan to populate this report.', 'qpedia-seo-pro' ); ?></p>
			<button type="button" class="button button-primary" id="qpedia-start-scan"><?php esc_html_e( 'Initial scan', 'qpedia-seo-pro' ); ?></button>
		</div>
	<?php else : ?>
		<div class="qpedia-grid-2">
			<div class="qpedia-card">
				<h2><?php esc_html_e( 'Sitemaps', 'qpedia-seo-pro' ); ?></h2>
				<pre class="qpedia-pre" dir="ltr"><?php echo esc_html( wp_json_encode( isset( $sitemaps ) ? $sitemaps : array(), JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT ) ); ?></pre>
			</div>
			<div class="qpedia-card">
				<h2><?php esc_html_e( 'robots.txt', 'qpedia-seo-pro' ); ?></h2>
				<pre class="qpedia-pre" dir="ltr"><?php echo esc_html( is_array( $robots ) ? ( isset( $robots['body'] ) ? $robots['body'] : wp_json_encode( $robots, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT ) ) : (string) $robots ); ?></pre>
			</div>
		</div>
		<div class="qpedia-card">
			<h2><?php esc_html_e( 'Meta conflicts (Rank Math / Yoast / Qpedia)', 'qpedia-seo-pro' ); ?></h2>
			<?php if ( empty( $meta_conflicts ) ) : ?>
				<p><?php esc_html_e( 'No conflicts recorded.', 'qpedia-seo-pro' ); ?></p>
			<?php else : ?>
				<table class="widefat striped">
					<tbody>
						<?php foreach ( array_slice( (array) $meta_conflicts, 0, 80 ) as $row ) : ?>
							<tr><td><?php echo esc_html( is_array( $row ) ? wp_json_encode( $row, JSON_UNESCAPED_UNICODE ) : (string) $row ); ?></td></tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			<?php endif; ?>
		</div>
		<?php if ( ! empty( $redirects ) ) : ?>
			<div class="qpedia-card">
				<h2><?php esc_html_e( 'Redirects', 'qpedia-seo-pro' ); ?></h2>
				<pre class="qpedia-pre" dir="ltr"><?php echo esc_html( wp_json_encode( $redirects, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT ) ); ?></pre>
			</div>
		<?php endif; ?>
	<?php endif; ?>
</div>
