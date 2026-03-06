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

		<?php $tm_enabled = SNDP_Testing_Mode::instance()->is_enabled(); ?>

		<table class="form-table" role="presentation">
			<tbody>
				<tr>
					<th scope="row">
						<label for="sndp-testing-mode-toggle"><?php esc_html_e( 'Testing Mode', 'snipdrop' ); ?></label>
					</th>
					<td>
						<label>
							<input type="checkbox" id="sndp-testing-mode-toggle" value="1" <?php checked( $tm_enabled ); ?>>
							<?php esc_html_e( 'Enable testing mode (stage changes before publishing)', 'snipdrop' ); ?>
						</label>
						<p class="description">
							<?php esc_html_e( 'When enabled, all snippet changes are staged and only visible to admins. Visitors always see the live version. Publish or discard your changes when ready.', 'snipdrop' ); ?>
						</p>
						<?php if ( $tm_enabled ) : ?>
							<?php
							$tm_data = SNDP_Testing_Mode::instance()->get_data();
							$tm_user = ! empty( $tm_data['enabled_by'] ) ? get_userdata( $tm_data['enabled_by'] ) : null;
							$tm_date = ! empty( $tm_data['enabled_at'] ) ? date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $tm_data['enabled_at'] ) ) : '';
							?>
							<p class="description" style="color: #dba617; margin-top: 8px;">
								<strong><?php esc_html_e( 'Active', 'snipdrop' ); ?></strong>
								<?php
								if ( $tm_date ) {
									/* translators: 1: date/time, 2: user name */
									printf( ' &mdash; ' . esc_html__( 'since %1$s by %2$s', 'snipdrop' ), esc_html( $tm_date ), esc_html( $tm_user ? $tm_user->display_name : __( 'Unknown', 'snipdrop' ) ) );
								}
								?>
							</p>
							<p style="margin-top: 8px;">
								<button type="button" class="button button-small" id="sndp-tm-view-changes">
									<?php esc_html_e( 'View Changes', 'snipdrop' ); ?>
								</button>
								<button type="button" class="button button-primary button-small" id="sndp-tm-publish">
									<?php esc_html_e( 'Publish All', 'snipdrop' ); ?>
								</button>
								<button type="button" class="button button-link-delete button-small" id="sndp-tm-discard">
									<?php esc_html_e( 'Discard All', 'snipdrop' ); ?>
								</button>
							</p>
						<?php endif; ?>
					</td>
				</tr>
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
						<label for="sndp_disable_for_admins"><?php esc_html_e( 'Admin Bypass', 'snipdrop' ); ?></label>
					</th>
					<td>
						<label>
							<input type="checkbox" name="sndp_disable_for_admins" id="sndp_disable_for_admins" value="1" <?php checked( ! empty( $settings['disable_for_admins'] ) ); ?>>
							<?php esc_html_e( 'Disable frontend snippets for administrators', 'snipdrop' ); ?>
						</label>
						<p class="description">
							<?php esc_html_e( 'When enabled, logged-in administrators will not see frontend snippet output. Useful during development.', 'snipdrop' ); ?>
						</p>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="sndp_auto_disable_errors"><?php esc_html_e( 'Error Handling', 'snipdrop' ); ?></label>
					</th>
					<td>
						<label>
							<input type="checkbox" name="sndp_auto_disable_errors" id="sndp_auto_disable_errors" value="1" <?php checked( ! isset( $settings['auto_disable_errors'] ) || $settings['auto_disable_errors'] ); ?>>
							<?php esc_html_e( 'Automatically disable snippets that cause PHP errors', 'snipdrop' ); ?>
						</label>
						<p class="description">
							<?php esc_html_e( 'When enabled, any snippet that causes a fatal error will be automatically deactivated to protect your site.', 'snipdrop' ); ?>
						</p>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="sndp_email_notifications"><?php esc_html_e( 'Error Email Alerts', 'snipdrop' ); ?></label>
					</th>
					<td>
						<label>
							<input type="checkbox" name="sndp_email_notifications" id="sndp_email_notifications" value="1" <?php checked( ! isset( $settings['email_notifications'] ) || $settings['email_notifications'] ); ?>>
							<?php esc_html_e( 'Email me when a snippet is auto-disabled due to an error', 'snipdrop' ); ?>
						</label>
						<p class="description">
							<?php esc_html_e( 'Receive an email notification when a snippet causes a fatal error and is automatically disabled. Emails are rate-limited to one per 15 minutes.', 'snipdrop' ); ?>
						</p>
						<br>
						<label for="sndp_notification_email"><?php esc_html_e( 'Notification Email:', 'snipdrop' ); ?></label>
						<input type="email" name="sndp_notification_email" id="sndp_notification_email" class="regular-text" value="<?php echo esc_attr( ! empty( $settings['notification_email'] ) ? $settings['notification_email'] : get_option( 'admin_email' ) ); ?>" placeholder="<?php echo esc_attr( get_option( 'admin_email' ) ); ?>">
						<p class="description">
							<?php esc_html_e( 'Leave empty to use the site admin email.', 'snipdrop' ); ?>
						</p>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="sndp_delete_on_uninstall"><?php esc_html_e( 'Uninstall', 'snipdrop' ); ?></label>
					</th>
					<td>
						<label>
							<input type="checkbox" name="sndp_delete_on_uninstall" id="sndp_delete_on_uninstall" value="1" <?php checked( ! empty( $settings['delete_on_uninstall'] ) ); ?>>
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
