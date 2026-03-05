<?php
/**
 * Add/Edit snippet page template.
 *
 * @package SnipDrop
 * @since   1.0.0
 */

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Template variables passed from class.

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$is_editing  = ! empty( $snippet );
$page_title  = $is_editing ? __( 'Edit Snippet', 'snipdrop' ) : __( 'Add New Snippet', 'snipdrop' );
$button_text = $is_editing ? __( 'Update Snippet', 'snipdrop' ) : __( 'Save Snippet', 'snipdrop' );

/**
 * Render a hierarchical checkbox tree for taxonomy terms.
 *
 * @param WP_Term[] $terms          Flat list of terms.
 * @param string    $taxonomy       Taxonomy name.
 * @param array     $saved          Already-selected "taxonomy:slug" values.
 * @param int       $parent_id      Parent term ID for current recursion level.
 */
function sndp_render_term_checklist_tree( $terms, $taxonomy, $saved, $parent_id = 0 ) {
	$children = array();
	foreach ( $terms as $sndp_term ) {
		if ( (int) $sndp_term->parent === $parent_id ) {
			$children[] = $sndp_term;
		}
	}

	if ( empty( $children ) ) {
		return;
	}

	foreach ( $children as $sndp_term ) {
		$value   = $taxonomy . ':' . $sndp_term->slug;
		$checked = in_array( $value, $saved, true );
		?>
		<li>
			<label>
				<input type="checkbox"
					name="taxonomies[]"
					value="<?php echo esc_attr( $value ); ?>"
					<?php checked( $checked ); ?>>
				<?php echo esc_html( $sndp_term->name ); ?>
			</label>
			<?php
			$has_children = false;
			foreach ( $terms as $sndp_child ) {
				if ( (int) $sndp_child->parent === (int) $sndp_term->term_id ) {
					$has_children = true;
					break;
				}
			}
			if ( $has_children ) :
				?>
				<ul class="children">
					<?php sndp_render_term_checklist_tree( $terms, $taxonomy, $saved, (int) $sndp_term->term_id ); ?>
				</ul>
			<?php endif; ?>
		</li>
		<?php
	}
}

// Defaults.
$defaults = array(
	'id'             => '',
	'title'          => '',
	'description'    => '',
	'code'           => '',
	'code_type'      => 'php',
	'status'         => 'inactive',
	'hook'           => 'init',
	'priority'       => 10,
	'location'       => 'everywhere',
	'user_cond'      => 'all',
	'post_types'     => array(),
	'page_ids'       => '',
	'url_patterns'   => '',
	'taxonomies'     => array(),
	'schedule_start' => '',
	'schedule_end'   => '',
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
					<div class="sndp-code-toolbar">
						<label for="sndp-snippet-code"><?php esc_html_e( 'Code', 'snipdrop' ); ?></label>
						<button type="button" id="sndp-dark-mode-toggle" class="button button-small sndp-dark-toggle" title="<?php esc_attr_e( 'Toggle dark mode', 'snipdrop' ); ?>">
							<span class="dashicons dashicons-editor-contract"></span>
							<?php esc_html_e( 'Dark Mode', 'snipdrop' ); ?>
						</button>
					</div>
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
					<p class="description" id="sndp-code-hint">
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
					<h3>
						<?php esc_html_e( 'Run Location', 'snipdrop' ); ?>
						<span class="sndp-help-tip" data-tip="<?php esc_attr_e( 'Where should this snippet execute? "Everywhere" runs on all pages. Auto-Insert locations inject output at specific positions. "Shortcode Only" lets you place it manually via [snipdrop] shortcode.', 'snipdrop' ); ?>">?</span>
					</h3>
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
						<p class="description sndp-shortcode-hint <?php echo ( 'shortcode' === $snippet['location'] && ! empty( $snippet['id'] ) ) ? '' : 'hidden'; ?>" id="sndp-shortcode-hint">
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
					<h3>
						<?php esc_html_e( 'User Condition', 'snipdrop' ); ?>
						<span class="sndp-help-tip" data-tip="<?php esc_attr_e( 'Restrict this snippet to logged-in users, logged-out users, or run for everyone.', 'snipdrop' ); ?>">?</span>
					</h3>
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

				<!-- Date/Time Schedule -->
				<?php
				$start_date = '';
				$start_time = '';
				if ( ! empty( $snippet['schedule_start'] ) ) {
					$parts      = explode( 'T', $snippet['schedule_start'] );
					$start_date = $parts[0] ?? '';
					$start_time = $parts[1] ?? '';
				}
				$end_date = '';
				$end_time = '';
				if ( ! empty( $snippet['schedule_end'] ) ) {
					$parts    = explode( 'T', $snippet['schedule_end'] );
					$end_date = $parts[0] ?? '';
					$end_time = $parts[1] ?? '';
				}
				?>
				<div class="sndp-metabox sndp-conditional-options">
					<h3>
						<?php esc_html_e( 'Schedule', 'snipdrop' ); ?>
						<span class="sndp-help-tip" data-tip="<?php esc_attr_e( 'Set a date range for when this snippet should be active. Uses your site timezone. Leave both empty to run always.', 'snipdrop' ); ?>">?</span>
					</h3>
					<div class="sndp-metabox-content">
						<div class="sndp-schedule-group">
							<label class="sndp-schedule-label"><?php esc_html_e( 'Start', 'snipdrop' ); ?></label>
							<div class="sndp-schedule-row">
								<input type="text"
									id="sndp-schedule-start-date"
									class="sndp-schedule-date sndp-datepicker"
									value="<?php echo esc_attr( $start_date ); ?>"
									placeholder="<?php esc_attr_e( 'YYYY-MM-DD', 'snipdrop' ); ?>"
									autocomplete="off">
								<input type="text"
									id="sndp-schedule-start-time"
									class="sndp-schedule-time"
									value="<?php echo esc_attr( $start_time ); ?>"
									placeholder="00:00"
									maxlength="5"
									pattern="[0-2][0-9]:[0-5][0-9]">
							</div>
						</div>
						<div class="sndp-schedule-group">
							<label class="sndp-schedule-label"><?php esc_html_e( 'End', 'snipdrop' ); ?></label>
							<div class="sndp-schedule-row">
								<input type="text"
									id="sndp-schedule-end-date"
									class="sndp-datepicker sndp-schedule-date"
									value="<?php echo esc_attr( $end_date ); ?>"
									placeholder="<?php esc_attr_e( 'YYYY-MM-DD', 'snipdrop' ); ?>"
									autocomplete="off">
								<input type="text"
									id="sndp-schedule-end-time"
									class="sndp-schedule-time"
									value="<?php echo esc_attr( $end_time ); ?>"
									placeholder="23:59"
									maxlength="5"
									pattern="[0-2][0-9]:[0-5][0-9]">
							</div>
						</div>
						<p class="description">
							<?php
							printf(
								/* translators: %s: Site timezone string */
								esc_html__( '24-hour time (e.g. 14:30 = 2:30 PM). Timezone: %s. Leave empty to run always.', 'snipdrop' ),
								'<code>' . esc_html( wp_timezone_string() ) . '</code>'
							);
							?>
						</p>
					</div>
				</div>

				<!-- Post Types -->
				<div class="sndp-metabox sndp-conditional-options sndp-frontend-only">
					<h3><?php esc_html_e( 'Post Types', 'snipdrop' ); ?></h3>
					<div class="sndp-metabox-content">
						<p class="description sndp-no-margin-top">
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

				<!-- Specific Posts/Pages -->
				<div class="sndp-metabox sndp-conditional-options sndp-frontend-only">
					<h3>
						<?php esc_html_e( 'Specific Posts / Pages', 'snipdrop' ); ?>
						<span class="sndp-help-tip" data-tip="<?php esc_attr_e( 'Search and select specific posts or pages where this snippet should run. Leave empty to run on all content.', 'snipdrop' ); ?>">?</span>
					</h3>
					<div class="sndp-metabox-content">
						<div class="sndp-page-picker">
							<input type="text"
								id="sndp-page-search"
								class="regular-text"
								placeholder="<?php esc_attr_e( 'Search posts and pages...', 'snipdrop' ); ?>"
								autocomplete="off">
							<div id="sndp-page-search-results" class="sndp-page-search-results"></div>
							<div id="sndp-selected-pages" class="sndp-selected-pages">
								<?php
								$saved_ids = array_filter( array_map( 'absint', explode( ',', $snippet['page_ids'] ) ) );
								foreach ( $saved_ids as $pid ) :
									$post_obj = get_post( $pid );
									if ( ! $post_obj ) {
										continue;
									}
									?>
									<span class="sndp-page-tag" data-id="<?php echo esc_attr( $pid ); ?>">
										<?php echo esc_html( $post_obj->post_title ); ?>
										<span class="sndp-page-tag-type"><?php echo esc_html( get_post_type_object( $post_obj->post_type )->labels->singular_name ); ?></span>
										<button type="button" class="sndp-page-tag-remove">&times;</button>
									</span>
								<?php endforeach; ?>
							</div>
							<input type="hidden"
								name="page_ids"
								id="sndp-snippet-page-ids"
								value="<?php echo esc_attr( $snippet['page_ids'] ); ?>">
						</div>
						<p class="description">
							<?php esc_html_e( 'Leave empty to run on all posts and pages.', 'snipdrop' ); ?>
						</p>
					</div>
				</div>

				<!-- URL Patterns -->
				<div class="sndp-metabox sndp-conditional-options sndp-frontend-only">
					<h3>
						<?php esc_html_e( 'URL Patterns', 'snipdrop' ); ?>
						<span class="sndp-help-tip" data-tip="<?php esc_attr_e( 'Run this snippet only on URLs matching these patterns. Use * as wildcard. One pattern per line. Example: /shop/*, /checkout, /my-account/*', 'snipdrop' ); ?>">?</span>
					</h3>
					<div class="sndp-metabox-content">
						<textarea name="url_patterns"
							id="sndp-snippet-url-patterns"
							rows="3"
							placeholder="<?php esc_attr_e( '/shop/*&#10;/checkout&#10;/my-account/*', 'snipdrop' ); ?>"><?php echo esc_textarea( $snippet['url_patterns'] ); ?></textarea>
						<p class="description">
							<?php esc_html_e( 'One pattern per line. Use * as wildcard. Leave empty to run on all URLs.', 'snipdrop' ); ?>
						</p>
					</div>
				</div>

				<!-- Taxonomy Conditions -->
				<div class="sndp-metabox sndp-conditional-options sndp-frontend-only">
					<h3>
						<?php esc_html_e( 'Taxonomy Terms', 'snipdrop' ); ?>
						<span class="sndp-help-tip" data-tip="<?php esc_attr_e( 'Run this snippet only when the current post belongs to specific categories, tags, or custom taxonomy terms.', 'snipdrop' ); ?>">?</span>
					</h3>
					<div class="sndp-metabox-content">
						<?php
						/**
						 * Available public taxonomies.
						 *
						 * @var array<string, WP_Taxonomy> $available_taxonomies
						 */
						$available_taxonomies = get_taxonomies(
							array(
								'public'  => true,
								'show_ui' => true,
							),
							'objects'
						);
						unset( $available_taxonomies['post_format'] );

						$saved_taxonomies = (array) $snippet['taxonomies'];

						// Build unique labels — append object type when names collide.
						$label_counts = array();
						foreach ( $available_taxonomies as $sndp_tax ) {
							$name                  = $sndp_tax->labels->singular_name;
							$label_counts[ $name ] = isset( $label_counts[ $name ] ) ? $label_counts[ $name ] + 1 : 1;
						}

						foreach ( $available_taxonomies as $sndp_tax ) :
							$sndp_terms = get_terms(
								array(
									'taxonomy'   => $sndp_tax->name,
									'hide_empty' => true,
									'number'     => 100,
									'orderby'    => 'name',
									'order'      => 'ASC',
								)
							);

							if ( empty( $sndp_terms ) || is_wp_error( $sndp_terms ) ) {
								continue;
							}

							$tax_label = $sndp_tax->labels->singular_name;
							if ( $label_counts[ $tax_label ] > 1 ) {
								$object_types = $sndp_tax->object_type;
								if ( ! empty( $object_types ) ) {
									$pt_obj    = get_post_type_object( $object_types[0] );
									$pt_name   = $pt_obj ? $pt_obj->labels->singular_name : $object_types[0];
									$tax_label = sprintf( '%s (%s)', $tax_label, $pt_name );
								}
							}

							$is_hierarchical = is_taxonomy_hierarchical( $sndp_tax->name );
							?>
							<div class="sndp-taxonomy-group">
								<label class="sndp-taxonomy-label"><?php echo esc_html( $tax_label ); ?></label>
								<div class="sndp-taxonomy-checklist-wrap">
									<ul class="sndp-taxonomy-checklist">
										<?php
										if ( $is_hierarchical ) {
											sndp_render_term_checklist_tree( $sndp_terms, $sndp_tax->name, $saved_taxonomies );
										} else {
											foreach ( $sndp_terms as $sndp_term ) :
												$value   = $sndp_tax->name . ':' . $sndp_term->slug;
												$checked = in_array( $value, $saved_taxonomies, true );
												?>
												<li>
													<label>
														<input type="checkbox"
															name="taxonomies[]"
															value="<?php echo esc_attr( $value ); ?>"
															<?php checked( $checked ); ?>>
														<?php echo esc_html( $sndp_term->name ); ?>
													</label>
												</li>
												<?php
											endforeach;
										}
										?>
									</ul>
								</div>
							</div>
						<?php endforeach; ?>
						<p class="description">
							<?php esc_html_e( 'Leave all unchecked to run on all content.', 'snipdrop' ); ?>
						</p>
					</div>
				</div>

				<!-- Revisions -->
				<?php
				if ( $is_editing ) :
					$revisions = SNDP_Custom_Snippets::instance()->get_revisions( $snippet['id'] );
					if ( ! empty( $revisions ) ) :
						?>
						<div class="sndp-metabox">
							<h3>
								<?php esc_html_e( 'Revision History', 'snipdrop' ); ?>
								<span class="sndp-help-tip" data-tip="<?php esc_attr_e( 'Previous versions of this snippet are saved automatically. Click Restore to revert to that version.', 'snipdrop' ); ?>">?</span>
							</h3>
							<div class="sndp-metabox-content sndp-revisions-list">
								<?php
								foreach ( $revisions as $idx => $rev ) :
									$rev_user = '';
									if ( ! empty( $rev['user'] ) ) {
										$u = get_userdata( $rev['user'] );
										if ( $u ) {
											$rev_user = $u->display_name;
										}
									}
									$rev_date_display = date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $rev['date'] ) );
									?>
									<div class="sndp-revision-item">
										<span class="sndp-revision-date">
											<?php echo esc_html( $rev_date_display ); ?>
											<?php if ( $rev_user ) : ?>
												<em><?php echo esc_html( $rev_user ); ?></em>
											<?php endif; ?>
										</span>
										<span class="sndp-revision-actions">
											<button type="button"
												class="button button-small sndp-view-diff"
												<?php // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- Encoding snippet code for safe HTML attribute transport to JS diff viewer. ?>
												data-revision-code="<?php echo esc_attr( base64_encode( $rev['code'] ) ); ?>"
												data-revision-date="<?php echo esc_attr( $rev_date_display ); ?>">
												<?php esc_html_e( 'View Changes', 'snipdrop' ); ?>
											</button>
											<button type="button"
												class="button button-small sndp-restore-revision"
												data-snippet-id="<?php echo esc_attr( $snippet['id'] ); ?>"
												data-revision-index="<?php echo esc_attr( $idx ); ?>">
												<?php esc_html_e( 'Restore', 'snipdrop' ); ?>
											</button>
										</span>
									</div>
								<?php endforeach; ?>
							</div>
						</div>
					<?php endif; ?>
				<?php endif; ?>

				<!-- Hook (for PHP) -->
				<div class="sndp-metabox sndp-php-options">
					<h3>
						<?php esc_html_e( 'Execution', 'snipdrop' ); ?>
						<span class="sndp-help-tip" data-tip="<?php esc_attr_e( 'Hook: the WordPress action this snippet attaches to. Priority: lower numbers run earlier. Default (init, 10) works for most snippets.', 'snipdrop' ); ?>">?</span>
					</h3>
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

<!-- Diff Modal -->
<div id="sndp-diff-modal" class="sndp-modal">
	<div class="sndp-modal-content sndp-diff-modal-content">
		<div class="sndp-modal-header">
			<h3 id="sndp-diff-modal-title"><?php esc_html_e( 'View Changes', 'snipdrop' ); ?></h3>
			<button type="button" class="sndp-modal-close">&times;</button>
		</div>
		<div class="sndp-modal-body">
			<div id="sndp-diff-legend" class="sndp-diff-legend">
				<span class="sndp-diff-legend-added"><?php esc_html_e( 'Added', 'snipdrop' ); ?></span>
				<span class="sndp-diff-legend-removed"><?php esc_html_e( 'Removed', 'snipdrop' ); ?></span>
				<span class="sndp-diff-legend-context"><?php esc_html_e( 'Unchanged', 'snipdrop' ); ?></span>
			</div>
			<div id="sndp-diff-output" class="sndp-diff-output"></div>
		</div>
	</div>
</div>
