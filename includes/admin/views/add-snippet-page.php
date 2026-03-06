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
	'schedule_start'    => '',
	'schedule_end'      => '',
	'tags'              => array(),
	'shortcode_name'    => '',
	'insert_paragraph'  => 2,
);

$snippet = wp_parse_args( $snippet ? $snippet : array(), $defaults );

// Get post types for conditions.
$post_types = get_post_types( array( 'public' => true ), 'objects' );
unset( $post_types['attachment'] );

// Determine if any conditions are set.
$has_conditions = (
	'all' !== $snippet['user_cond']
	|| ! empty( $snippet['post_types'] )
	|| '' !== $snippet['page_ids']
	|| '' !== $snippet['url_patterns']
	|| ! empty( $snippet['taxonomies'] )
	|| '' !== $snippet['schedule_start']
	|| '' !== $snippet['schedule_end']
);
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
			<!-- ======================== MAIN AREA ======================== -->
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

				<!-- ======================== CONDITIONAL LOGIC PANEL ======================== -->
				<?php
				$conditional_rules = isset( $snippet['conditional_rules'] ) ? $snippet['conditional_rules'] : array();
				$has_new_rules     = ! empty( $conditional_rules ) && ! empty( $conditional_rules['enabled'] );
				$panel_open        = $has_conditions || $has_new_rules;
				?>
				<div class="sndp-conditions-panel" id="sndp-conditions-panel">
					<input type="hidden" id="sndp-conditional-rules" name="conditional_rules"
						value="<?php echo esc_attr( ! empty( $conditional_rules ) ? wp_json_encode( $conditional_rules ) : '' ); ?>">

					<div class="sndp-conditions-header">
						<h3>
							<span class="dashicons dashicons-filter"></span>
							<?php esc_html_e( 'Smart Conditional Logic', 'snipdrop' ); ?>
						</h3>
						<label class="sndp-conditions-toggle">
							<input type="checkbox" id="sndp-enable-conditions" <?php checked( $panel_open ); ?>>
							<span><?php esc_html_e( 'Enable Logic', 'snipdrop' ); ?></span>
						</label>
					</div>
					<div class="sndp-conditions-body" <?php echo $panel_open ? '' : 'style="display:none;"'; ?>>
						<p class="sndp-conditions-intro">
							<?php esc_html_e( 'Using conditional logic you can limit where and when this snippet runs.', 'snipdrop' ); ?>
						</p>

						<div class="sndp-conditions-grid">
							<!-- User Condition -->
							<div class="sndp-condition-section">
								<h4><?php esc_html_e( 'User Condition', 'snipdrop' ); ?></h4>
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
							</div>

							<!-- Schedule -->
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
							<div class="sndp-condition-section">
								<h4><?php esc_html_e( 'Schedule', 'snipdrop' ); ?></h4>
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
										esc_html__( 'Timezone: %s', 'snipdrop' ),
										'<code>' . esc_html( wp_timezone_string() ) . '</code>'
									);
									?>
								</p>
							</div>
						</div>

						<!-- Page Targeting (hidden for Admin Only location) -->
						<div class="sndp-conditions-page-targeting sndp-frontend-only">
							<h4><?php esc_html_e( 'Page Targeting', 'snipdrop' ); ?></h4>
							<div class="sndp-conditions-grid">
								<!-- Post Types -->
								<div class="sndp-condition-section">
									<label class="sndp-condition-label"><?php esc_html_e( 'Post Types', 'snipdrop' ); ?></label>
									<div class="sndp-condition-checkboxes">
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
									<p class="description"><?php esc_html_e( 'Leave unchecked for all.', 'snipdrop' ); ?></p>
								</div>

								<!-- Specific Posts/Pages -->
								<div class="sndp-condition-section">
									<label class="sndp-condition-label"><?php esc_html_e( 'Specific Posts / Pages', 'snipdrop' ); ?></label>
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
								</div>
							</div>

							<div class="sndp-conditions-grid">
								<!-- URL Patterns -->
								<div class="sndp-condition-section">
									<label class="sndp-condition-label"><?php esc_html_e( 'URL Patterns', 'snipdrop' ); ?></label>
									<textarea name="url_patterns"
										id="sndp-snippet-url-patterns"
										rows="3"
										placeholder="<?php esc_attr_e( '/shop/*&#10;/checkout&#10;/my-account/*', 'snipdrop' ); ?>"><?php echo esc_textarea( $snippet['url_patterns'] ); ?></textarea>
									<p class="description"><?php esc_html_e( 'One per line, * as wildcard.', 'snipdrop' ); ?></p>
								</div>

								<!-- Taxonomy Terms -->
								<div class="sndp-condition-section">
									<label class="sndp-condition-label"><?php esc_html_e( 'Taxonomy Terms', 'snipdrop' ); ?></label>
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
								</div>
							</div>
						</div>
					</div>
				</div>

			</div>

			<!-- ======================== SIDEBAR ======================== -->
			<div class="sndp-form-sidebar">
				<!-- Actions -->
				<div class="sndp-metabox sndp-metabox-actions">
					<button type="submit" class="button button-primary button-large sndp-save-snippet">
						<?php echo esc_html( $button_text ); ?>
					</button>
					<button type="button" class="button sndp-save-activate-snippet">
						<?php esc_html_e( 'Save & Activate', 'snipdrop' ); ?>
					</button>
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

						<?php
						$tm_instance = SNDP_Testing_Mode::instance();
						$tm_active   = $tm_instance->is_enabled();
						?>
						<div class="sndp-testing-mode-status <?php echo $tm_active ? 'is-active' : ''; ?>">
							<label>
								<input type="checkbox"
									class="sndp-testing-mode-toggle"
									value="1"
									<?php checked( $tm_active ); ?>>
								<span><?php esc_html_e( 'Testing Mode', 'snipdrop' ); ?></span>
							</label>
							<p class="description">
								<?php
								if ( $tm_active ) {
									esc_html_e( 'Active — changes are staged, not live.', 'snipdrop' );
								} else {
									esc_html_e( 'Stage changes before publishing.', 'snipdrop' );
								}
								?>
							</p>
						</div>

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
								<option value="body_open" <?php selected( $snippet['location'], 'body_open' ); ?>>
									<?php esc_html_e( 'After Body Open (wp_body_open)', 'snipdrop' ); ?>
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
								<option value="after_paragraph" <?php selected( $snippet['location'], 'after_paragraph' ); ?>>
									<?php esc_html_e( 'After Paragraph #', 'snipdrop' ); ?>
								</option>
							</optgroup>
							<?php if ( class_exists( 'WooCommerce' ) ) : ?>
							<optgroup label="<?php esc_attr_e( 'WooCommerce', 'snipdrop' ); ?>">
								<option value="wc_before_shop_loop" <?php selected( $snippet['location'], 'wc_before_shop_loop' ); ?>>
									<?php esc_html_e( 'Before Shop Loop', 'snipdrop' ); ?>
								</option>
								<option value="wc_after_shop_loop" <?php selected( $snippet['location'], 'wc_after_shop_loop' ); ?>>
									<?php esc_html_e( 'After Shop Loop', 'snipdrop' ); ?>
								</option>
								<option value="wc_before_single_product" <?php selected( $snippet['location'], 'wc_before_single_product' ); ?>>
									<?php esc_html_e( 'Before Single Product', 'snipdrop' ); ?>
								</option>
								<option value="wc_after_single_product" <?php selected( $snippet['location'], 'wc_after_single_product' ); ?>>
									<?php esc_html_e( 'After Single Product', 'snipdrop' ); ?>
								</option>
								<option value="wc_before_cart" <?php selected( $snippet['location'], 'wc_before_cart' ); ?>>
									<?php esc_html_e( 'Before Cart', 'snipdrop' ); ?>
								</option>
								<option value="wc_before_checkout_form" <?php selected( $snippet['location'], 'wc_before_checkout_form' ); ?>>
									<?php esc_html_e( 'Before Checkout Form', 'snipdrop' ); ?>
								</option>
								<option value="wc_after_checkout_form" <?php selected( $snippet['location'], 'wc_after_checkout_form' ); ?>>
									<?php esc_html_e( 'After Checkout Form', 'snipdrop' ); ?>
								</option>
								<option value="wc_thankyou" <?php selected( $snippet['location'], 'wc_thankyou' ); ?>>
									<?php esc_html_e( 'Order Thank You Page', 'snipdrop' ); ?>
								</option>
							</optgroup>
							<?php endif; ?>
							<optgroup label="<?php esc_attr_e( 'Manual', 'snipdrop' ); ?>">
								<option value="shortcode" <?php selected( $snippet['location'], 'shortcode' ); ?>>
									<?php esc_html_e( 'Shortcode Only', 'snipdrop' ); ?>
								</option>
							</optgroup>
						</select>
						<div class="sndp-shortcode-hint <?php echo ( 'shortcode' === $snippet['location'] && ! empty( $snippet['id'] ) ) ? '' : 'hidden'; ?>" id="sndp-shortcode-hint">
							<p class="description">
								<?php esc_html_e( 'Default shortcode:', 'snipdrop' ); ?>
								<code id="sndp-shortcode-code">[snipdrop id="<?php echo esc_attr( $snippet['id'] ); ?>"]</code>
								<button type="button" class="button button-small sndp-copy-shortcode" title="<?php esc_attr_e( 'Copy shortcode', 'snipdrop' ); ?>">
									<span class="dashicons dashicons-clipboard"></span>
								</button>
							</p>
							<div class="sndp-shortcode-name-field">
								<label for="sndp-shortcode-name"><?php esc_html_e( 'Custom shortcode name:', 'snipdrop' ); ?></label>
								<div class="sndp-shortcode-name-wrap">
									<span class="sndp-shortcode-prefix">[</span>
									<input type="text"
										id="sndp-shortcode-name"
										name="shortcode_name"
										value="<?php echo esc_attr( isset( $snippet['shortcode_name'] ) ? $snippet['shortcode_name'] : '' ); ?>"
										placeholder="<?php esc_attr_e( 'my-snippet', 'snipdrop' ); ?>"
										pattern="[a-z0-9_\-]+"
										class="regular-text">
									<span class="sndp-shortcode-suffix">]</span>
								</div>
								<p class="description"><?php esc_html_e( 'Optional. Lowercase letters, numbers, hyphens, underscores only.', 'snipdrop' ); ?></p>
							</div>
						</div>
						<div class="sndp-paragraph-hint <?php echo ( 'after_paragraph' === $snippet['location'] ) ? '' : 'hidden'; ?>" id="sndp-paragraph-hint">
							<label for="sndp-insert-paragraph"><?php esc_html_e( 'Insert after paragraph:', 'snipdrop' ); ?></label>
							<input type="number"
								id="sndp-insert-paragraph"
								name="insert_paragraph"
								value="<?php echo esc_attr( $snippet['insert_paragraph'] ); ?>"
								min="1"
								max="100"
								class="small-text">
						</div>
					</div>
				</div>

				<!-- Execution (PHP only) -->
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

				<!-- Tags -->
				<div class="sndp-metabox">
					<h3><?php esc_html_e( 'Tags', 'snipdrop' ); ?></h3>
					<div class="sndp-metabox-content">
						<input type="text"
							id="sndp-snippet-tags"
							name="tags"
							class="large-text"
							value="<?php echo esc_attr( implode( ', ', (array) $snippet['tags'] ) ); ?>"
							placeholder="<?php esc_attr_e( 'e.g. woocommerce, checkout, custom', 'snipdrop' ); ?>">
						<p class="description">
							<?php esc_html_e( 'Comma-separated tags to organize your snippets.', 'snipdrop' ); ?>
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
								<span class="sndp-help-tip" data-tip="<?php esc_attr_e( 'Previous versions of this snippet are saved automatically when you make changes. Click Restore to revert.', 'snipdrop' ); ?>">?</span>
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

									// Build a summary of what changed.
									$changed_fields = array();
									$compare_map    = array(
										'code'        => __( 'Code', 'snipdrop' ),
										'title'       => __( 'Title', 'snipdrop' ),
										'description' => __( 'Description', 'snipdrop' ),
										'code_type'   => __( 'Type', 'snipdrop' ),
										'location'    => __( 'Location', 'snipdrop' ),
										'hook'        => __( 'Hook', 'snipdrop' ),
										'priority'    => __( 'Priority', 'snipdrop' ),
									);
									foreach ( $compare_map as $field => $label ) {
										if ( isset( $rev[ $field ] ) && ( $rev[ $field ] ?? '' ) !== ( $snippet[ $field ] ?? '' ) ) {
											$changed_fields[] = $label;
										}
									}

									$rev_code = isset( $rev['code'] ) ? $rev['code'] : '';
									?>
									<div class="sndp-revision-item">
										<span class="sndp-revision-date">
											<?php echo esc_html( $rev_date_display ); ?>
											<?php if ( $rev_user ) : ?>
												<em><?php echo esc_html( $rev_user ); ?></em>
											<?php endif; ?>
											<?php if ( ! empty( $changed_fields ) ) : ?>
												<span class="sndp-revision-changes">&mdash; <?php echo esc_html( implode( ', ', $changed_fields ) ); ?></span>
											<?php endif; ?>
										</span>
										<span class="sndp-revision-actions">
											<?php if ( $rev_code ) : ?>
											<button type="button"
												class="button button-small sndp-view-diff"
												<?php // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- Encoding snippet code for safe HTML attribute transport to JS diff viewer. ?>
												data-revision-code="<?php echo esc_attr( base64_encode( $rev_code ) ); ?>"
												data-revision-date="<?php echo esc_attr( $rev_date_display ); ?>">
												<?php esc_html_e( 'View Diff', 'snipdrop' ); ?>
											</button>
											<?php endif; ?>
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
