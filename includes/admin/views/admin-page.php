<?php
/**
 * Admin page template.
 *
 * @package SnipDrop
 * @since   1.0.0
 *
 * @var array  $categories      Categories from library.
 * @var array  $enabled         Enabled snippet IDs.
 * @var array  $error_snippets  Snippets with errors.
 * @var string $library_version Library version.
 * @var int    $last_sync       Last sync timestamp.
 * @var string $current_category Current category filter.
 * @var int    $total_snippets  Total snippets in library.
 */

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Template variables passed from class.

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="wrap sndp-admin-wrap">
	<h1 class="wp-heading-inline"><?php esc_html_e( 'SnipDrop Snippets', 'snipdrop' ); ?></h1>

	<div class="sndp-header-actions">
		<button type="button" class="button sndp-sync-btn" id="sndp-sync-library">
			<span class="dashicons dashicons-update"></span>
			<?php esc_html_e( 'Sync Library', 'snipdrop' ); ?>
		</button>
		<span class="sndp-library-info">
			<?php
			printf(
				/* translators: %s: Library version */
				esc_html__( 'Library v%s', 'snipdrop' ),
				esc_html( $library_version )
			);
			if ( $last_sync ) {
				echo ' &bull; ';
				printf(
					/* translators: %s: Human-readable time difference */
					esc_html__( 'Last synced %s ago', 'snipdrop' ),
					esc_html( human_time_diff( $last_sync ) )
				);
			}
			?>
		</span>
	</div>

	<hr class="wp-header-end">

	<div class="sndp-admin-content">
		<!-- Sidebar: Categories -->
		<div class="sndp-sidebar">
			<h3><?php esc_html_e( 'Categories', 'snipdrop' ); ?></h3>
			<ul class="sndp-category-list" id="sndp-category-list">
				<li>
					<a href="#" class="sndp-category-link active" data-category="">
						<?php esc_html_e( 'All Snippets', 'snipdrop' ); ?>
						<span class="count" id="sndp-total-count">(<?php echo absint( $total_snippets ); ?>)</span>
					</a>
				</li>
				<?php foreach ( $categories as $category ) : ?>
					<li>
						<a href="#" class="sndp-category-link" data-category="<?php echo esc_attr( $category['id'] ); ?>">
							<?php echo esc_html( $category['name'] ); ?>
							<span class="count">(<?php echo absint( $category['count'] ); ?>)</span>
						</a>
					</li>
				<?php endforeach; ?>
			</ul>

			<div class="sndp-stats-box">
				<p class="sndp-enabled-stat">
					<?php esc_html_e( 'Enabled:', 'snipdrop' ); ?>
					<span id="sndp-enabled-count"><?php echo count( $enabled ); ?></span>
				</p>
				<?php if ( ! empty( $error_snippets ) ) : ?>
					<p class="sndp-error-stat">
						<?php esc_html_e( 'Errors:', 'snipdrop' ); ?>
						<span><?php echo count( $error_snippets ); ?></span>
					</p>
				<?php endif; ?>
			</div>
		</div>

		<!-- Main: Snippets List -->
		<div class="sndp-main">
			<!-- Search Bar -->
			<div class="sndp-search-bar">
				<input type="search" id="sndp-search-input" class="sndp-search-input" placeholder="<?php esc_attr_e( 'Search snippets...', 'snipdrop' ); ?>">
			</div>

			<!-- Results Info -->
			<div class="sndp-results-info" id="sndp-results-info">
				<span class="sndp-showing"></span>
			</div>

			<!-- Snippets Grid -->
			<div class="sndp-snippets-grid" id="sndp-snippets-grid">
				<div class="sndp-loading">
					<span class="spinner is-active"></span>
					<p><?php esc_html_e( 'Loading snippets...', 'snipdrop' ); ?></p>
				</div>
			</div>

			<!-- Pagination -->
			<div class="sndp-pagination" id="sndp-pagination"></div>
		</div>
	</div>
</div>

<!-- Snippet Card Template -->
<script type="text/html" id="tmpl-sndp-snippet-card">
	<div class="sndp-snippet-card {{ data.is_enabled ? 'enabled' : '' }} {{ data.has_error ? 'has-error' : '' }}" data-snippet-id="{{ data.id }}" data-requires="{{ JSON.stringify(data.requires || []) }}">
		<div class="sndp-snippet-header">
			<h4 class="sndp-snippet-title">{{ data.title }}</h4>
			<label class="sndp-toggle">
				<input type="checkbox" class="sndp-toggle-input" {{ data.is_enabled ? 'checked' : '' }} {{ data.has_error ? 'disabled' : '' }}>
				<span class="sndp-toggle-slider"></span>
			</label>
		</div>

		<p class="sndp-snippet-desc">{{ data.description }}</p>

		<# if ( data.has_error && data.error_msg ) { #>
			<div class="sndp-snippet-error">
				<span class="dashicons dashicons-warning"></span>
				<?php esc_html_e( 'Error:', 'snipdrop' ); ?> {{ data.error_msg.message || '<?php esc_html_e( 'Unknown error', 'snipdrop' ); ?>' }}
			</div>
		<# } #>

		<div class="sndp-snippet-meta">
			<# if ( data.requires && data.requires.length ) { #>
				<span class="sndp-requires" title="<?php esc_attr_e( 'Required plugins', 'snipdrop' ); ?>">
					<span class="dashicons dashicons-admin-plugins"></span>
					{{ data.requires.join(', ') }}
				</span>
			<# } #>

			<# if ( data.tags && data.tags.length ) { #>
				<span class="sndp-tags">
					<# _.each( data.tags.slice(0, 3), function( tag ) { #>
						<span class="sndp-tag">{{ tag }}</span>
					<# }); #>
				</span>
			<# } #>
		</div>

		<div class="sndp-snippet-footer">
			<span class="sndp-version">v{{ data.version }}</span>
			<div class="sndp-snippet-actions">
				<button type="button" class="button button-small sndp-view-code" data-snippet-id="{{ data.id }}" title="<?php esc_attr_e( 'View Code', 'snipdrop' ); ?>">
					<span class="dashicons dashicons-editor-code"></span>
				</button>
				<# if ( data.configurable && data.settings && data.settings.length ) { #>
					<button type="button" class="button button-small sndp-configure-snippet" data-snippet-id="{{ data.id }}" title="<?php esc_attr_e( 'Configure', 'snipdrop' ); ?>">
						<span class="dashicons dashicons-admin-generic"></span>
					</button>
				<# } #>
				<button type="button" class="button button-small sndp-copy-to-custom" data-snippet-id="{{ data.id }}" title="<?php esc_attr_e( 'Copy to My Snippets', 'snipdrop' ); ?>">
					<span class="dashicons dashicons-admin-page"></span>
				</button>
			</div>
		</div>
	</div>
</script>

<!-- Code Preview Modal -->
<div id="sndp-code-modal" class="sndp-modal">
	<div class="sndp-modal-content sndp-code-modal-content">
		<div class="sndp-modal-header">
			<h3 id="sndp-modal-title"><?php esc_html_e( 'Snippet Code', 'snipdrop' ); ?></h3>
			<button type="button" class="sndp-modal-close">&times;</button>
		</div>
		<div class="sndp-modal-body">
			<div id="sndp-code-error" class="sndp-code-error"></div>
			<div id="sndp-code-preview-wrapper">
				<div id="sndp-modal-description" class="sndp-modal-description"></div>
				<div class="sndp-modal-code-wrapper">
					<div class="sndp-code-header">
						<span class="sndp-code-lang">PHP</span>
						<button type="button" class="sndp-copy-code" title="<?php esc_attr_e( 'Copy to clipboard', 'snipdrop' ); ?>">
							<span class="dashicons dashicons-clipboard"></span>
							<?php esc_html_e( 'Copy', 'snipdrop' ); ?>
						</button>
					</div>
					<div class="sndp-code-block">
						<div class="sndp-line-numbers" id="sndp-line-numbers"></div>
						<pre id="sndp-modal-code"></pre>
					</div>
				</div>
				<div id="sndp-modal-credits" class="sndp-modal-credits">
					<p id="sndp-modal-author"></p>
					<p id="sndp-modal-source"></p>
				</div>
			</div>
		</div>
	</div>
</div>

<!-- Configure Modal -->
<div id="sndp-configure-modal" class="sndp-modal">
	<div class="sndp-modal-content sndp-configure-modal-content">
		<div class="sndp-modal-header">
			<h3 id="sndp-configure-modal-title"><?php esc_html_e( 'Configure Snippet', 'snipdrop' ); ?></h3>
			<button type="button" class="sndp-modal-close">&times;</button>
		</div>
		<div class="sndp-modal-body">
			<form id="sndp-configure-form">
				<input type="hidden" id="sndp-configure-snippet-id" name="snippet_id" value="">
				<div id="sndp-configure-fields">
					<!-- Dynamic fields will be inserted here -->
				</div>
				<div class="sndp-configure-actions">
					<button type="submit" class="button button-primary">
						<?php esc_html_e( 'Save Configuration', 'snipdrop' ); ?>
					</button>
					<button type="button" class="button sndp-configure-reset">
						<?php esc_html_e( 'Reset to Defaults', 'snipdrop' ); ?>
					</button>
				</div>
			</form>
		</div>
	</div>
</div>
