<?php
/**
 * Error handler class.
 *
 * @package SnipDrop
 * @since   1.0.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * SNDP_Error_Handler class.
 *
 * Handles PHP errors and auto-disables problematic snippets.
 *
 * @since 1.0.0
 */
class SNDP_Error_Handler {

	/**
	 * Single instance.
	 *
	 * @var SNDP_Error_Handler
	 */
	private static $instance = null;

	/**
	 * Previous exception handler.
	 *
	 * @var callable|null
	 */
	private $previous_exception_handler = null;

	/**
	 * Log file directory.
	 *
	 * @var string
	 */
	private $log_dir;

	/**
	 * Maximum errors to keep in history per snippet.
	 *
	 * @var int
	 */
	private $max_error_history = 10;

	/**
	 * Get instance.
	 *
	 * @since 1.0.0
	 * @return SNDP_Error_Handler
	 */
	public static function instance() {
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 */
	private function __construct() {
		// Set up log directory.
		$this->log_dir = WP_CONTENT_DIR . '/snipdrop-logs';

		// Register shutdown handler for fatal errors.
		add_action( 'shutdown', array( $this, 'handle_shutdown' ) );

		// Hook into WordPress error handler.
		add_filter( 'wp_php_error_args', array( $this, 'handle_wp_error' ), 1, 2 );

		// Set exception handler.
		$this->previous_exception_handler = set_exception_handler( array( $this, 'handle_exception' ) );
	}

	/**
	 * Handle shutdown - catch fatal errors.
	 *
	 * @since 1.0.0
	 */
	public function handle_shutdown() {
		$error = error_get_last();

		// Only handle fatal errors.
		if ( $error && in_array( $error['type'], array( E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR ), true ) ) {
			$this->maybe_disable_snippet( $error );
		}
	}

	/**
	 * Handle WordPress PHP error filter.
	 *
	 * @since 1.0.0
	 * @param array $args  Error display arguments.
	 * @param array $error Error information.
	 * @return array
	 */
	public function handle_wp_error( $args, $error ) {
		if ( empty( $args['response'] ) || 500 !== $args['response'] ) {
			return $args;
		}

		$this->maybe_disable_snippet( $error );

		return $args;
	}

	/**
	 * Handle exceptions.
	 *
	 * @since 1.0.0
	 * @param \Throwable $exception The exception.
	 * @throws \Throwable Re-throws the exception if no previous handler exists.
	 */
	public function handle_exception( $exception ) {
		$error = array(
			'type'    => E_ERROR,
			'message' => $exception->getMessage(),
			'file'    => $exception->getFile(),
			'line'    => $exception->getLine(),
		);

		$this->maybe_disable_snippet( $error );

		// Call previous handler.
		if ( is_callable( $this->previous_exception_handler ) ) {
			call_user_func( $this->previous_exception_handler, $exception );
		} else {
			throw $exception;
		}
	}

	/**
	 * Check if error is from our snippet and disable if so.
	 *
	 * @since 1.0.0
	 * @param array $error Error details.
	 */
	private function maybe_disable_snippet( $error ) {
		if ( empty( $error['file'] ) ) {
			return;
		}

		// Check if this is an eval'd code error (our snippets use eval).
		$executor     = SNDP_Executor::instance();
		$current      = $executor->get_current_snippet();
		$snippet_type = $executor->current_snippet_type;

		if ( ! empty( $current ) ) {
			// Get snippet title for logging.
			$title = $current;
			if ( 'custom' === $snippet_type ) {
				$snippet = SNDP_Custom_Snippets::instance()->get( $current );
				if ( $snippet ) {
					$title = isset( $snippet['title'] ) ? $snippet['title'] : $current;
				}
			}

			$error_data = array(
				'message' => isset( $error['message'] ) ? $error['message'] : __( 'Unknown error', 'snipdrop' ),
				'line'    => isset( $error['line'] ) ? $error['line'] : 0,
				'file'    => isset( $error['file'] ) ? $error['file'] : '',
				'title'   => $title,
			);

			$sndp_settings  = SNDP_Snippets::instance()->get_settings();
			$should_disable = ! isset( $sndp_settings['auto_disable_errors'] ) || $sndp_settings['auto_disable_errors'];

			if ( $should_disable ) {
				if ( 'custom' === $snippet_type ) {
					$custom_snippets = SNDP_Custom_Snippets::instance();
					$custom_snippets->record_error( $current, $error_data );
				} else {
					$snippets = SNDP_Snippets::instance();
					$snippets->record_snippet_error( $current, $error_data );
				}
			}

			// Log error to file.
			$this->log_error_to_file( $current, $snippet_type, $error_data );

			// Add to error history.
			$this->add_to_error_history( $current, $snippet_type, $error_data );

			// Set transient to show admin notice.
			set_transient(
				'sndp_error_notice',
				array(
					'snippet_id'   => $current,
					'snippet_type' => $snippet_type,
					'message'      => isset( $error['message'] ) ? $error['message'] : __( 'Unknown error', 'snipdrop' ),
				),
				HOUR_IN_SECONDS
			);

			// Send email notification if enabled.
			if ( $should_disable ) {
				$this->send_error_email( $current, $snippet_type, $error_data );
			}
		}
	}

	/**
	 * Check if safe mode should be suggested.
	 *
	 * @since 1.0.0
	 * @return bool
	 */
	public function should_suggest_safe_mode() {
		$snippets = SNDP_Snippets::instance();
		return $snippets->get_error_count() > 0;
	}

	/**
	 * Get error notice transient.
	 *
	 * @since 1.0.0
	 * @return array|false
	 */
	public function get_error_notice() {
		return get_transient( 'sndp_error_notice' );
	}

	/**
	 * Clear error notice transient.
	 *
	 * @since 1.0.0
	 */
	public function clear_error_notice() {
		delete_transient( 'sndp_error_notice' );
	}

	/**
	 * Log error to file.
	 *
	 * @since 1.0.0
	 * @param string $snippet_id   Snippet ID.
	 * @param string $snippet_type Snippet type (library or custom).
	 * @param array  $error        Error data.
	 */
	public function log_error_to_file( $snippet_id, $snippet_type, $error ) {
		global $wp_filesystem;

		if ( ! function_exists( 'WP_Filesystem' ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
		}

		if ( ! WP_Filesystem() ) {
			return;
		}

		if ( ! $wp_filesystem->is_dir( $this->log_dir ) ) {
			wp_mkdir_p( $this->log_dir );

			$wp_filesystem->put_contents(
				$this->log_dir . '/index.php',
				'<?php // Silence is golden.',
				FS_CHMOD_FILE
			);

			$wp_filesystem->put_contents(
				$this->log_dir . '/.htaccess',
				'Deny from all',
				FS_CHMOD_FILE
			);
		}

		$log_entry = sprintf(
			"[%s] [%s] [%s:%s]\nMessage: %s\nFile: %s\nLine: %d\n%s\n",
			gmdate( 'Y-m-d H:i:s' ),
			strtoupper( $snippet_type ),
			$snippet_id,
			isset( $error['title'] ) ? $error['title'] : 'Unknown',
			isset( $error['message'] ) ? $error['message'] : 'Unknown error',
			isset( $error['file'] ) ? $error['file'] : 'N/A',
			isset( $error['line'] ) ? $error['line'] : 0,
			str_repeat( '-', 80 )
		);

		$log_file = $this->log_dir . '/error-' . gmdate( 'Y-m-d' ) . '.log';
		$existing = $wp_filesystem->exists( $log_file ) ? $wp_filesystem->get_contents( $log_file ) : '';
		$wp_filesystem->put_contents( $log_file, $existing . $log_entry, FS_CHMOD_FILE );
	}

	/**
	 * Get log directory path.
	 *
	 * @since 1.0.0
	 * @return string
	 */
	public function get_log_dir() {
		return $this->log_dir;
	}

	/**
	 * Get recent log entries.
	 *
	 * @since 1.0.0
	 * @param int $days Number of days to retrieve logs for.
	 * @return array Log entries.
	 */
	public function get_recent_logs( $days = 7 ) {
		global $wp_filesystem;

		$logs = array();

		if ( ! function_exists( 'WP_Filesystem' ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
		}

		if ( ! WP_Filesystem() || ! $wp_filesystem->is_dir( $this->log_dir ) ) {
			return $logs;
		}

		for ( $i = 0; $i < $days; $i++ ) {
			$date     = gmdate( 'Y-m-d', strtotime( "-{$i} days" ) );
			$log_file = $this->log_dir . '/error-' . $date . '.log';

			if ( $wp_filesystem->exists( $log_file ) ) {
				$content = $wp_filesystem->get_contents( $log_file );
				if ( $content ) {
					$logs[ $date ] = $content;
				}
			}
		}

		return $logs;
	}

	/**
	 * Clear old log files.
	 *
	 * @since 1.0.0
	 * @param int $days Keep logs newer than this many days.
	 */
	public function clear_old_logs( $days = 30 ) {
		if ( ! file_exists( $this->log_dir ) ) {
			return;
		}

		$cutoff = strtotime( "-{$days} days" );
		$files  = glob( $this->log_dir . '/error-*.log' );

		if ( ! is_array( $files ) ) {
			return;
		}

		foreach ( $files as $file ) {
			// Extract date from filename.
			if ( preg_match( '/error-(\d{4}-\d{2}-\d{2})\.log$/', $file, $matches ) ) {
				$file_date = strtotime( $matches[1] );
				if ( $file_date < $cutoff ) {
					wp_delete_file( $file );
				}
			}
		}
	}

	/**
	 * Get error history for all snippets.
	 *
	 * @since 1.0.0
	 * @return array Error history.
	 */
	public function get_error_history() {
		return get_option( 'sndp_error_history', array() );
	}

	/**
	 * Add error to history.
	 *
	 * @since 1.0.0
	 * @param string $snippet_id   Snippet ID.
	 * @param string $snippet_type Snippet type.
	 * @param array  $error        Error data.
	 */
	public function add_to_error_history( $snippet_id, $snippet_type, $error ) {
		$history = $this->get_error_history();

		$key = $snippet_type . ':' . $snippet_id;

		if ( ! isset( $history[ $key ] ) ) {
			$history[ $key ] = array();
		}

		// Add timestamp to error.
		$error['timestamp'] = time();

		// Add to beginning of array.
		array_unshift( $history[ $key ], $error );

		// Keep only the last N errors.
		$history[ $key ] = array_slice( $history[ $key ], 0, $this->max_error_history );

		update_option( 'sndp_error_history', $history, false );
	}

	/**
	 * Get error history for a specific snippet.
	 *
	 * @since 1.0.0
	 * @param string $snippet_id   Snippet ID.
	 * @param string $snippet_type Snippet type.
	 * @return array Error history for the snippet.
	 */
	public function get_snippet_error_history( $snippet_id, $snippet_type ) {
		$history = $this->get_error_history();
		$key     = $snippet_type . ':' . $snippet_id;

		return isset( $history[ $key ] ) ? $history[ $key ] : array();
	}

	/**
	 * Clear error history for a snippet.
	 *
	 * @since 1.0.0
	 * @param string $snippet_id   Snippet ID.
	 * @param string $snippet_type Snippet type.
	 */
	public function clear_snippet_error_history( $snippet_id, $snippet_type ) {
		$history = $this->get_error_history();
		$key     = $snippet_type . ':' . $snippet_id;

		if ( isset( $history[ $key ] ) ) {
			unset( $history[ $key ] );
			update_option( 'sndp_error_history', $history, false );
		}
	}

	/**
	 * Send email notification when a snippet is auto-disabled.
	 *
	 * Rate-limited to one email per 15 minutes to prevent flooding.
	 *
	 * @since 1.0.0
	 * @param string $snippet_id   Snippet ID.
	 * @param string $snippet_type Snippet type (library or custom).
	 * @param array  $error        Error data with 'message', 'line', 'file', 'title' keys.
	 */
	private function send_error_email( $snippet_id, $snippet_type, $error ) {
		$settings = get_option( 'sndp_settings', array() );

		// Check if email notifications are enabled (default: on).
		if ( isset( $settings['email_notifications'] ) && ! $settings['email_notifications'] ) {
			return;
		}

		// Rate limit: one email per 15 minutes.
		if ( get_transient( 'sndp_last_error_email' ) ) {
			return;
		}

		$to = ! empty( $settings['notification_email'] ) ? $settings['notification_email'] : get_option( 'admin_email' );
		if ( empty( $to ) || ! is_email( $to ) ) {
			return;
		}

		$title   = isset( $error['title'] ) ? $error['title'] : $snippet_id;
		$message = isset( $error['message'] ) ? $error['message'] : __( 'Unknown error', 'snipdrop' );
		$line    = isset( $error['line'] ) ? absint( $error['line'] ) : 0;
		$time    = gmdate( 'Y-m-d H:i:s' ) . ' UTC';

		$site_name    = wp_specialchars_decode( get_option( 'blogname' ), ENT_QUOTES );
		$settings_url = admin_url( 'admin.php?page=snipdrop-settings' );
		$snippets_url = 'custom' === $snippet_type
			? admin_url( 'admin.php?page=snipdrop-custom' )
			: admin_url( 'admin.php?page=snipdrop' );

		/* translators: 1: Snippet title, 2: Site name */
		$subject = sprintf( __( '[%2$s] SnipDrop: Snippet "%1$s" was auto-disabled', 'snipdrop' ), $title, $site_name );

		$body  = '<div style="font-family:-apple-system,BlinkMacSystemFont,\'Segoe UI\',Roboto,sans-serif;max-width:600px;margin:0 auto;">';
		$body .= '<h2 style="color:#1d2327;font-size:18px;margin:0 0 16px;">' . esc_html__( 'Snippet Auto-Disabled', 'snipdrop' ) . '</h2>';
		$body .= '<p style="color:#50575e;font-size:14px;line-height:1.6;margin:0 0 16px;">';
		$body .= esc_html__( 'A snippet on your site caused a PHP error and has been automatically disabled to protect your site.', 'snipdrop' );
		$body .= '</p>';

		$body .= '<table style="width:100%;border-collapse:collapse;margin:0 0 20px;">';
		$body .= '<tr><td style="padding:8px 12px;border:1px solid #dcdcde;font-weight:600;background:#f6f7f7;width:120px;">' . esc_html__( 'Snippet', 'snipdrop' ) . '</td>';
		$body .= '<td style="padding:8px 12px;border:1px solid #dcdcde;">' . esc_html( $title ) . ' <span style="color:#787c82;">(' . esc_html( $snippet_type ) . ')</span></td></tr>';
		$body .= '<tr><td style="padding:8px 12px;border:1px solid #dcdcde;font-weight:600;background:#f6f7f7;">' . esc_html__( 'Error', 'snipdrop' ) . '</td>';
		$body .= '<td style="padding:8px 12px;border:1px solid #dcdcde;color:#d63638;">' . esc_html( $message ) . '</td></tr>';

		if ( $line > 0 ) {
			$body .= '<tr><td style="padding:8px 12px;border:1px solid #dcdcde;font-weight:600;background:#f6f7f7;">' . esc_html__( 'Line', 'snipdrop' ) . '</td>';
			$body .= '<td style="padding:8px 12px;border:1px solid #dcdcde;">' . $line . '</td></tr>';
		}

		$body .= '<tr><td style="padding:8px 12px;border:1px solid #dcdcde;font-weight:600;background:#f6f7f7;">' . esc_html__( 'Time', 'snipdrop' ) . '</td>';
		$body .= '<td style="padding:8px 12px;border:1px solid #dcdcde;">' . esc_html( $time ) . '</td></tr>';
		$body .= '</table>';

		$body .= '<p style="margin:0 0 8px;">';
		$body .= '<a href="' . esc_url( $snippets_url ) . '" style="display:inline-block;background:#2271b1;color:#fff;padding:8px 16px;border-radius:3px;text-decoration:none;font-size:13px;">';
		$body .= esc_html__( 'View Snippets', 'snipdrop' ) . '</a></p>';
		$body .= '<p style="color:#787c82;font-size:12px;margin:16px 0 0;">';
		/* translators: %s: Settings page URL */
		$body .= sprintf( esc_html__( 'You can manage email notifications in %s.', 'snipdrop' ), '<a href="' . esc_url( $settings_url ) . '">' . esc_html__( 'SnipDrop Settings', 'snipdrop' ) . '</a>' );
		$body .= '</p></div>';

		$headers = array( 'Content-Type: text/html; charset=UTF-8' );

		wp_mail( $to, $subject, $body, $headers );

		// Set rate-limit transient (15 minutes).
		set_transient( 'sndp_last_error_email', time(), 15 * MINUTE_IN_SECONDS );
	}
}
