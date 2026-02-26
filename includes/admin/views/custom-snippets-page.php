<?php
/**
 * Custom snippets admin page template.
 *
 * @package SnipDrop
 * @since   1.0.0
 */

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Template variables passed from class.

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="wrap sndp-wrap">
	<h1 class="wp-heading-inline"><?php esc_html_e( 'My Snippets', 'snipdrop' ); ?></h1>
	<a href="<?php echo esc_url( admin_url( 'admin.php?page=snipdrop-add' ) ); ?>" class="page-title-action">
		<?php esc_html_e( 'Add New', 'snipdrop' ); ?>
	</a>

	<?php if ( ! empty( $custom_snippets ) ) : ?>
		<a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=sndp_export_snippets' ), 'sndp_export_snippets', 'sndp_export_nonce' ) ); ?>" class="page-title-action">
			<?php esc_html_e( 'Export', 'snipdrop' ); ?>
		</a>
	<?php endif; ?>

	<button type="button" class="page-title-action" id="sndp-import-trigger">
		<?php esc_html_e( 'Import', 'snipdrop' ); ?>
	</button>

	<div id="sndp-import-form" class="sndp-import-form" style="display:none;">
		<form id="sndp-import-upload" enctype="multipart/form-data">
			<input type="file" name="import_file" id="sndp-import-file" accept=".json">
			<button type="submit" class="button button-primary" id="sndp-import-submit" disabled>
				<?php esc_html_e( 'Upload & Import', 'snipdrop' ); ?>
			</button>
			<button type="button" class="button" id="sndp-import-cancel">
				<?php esc_html_e( 'Cancel', 'snipdrop' ); ?>
			</button>
			<p class="description">
				<?php esc_html_e( 'Supports SnipDrop, WPCode, and Code Snippets export formats. All snippets are imported as inactive.', 'snipdrop' ); ?>
			</p>
		</form>
	</div>

	<hr class="wp-header-end">

	<?php if ( empty( $custom_snippets ) ) : ?>
		<div class="sndp-empty-state">
			<div class="sndp-empty-state-icon">
				<span class="dashicons dashicons-editor-code"></span>
			</div>
			<h2><?php esc_html_e( 'No custom snippets yet', 'snipdrop' ); ?></h2>
			<p><?php esc_html_e( 'Create your own code snippets, copy from the library, or import from another plugin.', 'snipdrop' ); ?></p>
			<p>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=snipdrop-add' ) ); ?>" class="button button-primary">
					<?php esc_html_e( 'Create Your First Snippet', 'snipdrop' ); ?>
				</a>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=snipdrop' ) ); ?>" class="button">
					<?php esc_html_e( 'Browse Library', 'snipdrop' ); ?>
				</a>
			</p>
		</div>
	<?php else : ?>
		<div class="sndp-custom-toolbar">
			<div class="sndp-bulk-actions">
				<select id="sndp-bulk-action">
					<option value=""><?php esc_html_e( 'Bulk Actions', 'snipdrop' ); ?></option>
					<option value="activate"><?php esc_html_e( 'Activate', 'snipdrop' ); ?></option>
					<option value="deactivate"><?php esc_html_e( 'Deactivate', 'snipdrop' ); ?></option>
					<option value="delete"><?php esc_html_e( 'Delete', 'snipdrop' ); ?></option>
				</select>
				<button type="button" class="button" id="sndp-bulk-apply" disabled>
					<?php esc_html_e( 'Apply', 'snipdrop' ); ?>
				</button>
			</div>
			<input type="search" id="sndp-custom-search" class="sndp-search-input" placeholder="<?php esc_attr_e( 'Filter snippets...', 'snipdrop' ); ?>">
		</div>
		<table class="wp-list-table widefat fixed striped sndp-custom-snippets-table">
			<thead>
				<tr>
					<td class="manage-column column-cb check-column">
						<input type="checkbox" id="sndp-select-all">
					</td>
					<th scope="col" class="column-status"><?php esc_html_e( 'Status', 'snipdrop' ); ?></th>
					<th scope="col" class="column-title"><?php esc_html_e( 'Title', 'snipdrop' ); ?></th>
					<th scope="col" class="column-type"><?php esc_html_e( 'Type', 'snipdrop' ); ?></th>
					<th scope="col" class="column-location"><?php esc_html_e( 'Location', 'snipdrop' ); ?></th>
					<th scope="col" class="column-author"><?php esc_html_e( 'Author', 'snipdrop' ); ?></th>
					<th scope="col" class="column-updated"><?php esc_html_e( 'Updated', 'snipdrop' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $custom_snippets as $snippet_id => $snippet ) : ?>
					<?php
					$is_active  = 'active' === $snippet['status'];
					$has_error  = isset( $snippet['last_error'] );
					$row_class  = $is_active ? 'sndp-snippet-active' : '';
					$row_class .= $has_error ? ' sndp-snippet-error' : '';
					?>
					<tr class="<?php echo esc_attr( $row_class ); ?>" data-snippet-id="<?php echo esc_attr( $snippet_id ); ?>">
						<th scope="row" class="check-column">
							<input type="checkbox" class="sndp-bulk-check" value="<?php echo esc_attr( $snippet_id ); ?>">
						</th>
						<td class="column-status">
							<label class="sndp-toggle">
								<input type="checkbox"
									class="sndp-toggle-input sndp-custom-toggle"
									data-snippet-id="<?php echo esc_attr( $snippet_id ); ?>"
									<?php checked( $is_active ); ?>
									<?php disabled( $has_error ); ?>>
								<span class="sndp-toggle-slider"></span>
							</label>
							<?php if ( $has_error ) : ?>
								<span class="sndp-error-indicator" title="<?php echo esc_attr( $snippet['last_error']['message'] ); ?>">
									<span class="dashicons dashicons-warning"></span>
								</span>
							<?php endif; ?>
						</td>
						<td class="column-title">
							<strong>
								<a href="<?php echo esc_url( admin_url( 'admin.php?page=snipdrop-add&id=' . $snippet_id ) ); ?>">
									<?php echo esc_html( $snippet['title'] ); ?>
								</a>
							</strong>
							<?php if ( ! empty( $snippet['description'] ) ) : ?>
								<p class="description"><?php echo esc_html( $snippet['description'] ); ?></p>
							<?php endif; ?>
							<div class="row-actions">
								<span class="edit">
									<a href="<?php echo esc_url( admin_url( 'admin.php?page=snipdrop-add&id=' . $snippet_id ) ); ?>">
										<?php esc_html_e( 'Edit', 'snipdrop' ); ?>
									</a> |
								</span>
								<span class="duplicate">
									<a href="#" class="sndp-duplicate-custom" data-snippet-id="<?php echo esc_attr( $snippet_id ); ?>">
										<?php esc_html_e( 'Duplicate', 'snipdrop' ); ?>
									</a> |
								</span>
								<span class="delete">
									<a href="#" class="sndp-delete-custom" data-snippet-id="<?php echo esc_attr( $snippet_id ); ?>">
										<?php esc_html_e( 'Delete', 'snipdrop' ); ?>
									</a>
								</span>
							</div>
						</td>
						<td class="column-type">
							<span class="sndp-code-type sndp-code-type-<?php echo esc_attr( $snippet['code_type'] ); ?>">
								<?php echo esc_html( strtoupper( $snippet['code_type'] ) ); ?>
							</span>
						</td>
						<td class="column-location">
							<?php
							$location = isset( $snippet['location'] ) ? $snippet['location'] : 'everywhere';

							// For shortcode, just show the shortcode (no label).
							if ( 'shortcode' === $location ) {
								echo '<code class="sndp-shortcode-display">[snipdrop id="' . esc_attr( $snippet_id ) . '"]</code>';
							} else {
								$locations = array(
									'everywhere'     => __( 'Everywhere', 'snipdrop' ),
									'frontend'       => __( 'Frontend', 'snipdrop' ),
									'admin'          => __( 'Admin', 'snipdrop' ),
									'site_header'    => __( 'Site Header', 'snipdrop' ),
									'site_footer'    => __( 'Site Footer', 'snipdrop' ),
									'before_content' => __( 'Before Content', 'snipdrop' ),
									'after_content'  => __( 'After Content', 'snipdrop' ),
								);
								echo esc_html( isset( $locations[ $location ] ) ? $locations[ $location ] : $location );
							}
							?>
						</td>
						<td class="column-author">
							<?php
							$author_id = isset( $snippet['created_by'] ) ? $snippet['created_by'] : 0;
							if ( $author_id ) {
								$author = get_userdata( $author_id );
								echo $author ? esc_html( $author->display_name ) : '&mdash;';
							} else {
								echo '&mdash;';
							}
							?>
						</td>
						<td class="column-updated">
							<?php
							if ( ! empty( $snippet['updated_at'] ) ) {
								echo esc_html( human_time_diff( strtotime( $snippet['updated_at'] ), time() ) . ' ' . __( 'ago', 'snipdrop' ) );
							} else {
								echo '&mdash;';
							}
							?>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	<?php endif; ?>
</div>
