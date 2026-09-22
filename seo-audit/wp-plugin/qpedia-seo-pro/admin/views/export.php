<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
$url  = ( is_array( $last_export ) && ! empty( $last_export['url'] ) ) ? $last_export['url'] : '';
$when = ( is_array( $last_export ) && ! empty( $last_export['time'] ) ) ? $last_export['time'] : '';
?>
<div class="wrap qpedia-wrap">
	<h1><?php esc_html_e( 'Export', 'qpedia-seo-pro' ); ?></h1>
	<p><?php esc_html_e( 'Generate a ZIP archive with CSV (UTF-8 BOM), JSON and standalone RTL HTML reports.', 'qpedia-seo-pro' ); ?></p>
	<?php if ( empty( $has_scan ) ) : ?>
		<div class="qpedia-empty">
			<p><?php esc_html_e( 'Scan the site before exporting a report.', 'qpedia-seo-pro' ); ?></p>
			<button type="button" class="button button-primary" id="qpedia-start-scan"><?php esc_html_e( 'Initial scan', 'qpedia-seo-pro' ); ?></button>
		</div>
	<?php else : ?>
		<p>
			<button type="button" class="button button-primary" id="qpedia-export-zip"><?php esc_html_e( 'Generate ZIP report', 'qpedia-seo-pro' ); ?></button>
		</p>
		<p id="qpedia-export-status" class="qpedia-muted"></p>
		<?php if ( $url ) : ?>
			<p>
				<a class="button" href="<?php echo esc_url( $url ); ?>"><?php esc_html_e( 'Download last export', 'qpedia-seo-pro' ); ?></a>
				<?php if ( $when ) : ?>
					<span class="qpedia-muted"><?php echo esc_html( $when ); ?></span>
				<?php endif; ?>
			</p>
		<?php endif; ?>
		<ul class="qpedia-tree">
			<li>summary.json / summary.html</li>
			<li>articles / scientists / taxonomy / images / links / schema / technical / recommendations</li>
		</ul>
	<?php endif; ?>
</div>
