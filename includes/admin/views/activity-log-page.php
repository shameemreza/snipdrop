<?php
/**
 * Activity Log page template.
 *
 * @package SnipDrop
 * @since   1.0.0
 *
 * @var SNDP_Activity_Log $activity_log Activity log instance.
 * @var int               $log_count   Total number of log entries.
 * @var array             $log_result  Paginated log entries.
 * @var string[]          $log_types   Valid event types.
 */

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Template variables passed from class.

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$sndp_has_entries = ! empty( $log_result['entries'] );
?>
<div class="wrap sndp-admin-wrap sndp-al-wrap">

	<h1 class="wp-heading-inline"><?php esc_html_e( 'Activity Log', 'snipdrop' ); ?></h1>
	<hr class="wp-header-end">

	<div class="sndp-al-toolbar">
		<div class="sndp-al-toolbar-left">
			<select id="sndp-al-filter" class="sndp-al-filter">
				<option value=""><?php esc_html_e( 'All Events', 'snipdrop' ); ?></option>
				<?php foreach ( $log_types as $sndp_type ) : ?>
					<option value="<?php echo esc_attr( $sndp_type ); ?>">
						<?php echo esc_html( SNDP_Activity_Log::get_type_label( $sndp_type ) ); ?>
					</option>
				<?php endforeach; ?>
			</select>
			<?php if ( $log_count > 0 ) : ?>
				<span class="sndp-al-toolbar-count">
					<?php
					printf(
						/* translators: %s: number of events */
						esc_html__( '%s events', 'snipdrop' ),
						'<strong>' . absint( $log_count ) . '</strong>'
					);
					?>
				</span>
			<?php endif; ?>
		</div>
		<?php if ( $log_count > 0 ) : ?>
			<div class="sndp-al-toolbar-right">
				<button type="button" class="button sndp-al-clear" id="sndp-al-clear">
					<?php esc_html_e( 'Clear Log', 'snipdrop' ); ?>
				</button>
			</div>
		<?php endif; ?>
	</div>

	<div class="sndp-al-table-wrap" id="sndp-al-table-wrap">
		<?php if ( ! $sndp_has_entries ) : ?>
			<div class="sndp-al-empty">
				<span class="dashicons dashicons-clipboard"></span>
				<h3><?php esc_html_e( 'No activity yet', 'snipdrop' ); ?></h3>
				<p><?php esc_html_e( 'Events will appear here as you enable, disable, create, edit, or delete snippets. Errors and imports are also logged.', 'snipdrop' ); ?></p>
			</div>
		<?php else : ?>
			<table class="wp-list-table widefat fixed striped sndp-al-table" id="sndp-al-table">
				<thead>
					<tr>
						<th class="sndp-al-col-event" scope="col"><?php esc_html_e( 'Event', 'snipdrop' ); ?></th>
						<th class="sndp-al-col-details" scope="col"><?php esc_html_e( 'Details', 'snipdrop' ); ?></th>
						<th class="sndp-al-col-context" scope="col"><?php esc_html_e( 'Source', 'snipdrop' ); ?></th>
						<th class="sndp-al-col-user" scope="col"><?php esc_html_e( 'User', 'snipdrop' ); ?></th>
						<th class="sndp-al-col-time" scope="col"><?php esc_html_e( 'Time', 'snipdrop' ); ?></th>
					</tr>
				</thead>
				<tbody id="sndp-al-body">
					<?php foreach ( $log_result['entries'] as $sndp_entry ) : ?>
						<?php
						$sndp_al_user      = ! empty( $sndp_entry['user_id'] ) ? get_userdata( $sndp_entry['user_id'] ) : null;
						$sndp_al_user_name = $sndp_al_user ? $sndp_al_user->display_name : __( 'System', 'snipdrop' );
						$sndp_al_badge_cls = SNDP_Activity_Log::get_type_badge_class( $sndp_entry['type'] );
						$sndp_al_label     = SNDP_Activity_Log::get_type_label( $sndp_entry['type'] );
						$sndp_al_time_ago  = human_time_diff( strtotime( $sndp_entry['timestamp'] ), time() );
						$sndp_al_context   = ! empty( $sndp_entry['context'] ) ? ucfirst( $sndp_entry['context'] ) : '&mdash;';
						?>
						<tr>
							<td class="sndp-al-col-event">
								<span class="sndp-al-badge sndp-al-badge--<?php echo esc_attr( $sndp_al_badge_cls ); ?>">
									<?php echo esc_html( $sndp_al_label ); ?>
								</span>
							</td>
							<td class="sndp-al-col-details">
								<?php if ( ! empty( $sndp_entry['snippet_title'] ) ) : ?>
									<strong><?php echo esc_html( $sndp_entry['snippet_title'] ); ?></strong>
									<?php if ( ! empty( $sndp_entry['snippet_type'] ) ) : ?>
										<span class="sndp-al-type-tag"><?php echo esc_html( strtoupper( $sndp_entry['snippet_type'] ) ); ?></span>
									<?php endif; ?>
								<?php elseif ( ! empty( $sndp_entry['details'] ) ) : ?>
									<?php echo esc_html( $sndp_entry['details'] ); ?>
								<?php else : ?>
									&mdash;
								<?php endif; ?>
								<?php if ( ! empty( $sndp_entry['snippet_title'] ) && ! empty( $sndp_entry['details'] ) ) : ?>
									<div class="sndp-al-details"><?php echo esc_html( $sndp_entry['details'] ); ?></div>
								<?php endif; ?>
							</td>
							<td class="sndp-al-col-context">
								<?php echo esc_html( $sndp_al_context ); ?>
							</td>
							<td class="sndp-al-col-user">
								<?php if ( $sndp_al_user ) : ?>
									<?php echo get_avatar( $sndp_al_user->ID, 24, '', '', array( 'class' => 'sndp-al-avatar' ) ); ?>
								<?php endif; ?>
								<?php echo esc_html( $sndp_al_user_name ); ?>
							</td>
							<td class="sndp-al-col-time" title="<?php echo esc_attr( $sndp_entry['timestamp'] . ' UTC' ); ?>">
								<?php
								/* translators: %s: Human-readable time difference */
								printf( esc_html__( '%s ago', 'snipdrop' ), esc_html( $sndp_al_time_ago ) );
								?>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>

			<?php if ( $log_result['total'] > 20 ) : ?>
				<div class="sndp-al-footer" id="sndp-al-pagination">
					<span class="sndp-al-pagination-info">
						<?php
					printf(
						/* translators: 1: shown count, 2: total count */
						esc_html__( 'Showing %1$d of %2$d events', 'snipdrop' ),
						absint( min( 20, $log_result['total'] ) ),
						absint( $log_result['total'] )
					);
						?>
					</span>
					<button type="button" class="button button-secondary" id="sndp-al-load-more" data-page="1" data-total-pages="<?php echo absint( ceil( $log_result['total'] / 20 ) ); ?>">
						<?php esc_html_e( 'Load More', 'snipdrop' ); ?>
					</button>
				</div>
			<?php endif; ?>
		<?php endif; ?>
	</div>
</div>
