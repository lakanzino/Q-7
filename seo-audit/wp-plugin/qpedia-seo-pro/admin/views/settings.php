<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
$s = isset( $settings ) && is_array( $settings ) ? $settings : array();
?>
<div class="wrap qpedia-wrap">
	<h1><?php esc_html_e( 'Settings', 'qpedia-seo-pro' ); ?></h1>
	<?php if ( ! empty( $updated ) ) : ?>
		<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Settings saved.', 'qpedia-seo-pro' ); ?></p></div>
	<?php endif; ?>
	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" id="qpedia-settings-form">
		<input type="hidden" name="action" value="qpedia_save_settings_form" />
		<?php wp_nonce_field( 'qpedia_seo_pro', 'qpedia_nonce' ); ?>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><?php esc_html_e( 'Inject JSON-LD', 'qpedia-seo-pro' ); ?></th>
				<td><label><input type="checkbox" name="inject_schema" value="1" <?php checked( ! empty( $s['inject_schema'] ) ); ?> /> <?php esc_html_e( 'Output Qpedia schema on the front-end', 'qpedia-seo-pro' ); ?></label></td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Rank Math coexistence', 'qpedia-seo-pro' ); ?></th>
				<td><label><input type="checkbox" name="skip_schema_if_rank_math" value="1" <?php checked( ! empty( $s['skip_schema_if_rank_math'] ) ); ?> /> <?php esc_html_e( 'Skip schema injection when Rank Math is active', 'qpedia-seo-pro' ); ?></label></td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Breadcrumbs', 'qpedia-seo-pro' ); ?></th>
				<td><label><input type="checkbox" name="inject_breadcrumb" value="1" <?php checked( ! empty( $s['inject_breadcrumb'] ) ); ?> /> <?php esc_html_e( 'Render Qpedia breadcrumbs', 'qpedia-seo-pro' ); ?></label></td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Weekly full scan', 'qpedia-seo-pro' ); ?></th>
				<td><label><input type="checkbox" name="weekly_scan" value="1" <?php checked( ! empty( $s['weekly_scan'] ) ); ?> /></label></td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Daily quick check', 'qpedia-seo-pro' ); ?></th>
				<td><label><input type="checkbox" name="daily_quick" value="1" <?php checked( ! empty( $s['daily_quick'] ) ); ?> /></label></td>
			</tr>
			<tr>
				<th scope="row"><label for="qpedia-batch-size"><?php esc_html_e( 'Batch size', 'qpedia-seo-pro' ); ?></label></th>
				<td><input id="qpedia-batch-size" type="number" min="10" max="100" name="batch_size" value="<?php echo esc_attr( isset( $s['batch_size'] ) ? (int) $s['batch_size'] : 50 ); ?>" /></td>
			</tr>
		</table>
		<?php submit_button( __( 'Save settings', 'qpedia-seo-pro' ) ); ?>
	</form>
	<p>
		<button type="button" class="button" id="qpedia-clear-cache"><?php esc_html_e( 'Clear scan cache', 'qpedia-seo-pro' ); ?></button>
	</p>
</div>
