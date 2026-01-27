<?php
/**
 * Add/Edit snippet page template.
 *
 * @package SnipDrop
 * @since   1.1.0
 */

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Template variables passed from class.

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$is_editing  = ! empty( $snippet );
$page_title  = $is_editing ? __( 'Edit Snippet', 'snipdrop' ) : __( 'Add New Snippet', 'snipdrop' );
$button_text = $is_editing ? __( 'Update Snippet', 'snipdrop' ) : __( 'Save Snippet', 'snipdrop' );

// Defaults.
$defaults = array(
	'id'          => '',
	'title'       => '',
	'description' => '',
	'code'        => '',
	'code_type'   => 'php',
	'status'      => 'inactive',
	'hook'        => 'init',
	'priority'    => 10,
	'location'    => 'everywhere',
	'user_cond'   => 'all',
	'post_types'  => array(),
	'page_ids'    => '',
);

$snippet = wp_parse_args( $snippet ? $snippet : array(), $defaults );

// Get post types for dropdown.
$post_types = get_post_types( array( 'public' => true ), 'objects' );
unset( $post_types['attachment'] );
?>
<div class="wrap sndp-wrap">
	<h1 class="wp-heading-inline"><?php echo esc_html( $page_title ); ?></h1>
	<a href="<?php echo esc_url( admin_url( 'admin.php?page=snipdrop-custom' ) ); ?>" class="page-title-action">
		<?php esc_html_e( 'Back to My Snippets', 'snipdrop' ); ?>
	</a>
	<hr class="wp-header-end">

	<form id="sndp-snippet-form" class="sndp-snippet-form">
		<input type="hidden" name="id" id="sndp-snippet-id" value="<?php echo esc_attr( $snippet['id'] ); ?>">

		<div class="sndp-form-columns">
			<div class="sndp-form-main">
				<!-- Title -->
				<div class="sndp-form-field">
					<label for="sndp-snippet-title"><?php esc_html_e( 'Title', 'snipdrop' ); ?></label>
					<input type="text"
						id="sndp-snippet-title"
						name="title"
						class="large-text"
						value="<?php echo esc_attr( $snippet['title'] ); ?>"
						placeholder="<?php esc_attr_e( 'Enter snippet title', 'snipdrop' ); ?>"
						required>
				</div>

				<!-- Description -->
				<div class="sndp-form-field">
					<label for="sndp-snippet-description"><?php esc_html_e( 'Description', 'snipdrop' ); ?></label>
					<textarea id="sndp-snippet-description"
						name="description"
						class="large-text"
						rows="2"
						placeholder="<?php esc_attr_e( 'Optional description of what this snippet does', 'snipdrop' ); ?>"><?php echo esc_textarea( $snippet['description'] ); ?></textarea>
				</div>

				<!-- Code Editor -->
				<div class="sndp-form-field sndp-code-field">
					<label for="sndp-snippet-code"><?php esc_html_e( 'Code', 'snipdrop' ); ?></label>
					<div class="sndp-code-type-selector">
						<label>
							<input type="radio" name="code_type" value="php" <?php checked( $snippet['code_type'], 'php' ); ?>>
							<?php esc_html_e( 'PHP', 'snipdrop' ); ?>
						</label>
						<label>
							<input type="radio" name="code_type" value="js" <?php checked( $snippet['code_type'], 'js' ); ?>>
							<?php esc_html_e( 'JavaScript', 'snipdrop' ); ?>
						</label>
						<label>
							<input type="radio" name="code_type" value="css" <?php checked( $snippet['code_type'], 'css' ); ?>>
							<?php esc_html_e( 'CSS', 'snipdrop' ); ?>
						</label>
						<label>
							<input type="radio" name="code_type" value="html" <?php checked( $snippet['code_type'], 'html' ); ?>>
							<?php esc_html_e( 'HTML', 'snipdrop' ); ?>
						</label>
					</div>
					<textarea id="sndp-snippet-code"
						name="code"
						class="large-text code"
						rows="15"><?php echo esc_textarea( $snippet['code'] ); ?></textarea>
					<p class="description">
						<?php esc_html_e( 'Do not include opening PHP tags for PHP snippets.', 'snipdrop' ); ?>
					</p>
				</div>
			</div>

			<div class="sndp-form-sidebar">
				<!-- Actions (Primary) -->
				<div class="sndp-metabox sndp-metabox-actions">
					<button type="submit" class="button button-primary button-large sndp-save-snippet">
						<?php echo esc_html( $button_text ); ?>
					</button>
					<?php if ( $is_editing ) : ?>
						<button type="button" class="button sndp-save-activate-snippet">
							<?php esc_html_e( 'Save & Activate', 'snipdrop' ); ?>
						</button>
					<?php endif; ?>
				</div>

				<!-- Status -->
				<div class="sndp-metabox">
					<h3><?php esc_html_e( 'Status', 'snipdrop' ); ?></h3>
					<div class="sndp-metabox-content">
						<label class="sndp-toggle-label">
							<input type="checkbox"
								name="status"
								value="active"
								<?php checked( $snippet['status'], 'active' ); ?>>
							<span><?php esc_html_e( 'Active', 'snipdrop' ); ?></span>
						</label>
						<p class="description">
							<?php esc_html_e( 'Enable to run this snippet on your site.', 'snipdrop' ); ?>
						</p>
						<?php if ( $is_editing ) : ?>
							<div class="sndp-snippet-meta">
								<?php if ( ! empty( $snippet['created_at'] ) ) : ?>
									<p>
										<?php
										$created_by = '';
										if ( ! empty( $snippet['created_by'] ) ) {
											$user = get_userdata( $snippet['created_by'] );
											if ( $user ) {
												$created_by = $user->display_name;
											}
										}
										if ( $created_by ) {
											printf(
												/* translators: 1: user name, 2: date */
												esc_html__( 'Created by %1$s on %2$s', 'snipdrop' ),
												'<strong>' . esc_html( $created_by ) . '</strong>',
												esc_html( date_i18n( get_option( 'date_format' ), strtotime( $snippet['created_at'] ) ) )
											);
										} else {
											printf(
												/* translators: %s: date */
												esc_html__( 'Created on %s', 'snipdrop' ),
												esc_html( date_i18n( get_option( 'date_format' ), strtotime( $snippet['created_at'] ) ) )
											);
										}
										?>
									</p>
								<?php endif; ?>
								<?php if ( ! empty( $snippet['updated_at'] ) && $snippet['updated_at'] !== $snippet['created_at'] ) : ?>
									<p>
										<?php
										$modified_by = '';
										if ( ! empty( $snippet['updated_by'] ) ) {
											$user = get_userdata( $snippet['updated_by'] );
											if ( $user ) {
												$modified_by = $user->display_name;
											}
										}
										if ( $modified_by ) {
											printf(
												/* translators: 1: user name, 2: date */
												esc_html__( 'Modified by %1$s on %2$s', 'snipdrop' ),
												'<strong>' . esc_html( $modified_by ) . '</strong>',
												esc_html( date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $snippet['updated_at'] ) ) )
											);
										}
										?>
									</p>
								<?php endif; ?>
							</div>
						<?php endif; ?>
					</div>
				</div>

				<!-- Location -->
				<div class="sndp-metabox">
					<h3><?php esc_html_e( 'Run Location', 'snipdrop' ); ?></h3>
					<div class="sndp-metabox-content">
						<select name="location" id="sndp-snippet-location">
							<optgroup label="<?php esc_attr_e( 'Global', 'snipdrop' ); ?>">
								<option value="everywhere" <?php selected( $snippet['location'], 'everywhere' ); ?>>
									<?php esc_html_e( 'Run Everywhere', 'snipdrop' ); ?>
								</option>
								<option value="frontend" <?php selected( $snippet['location'], 'frontend' ); ?>>
									<?php esc_html_e( 'Frontend Only', 'snipdrop' ); ?>
								</option>
								<option value="admin" <?php selected( $snippet['location'], 'admin' ); ?>>
									<?php esc_html_e( 'Admin Only', 'snipdrop' ); ?>
								</option>
							</optgroup>
							<optgroup label="<?php esc_attr_e( 'Auto-Insert', 'snipdrop' ); ?>">
								<option value="site_header" <?php selected( $snippet['location'], 'site_header' ); ?>>
									<?php esc_html_e( 'Site Header (wp_head)', 'snipdrop' ); ?>
								</option>
								<option value="site_footer" <?php selected( $snippet['location'], 'site_footer' ); ?>>
									<?php esc_html_e( 'Site Footer (wp_footer)', 'snipdrop' ); ?>
								</option>
								<option value="before_content" <?php selected( $snippet['location'], 'before_content' ); ?>>
									<?php esc_html_e( 'Before Post Content', 'snipdrop' ); ?>
								</option>
								<option value="after_content" <?php selected( $snippet['location'], 'after_content' ); ?>>
									<?php esc_html_e( 'After Post Content', 'snipdrop' ); ?>
								</option>
							</optgroup>
							<optgroup label="<?php esc_attr_e( 'Manual', 'snipdrop' ); ?>">
								<option value="shortcode" <?php selected( $snippet['location'], 'shortcode' ); ?>>
									<?php esc_html_e( 'Shortcode Only', 'snipdrop' ); ?>
								</option>
							</optgroup>
						</select>
						<p class="description sndp-shortcode-hint" id="sndp-shortcode-hint" style="<?php echo ( 'shortcode' === $snippet['location'] && ! empty( $snippet['id'] ) ) ? '' : 'display:none;'; ?>">
							<?php esc_html_e( 'Use shortcode:', 'snipdrop' ); ?>
							<code id="sndp-shortcode-code">[snipdrop id="<?php echo esc_attr( $snippet['id'] ); ?>"]</code>
							<button type="button" class="button button-small sndp-copy-shortcode" title="<?php esc_attr_e( 'Copy shortcode', 'snipdrop' ); ?>">
								<span class="dashicons dashicons-clipboard"></span>
							</button>
						</p>
					</div>
				</div>

				<!-- User Condition -->
				<div class="sndp-metabox sndp-conditional-options">
					<h3><?php esc_html_e( 'User Condition', 'snipdrop' ); ?></h3>
					<div class="sndp-metabox-content">
						<select name="user_cond" id="sndp-snippet-user-cond">
							<option value="all" <?php selected( $snippet['user_cond'], 'all' ); ?>>
								<?php esc_html_e( 'All Users', 'snipdrop' ); ?>
							</option>
							<option value="logged_in" <?php selected( $snippet['user_cond'], 'logged_in' ); ?>>
								<?php esc_html_e( 'Logged In Only', 'snipdrop' ); ?>
							</option>
							<option value="logged_out" <?php selected( $snippet['user_cond'], 'logged_out' ); ?>>
								<?php esc_html_e( 'Logged Out Only', 'snipdrop' ); ?>
							</option>
						</select>
						<p class="description">
							<?php esc_html_e( 'Run this snippet for specific user states.', 'snipdrop' ); ?>
						</p>
					</div>
				</div>

				<!-- Post Types -->
				<div class="sndp-metabox sndp-conditional-options sndp-frontend-only">
					<h3><?php esc_html_e( 'Post Types', 'snipdrop' ); ?></h3>
					<div class="sndp-metabox-content">
						<p class="description" style="margin-top: 0;">
							<?php esc_html_e( 'Leave unchecked to run on all post types.', 'snipdrop' ); ?>
						</p>
						<?php foreach ( $post_types as $pt ) : ?>
							<label class="sndp-checkbox-label">
								<input type="checkbox"
									name="post_types[]"
									value="<?php echo esc_attr( $pt->name ); ?>"
									<?php checked( in_array( $pt->name, (array) $snippet['post_types'], true ) ); ?>>
								<?php echo esc_html( $pt->labels->singular_name ); ?>
							</label>
						<?php endforeach; ?>
					</div>
				</div>

				<!-- Specific Pages -->
				<div class="sndp-metabox sndp-conditional-options sndp-frontend-only">
					<h3><?php esc_html_e( 'Specific Pages', 'snipdrop' ); ?></h3>
					<div class="sndp-metabox-content">
						<input type="text"
							name="page_ids"
							id="sndp-snippet-page-ids"
							class="regular-text"
							value="<?php echo esc_attr( $snippet['page_ids'] ); ?>"
							placeholder="<?php esc_attr_e( 'e.g., 1, 5, 23', 'snipdrop' ); ?>">
						<p class="description">
							<?php esc_html_e( 'Comma-separated post/page IDs. Leave empty to run on all pages.', 'snipdrop' ); ?>
						</p>
					</div>
				</div>

				<!-- Hook (for PHP) -->
				<div class="sndp-metabox sndp-php-options">
					<h3><?php esc_html_e( 'Execution', 'snipdrop' ); ?></h3>
					<div class="sndp-metabox-content">
						<div class="sndp-form-field">
							<label for="sndp-snippet-hook"><?php esc_html_e( 'Hook', 'snipdrop' ); ?></label>
							<select name="hook" id="sndp-snippet-hook">
								<option value="init" <?php selected( $snippet['hook'], 'init' ); ?>>init</option>
								<option value="wp_loaded" <?php selected( $snippet['hook'], 'wp_loaded' ); ?>>wp_loaded</option>
								<option value="admin_init" <?php selected( $snippet['hook'], 'admin_init' ); ?>>admin_init</option>
								<option value="wp_head" <?php selected( $snippet['hook'], 'wp_head' ); ?>>wp_head</option>
								<option value="wp_footer" <?php selected( $snippet['hook'], 'wp_footer' ); ?>>wp_footer</option>
								<option value="plugins_loaded" <?php selected( $snippet['hook'], 'plugins_loaded' ); ?>>plugins_loaded</option>
							</select>
						</div>
						<div class="sndp-form-field">
							<label for="sndp-snippet-priority"><?php esc_html_e( 'Priority', 'snipdrop' ); ?></label>
							<input type="number"
								id="sndp-snippet-priority"
								name="priority"
								value="<?php echo esc_attr( $snippet['priority'] ); ?>"
								min="1"
								max="999"
								class="small-text">
						</div>
					</div>
				</div>
			</div>
		</div>
	</form>
</div>
