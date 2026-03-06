<?php
/**
 * Header & Footer scripts page template.
 *
 * @package SnipDrop
 * @since   1.0.0
 */

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Template variables passed from class.

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$scripts = is_array( $scripts ) ? $scripts : array();
$header  = isset( $scripts['header'] ) ? $scripts['header'] : '';
$body    = isset( $scripts['body_open'] ) ? $scripts['body_open'] : '';
$footer  = isset( $scripts['footer'] ) ? $scripts['footer'] : '';
?>
<div class="wrap sndp-wrap">
	<h1><?php esc_html_e( 'Header & Footer Scripts', 'snipdrop' ); ?></h1>
	<p class="description sndp-page-desc">
		<?php esc_html_e( 'Add global scripts that will be output on every page of your site. Use this for tracking codes, analytics, custom CSS, or any code that should be present site-wide.', 'snipdrop' ); ?>
	</p>

	<form id="sndp-global-scripts-form" class="sndp-global-scripts-form">
		<div class="sndp-global-scripts-grid">
			<!-- Header Scripts -->
			<div class="sndp-global-script-box">
				<div class="sndp-global-script-header">
					<h2>
						<span class="dashicons dashicons-arrow-up-alt"></span>
						<?php esc_html_e( 'Header Scripts', 'snipdrop' ); ?>
					</h2>
					<code>wp_head</code>
				</div>
				<p class="description">
					<?php esc_html_e( 'Output just before the closing &lt;/head&gt; tag. Ideal for meta tags, stylesheets, and analytics code.', 'snipdrop' ); ?>
				</p>
				<textarea id="sndp-global-header" name="header" class="large-text code sndp-global-editor" rows="10"><?php echo esc_textarea( $header ); ?></textarea>
			</div>

			<!-- Body Open Scripts -->
			<div class="sndp-global-script-box">
				<div class="sndp-global-script-header">
					<h2>
						<span class="dashicons dashicons-editor-code"></span>
						<?php esc_html_e( 'Body Scripts', 'snipdrop' ); ?>
					</h2>
					<code>wp_body_open</code>
				</div>
				<p class="description">
					<?php esc_html_e( 'Output right after the opening &lt;body&gt; tag. Common for Google Tag Manager noscript, chat widgets, and overlay scripts.', 'snipdrop' ); ?>
				</p>
				<textarea id="sndp-global-body" name="body_open" class="large-text code sndp-global-editor" rows="10"><?php echo esc_textarea( $body ); ?></textarea>
			</div>

			<!-- Footer Scripts -->
			<div class="sndp-global-script-box">
				<div class="sndp-global-script-header">
					<h2>
						<span class="dashicons dashicons-arrow-down-alt"></span>
						<?php esc_html_e( 'Footer Scripts', 'snipdrop' ); ?>
					</h2>
					<code>wp_footer</code>
				</div>
				<p class="description">
					<?php esc_html_e( 'Output just before the closing &lt;/body&gt; tag. Best for JavaScript that should load last — chat widgets, tracking pixels, deferred scripts.', 'snipdrop' ); ?>
				</p>
				<textarea id="sndp-global-footer" name="footer" class="large-text code sndp-global-editor" rows="10"><?php echo esc_textarea( $footer ); ?></textarea>
			</div>
		</div>

		<div class="sndp-global-scripts-actions">
			<button type="submit" class="button button-primary button-large" id="sndp-save-global-scripts">
				<?php esc_html_e( 'Save All Scripts', 'snipdrop' ); ?>
			</button>
			<span class="spinner" id="sndp-global-spinner"></span>
			<?php $tm_active = SNDP_Testing_Mode::instance()->is_enabled(); ?>
			<label class="sndp-testing-mode-inline <?php echo $tm_active ? 'is-active' : ''; ?>" title="<?php esc_attr_e( 'Stage changes safely before publishing to visitors', 'snipdrop' ); ?>">
				<input type="checkbox" class="sndp-testing-mode-toggle" value="1" <?php checked( $tm_active ); ?>>
				<span class="dashicons dashicons-visibility"></span>
				<span><?php esc_html_e( 'Testing', 'snipdrop' ); ?></span>
			</label>
		</div>
	</form>

	<?php
	$global_revisions = SNDP_Custom_Snippets::instance()->get_revisions( 'global_scripts' );
	if ( ! empty( $global_revisions ) ) :
		?>
		<div class="sndp-global-script-box" style="margin-top: 24px;">
			<div class="sndp-global-script-header">
				<h2>
					<span class="dashicons dashicons-backup"></span>
					<?php esc_html_e( 'Revision History', 'snipdrop' ); ?>
				</h2>
				<span class="description"><?php esc_html_e( 'Previous versions are saved automatically when you make changes.', 'snipdrop' ); ?></span>
			</div>
			<div class="sndp-metabox-content sndp-revisions-list" style="padding: 12px 16px;">
				<?php
				foreach ( $global_revisions as $idx => $rev ) :
					$rev_user = '';
					if ( ! empty( $rev['user'] ) ) {
						$u = get_userdata( $rev['user'] );
						if ( $u ) {
							$rev_user = $u->display_name;
						}
					}
					$rev_date_display = date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $rev['date'] ) );

					$changed_sections = array();
					if ( ( $rev['header'] ?? '' ) !== $header ) {
						$changed_sections[] = __( 'Header', 'snipdrop' );
					}
					if ( ( $rev['body_open'] ?? '' ) !== $body ) {
						$changed_sections[] = __( 'Body', 'snipdrop' );
					}
					if ( ( $rev['footer'] ?? '' ) !== $footer ) {
						$changed_sections[] = __( 'Footer', 'snipdrop' );
					}
					?>
					<div class="sndp-revision-item">
						<span class="sndp-revision-date">
							<?php echo esc_html( $rev_date_display ); ?>
							<?php if ( $rev_user ) : ?>
								<em><?php echo esc_html( $rev_user ); ?></em>
							<?php endif; ?>
							<?php if ( ! empty( $changed_sections ) ) : ?>
								<span class="sndp-revision-changes">&mdash; <?php echo esc_html( implode( ', ', $changed_sections ) ); ?></span>
							<?php endif; ?>
						</span>
						<span class="sndp-revision-actions">
							<button type="button"
								class="button button-small sndp-restore-global-revision"
								data-revision-index="<?php echo esc_attr( $idx ); ?>">
								<?php esc_html_e( 'Restore', 'snipdrop' ); ?>
							</button>
						</span>
					</div>
				<?php endforeach; ?>
			</div>
		</div>
	<?php endif; ?>
</div>
