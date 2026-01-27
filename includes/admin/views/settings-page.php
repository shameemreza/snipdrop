<?php
/**
 * Settings page template.
 *
 * @package SnipDrop
 * @since   1.0.0
 *
 * @var bool   $safe_mode     Safe mode status.
 * @var string $secret_key    Secret key for safe mode URL.
 * @var string $safe_mode_url Safe mode URL.
 * @var array  $settings      Plugin settings.
 */

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Template variables passed from class.

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="wrap sndp-settings-wrap">
	<h1><?php esc_html_e( 'SnipDrop Settings', 'snipdrop' ); ?></h1>

	<form method="post" action="">
		<?php wp_nonce_field( 'sndp_settings_nonce', 'sndp_nonce' ); ?>

		<table class="form-table" role="presentation">
			<tbody>
				<tr>
					<th scope="row">
						<label for="sndp_safe_mode"><?php esc_html_e( 'Safe Mode', 'snipdrop' ); ?></label>
					</th>
					<td>
						<label>
							<input type="checkbox" name="sndp_safe_mode" id="sndp_safe_mode" value="1" <?php checked( $safe_mode ); ?>>
							<?php esc_html_e( 'Enable safe mode (disables all snippets)', 'snipdrop' ); ?>
						</label>
						<p class="description">
							<?php esc_html_e( 'When enabled, no snippets will be executed. Use this if you are experiencing issues.', 'snipdrop' ); ?>
						</p>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<?php esc_html_e( 'Recovery URL', 'snipdrop' ); ?>
					</th>
					<td>
						<code class="sndp-recovery-url"><?php echo esc_url( $safe_mode_url ); ?></code>
						<p class="description">
							<?php esc_html_e( 'Visit this URL if your site becomes inaccessible due to a snippet error. It will enable safe mode automatically.', 'snipdrop' ); ?>
						</p>
						<p class="description">
							<strong><?php esc_html_e( 'Keep this URL private!', 'snipdrop' ); ?></strong>
						</p>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="sndp_delete_on_uninstall"><?php esc_html_e( 'Uninstall', 'snipdrop' ); ?></label>
					</th>
					<td>
						<label>
							<input type="checkbox" name="sndp_delete_on_uninstall" id="sndp_delete_on_uninstall" value="1" <?php checked( isset( $settings['delete_on_uninstall'] ) && $settings['delete_on_uninstall'] ); ?>>
							<?php esc_html_e( 'Delete all data when plugin is deleted', 'snipdrop' ); ?>
						</label>
						<p class="description">
							<?php esc_html_e( 'When enabled, all plugin data including custom snippets and settings will be permanently deleted when the plugin is uninstalled.', 'snipdrop' ); ?>
						</p>
					</td>
				</tr>
			</tbody>
		</table>

		<h2><?php esc_html_e( 'Library Information', 'snipdrop' ); ?></h2>
		<table class="form-table" role="presentation">
			<tbody>
				<tr>
					<th scope="row"><?php esc_html_e( 'Library Source', 'snipdrop' ); ?></th>
					<td>
						<a href="https://github.com/shameemreza/snipdrop-library" target="_blank" rel="noopener noreferrer">
							github.com/shameemreza/snipdrop-library
						</a>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Library Version', 'snipdrop' ); ?></th>
					<td><?php echo esc_html( SNDP_Library::instance()->get_library_version() ); ?></td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Last Synced', 'snipdrop' ); ?></th>
					<td>
						<?php
						$sndp_last_sync = SNDP_Library::instance()->get_last_sync();
						if ( $sndp_last_sync ) {
							printf(
								/* translators: 1: Date and time, 2: Human readable time difference */
								esc_html__( '%1$s (%2$s ago)', 'snipdrop' ),
								esc_html( gmdate( 'Y-m-d H:i:s', $sndp_last_sync ) ),
								esc_html( human_time_diff( $sndp_last_sync ) )
							);
						} else {
							esc_html_e( 'Never', 'snipdrop' );
						}
						?>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Enabled Snippets', 'snipdrop' ); ?></th>
					<td><?php echo absint( SNDP_Snippets::instance()->get_enabled_count() ); ?></td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Snippets with Errors', 'snipdrop' ); ?></th>
					<td>
						<?php
						$sndp_error_count = SNDP_Snippets::instance()->get_error_count();
						if ( $sndp_error_count > 0 ) {
							echo '<span class="sndp-error-badge">' . absint( $sndp_error_count ) . '</span>';
						} else {
							echo '0';
						}
						?>
					</td>
				</tr>
			</tbody>
		</table>

		<?php submit_button( __( 'Save Settings', 'snipdrop' ), 'primary', 'sndp_save_settings' ); ?>
	</form>

	<?php
	// Get error log.
	$sndp_snippet_errors = SNDP_Snippets::instance()->get_error_snippets();
	if ( ! empty( $sndp_snippet_errors ) ) :
		?>
	<hr>

	<h2><?php esc_html_e( 'Error Log', 'snipdrop' ); ?></h2>
	<p><?php esc_html_e( 'The following snippets have encountered errors and were automatically disabled:', 'snipdrop' ); ?></p>
	<table class="widefat striped sndp-error-log">
		<thead>
			<tr>
				<th><?php esc_html_e( 'Snippet', 'snipdrop' ); ?></th>
				<th><?php esc_html_e( 'Error', 'snipdrop' ); ?></th>
				<th><?php esc_html_e( 'Time', 'snipdrop' ); ?></th>
			</tr>
		</thead>
		<tbody>
			<?php foreach ( $sndp_snippet_errors as $sndp_snippet_id => $sndp_error ) : ?>
				<tr>
					<td><code><?php echo esc_html( $sndp_snippet_id ); ?></code></td>
					<td>
						<?php echo esc_html( isset( $sndp_error['message'] ) ? $sndp_error['message'] : __( 'Unknown error', 'snipdrop' ) ); ?>
						<?php if ( ! empty( $sndp_error['line'] ) ) : ?>
							<?php /* translators: %d: Line number where error occurred */ ?>
							<br><small><?php printf( esc_html__( 'Line: %d', 'snipdrop' ), absint( $sndp_error['line'] ) ); ?></small>
						<?php endif; ?>
					</td>
					<td>
						<?php
						if ( ! empty( $sndp_error['time'] ) ) {
							echo esc_html( human_time_diff( $sndp_error['time'], time() ) . ' ' . __( 'ago', 'snipdrop' ) );
						} else {
							echo '&mdash;';
						}
						?>
					</td>
				</tr>
			<?php endforeach; ?>
		</tbody>
	</table>
	<p>
		<a href="<?php echo esc_url( admin_url( 'admin.php?page=snipdrop' ) ); ?>" class="button">
			<?php esc_html_e( 'View Snippets', 'snipdrop' ); ?>
		</a>
	</p>
	<?php endif; ?>

	<hr>

	<h2><?php esc_html_e( 'Third-Party Services', 'snipdrop' ); ?></h2>
	<p>
		<?php esc_html_e( 'This plugin connects to the following external service:', 'snipdrop' ); ?>
	</p>
	<table class="widefat striped">
		<thead>
			<tr>
				<th><?php esc_html_e( 'Service', 'snipdrop' ); ?></th>
				<th><?php esc_html_e( 'Purpose', 'snipdrop' ); ?></th>
				<th><?php esc_html_e( 'Privacy Policy', 'snipdrop' ); ?></th>
			</tr>
		</thead>
		<tbody>
			<tr>
				<td><strong>GitHub</strong> (raw.githubusercontent.com)</td>
				<td><?php esc_html_e( 'Fetching snippet library data', 'snipdrop' ); ?></td>
				<td>
					<a href="https://docs.github.com/en/site-policy/privacy-policies/github-general-privacy-statement" target="_blank" rel="noopener noreferrer">
						<?php esc_html_e( 'GitHub Privacy Policy', 'snipdrop' ); ?>
					</a>
				</td>
			</tr>
		</tbody>
	</table>
</div>
