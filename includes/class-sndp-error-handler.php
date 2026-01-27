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

			// Record error based on snippet type.
			if ( 'custom' === $snippet_type ) {
				$custom_snippets = SNDP_Custom_Snippets::instance();
				$custom_snippets->record_error( $current, $error_data );
			} else {
				$snippets = SNDP_Snippets::instance();
				$snippets->record_snippet_error( $current, $error_data );
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
	 * @since 1.2.0
	 * @param string $snippet_id   Snippet ID.
	 * @param string $snippet_type Snippet type (library or custom).
	 * @param array  $error        Error data.
	 */
	public function log_error_to_file( $snippet_id, $snippet_type, $error ) {
		// Create log directory if it doesn't exist.
		if ( ! file_exists( $this->log_dir ) ) {
			wp_mkdir_p( $this->log_dir );

			// Add index.php for security.
			$index_file = $this->log_dir . '/index.php';
			if ( ! file_exists( $index_file ) ) {
				// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
				file_put_contents( $index_file, '<?php // Silence is golden.' );
			}

			// Add .htaccess to prevent direct access.
			$htaccess_file = $this->log_dir . '/.htaccess';
			if ( ! file_exists( $htaccess_file ) ) {
				// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
				file_put_contents( $htaccess_file, 'Deny from all' );
			}
		}

		// Create log entry.
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

		// Write to daily log file.
		$log_file = $this->log_dir . '/error-' . gmdate( 'Y-m-d' ) . '.log';
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
		file_put_contents( $log_file, $log_entry, FILE_APPEND | LOCK_EX );
	}

	/**
	 * Get log directory path.
	 *
	 * @since 1.2.0
	 * @return string
	 */
	public function get_log_dir() {
		return $this->log_dir;
	}

	/**
	 * Get recent log entries.
	 *
	 * @since 1.2.0
	 * @param int $days Number of days to retrieve logs for.
	 * @return array Log entries.
	 */
	public function get_recent_logs( $days = 7 ) {
		$logs = array();

		if ( ! file_exists( $this->log_dir ) ) {
			return $logs;
		}

		for ( $i = 0; $i < $days; $i++ ) {
			$date     = gmdate( 'Y-m-d', strtotime( "-{$i} days" ) );
			$log_file = $this->log_dir . '/error-' . $date . '.log';

			if ( file_exists( $log_file ) ) {
				// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
				$content = file_get_contents( $log_file );
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
	 * @since 1.2.0
	 * @param int $days Keep logs newer than this many days.
	 */
	public function clear_old_logs( $days = 30 ) {
		if ( ! file_exists( $this->log_dir ) ) {
			return;
		}

		$cutoff = strtotime( "-{$days} days" );
		$files  = glob( $this->log_dir . '/error-*.log' );

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
	 * @since 1.2.0
	 * @return array Error history.
	 */
	public function get_error_history() {
		return get_option( 'sndp_error_history', array() );
	}

	/**
	 * Add error to history.
	 *
	 * @since 1.2.0
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
	 * @since 1.2.0
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
	 * @since 1.2.0
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
}
