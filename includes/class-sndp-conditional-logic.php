<?php
/**
 * Conditional Logic Engine.
 *
 * Evaluates rule-based conditions with AND/OR groups at runtime.
 * Supports user state, content targeting, URL matching, scheduling,
 * device detection, and WooCommerce page conditions.
 *
 * @package SnipDrop
 * @since   1.0.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * SNDP_Conditional_Logic class.
 */
class SNDP_Conditional_Logic {

	/**
	 * Singleton instance.
	 *
	 * @var SNDP_Conditional_Logic|null
	 */
	private static $instance = null;

	/**
	 * Cached evaluation results for current request.
	 *
	 * @var array<string, bool>
	 */
	private $cache = array();

	/**
	 * Registered condition type definitions.
	 *
	 * @var array<string, array>
	 */
	private $condition_types = array();

	/**
	 * Get singleton instance.
	 *
	 * @return SNDP_Conditional_Logic
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor.
	 */
	private function __construct() {
		$this->register_condition_types();
	}

	/**
	 * Register all available condition types with their metadata.
	 */
	private function register_condition_types() {
		$this->condition_types = array(
			'user_logged_in'  => array(
				'label'     => __( 'Login Status', 'snipdrop' ),
				'category'  => 'user',
				'operators' => array( 'is' ),
				'values'    => array(
					'yes' => __( 'Logged In', 'snipdrop' ),
					'no'  => __( 'Logged Out', 'snipdrop' ),
				),
			),
			'user_role'       => array(
				'label'     => __( 'User Role', 'snipdrop' ),
				'category'  => 'user',
				'operators' => array( 'is', 'is_not' ),
				'values'    => 'dynamic_roles',
			),
			'post_type'       => array(
				'label'     => __( 'Post Type', 'snipdrop' ),
				'category'  => 'content',
				'operators' => array( 'is', 'is_not' ),
				'values'    => 'dynamic_post_types',
			),
			'page'            => array(
				'label'     => __( 'Specific Page/Post', 'snipdrop' ),
				'category'  => 'content',
				'operators' => array( 'is', 'is_not' ),
				'values'    => 'dynamic_pages',
			),
			'taxonomy_term'   => array(
				'label'     => __( 'Taxonomy Term', 'snipdrop' ),
				'category'  => 'content',
				'operators' => array( 'is', 'is_not' ),
				'values'    => 'dynamic_taxonomy',
			),
			'url_contains'    => array(
				'label'     => __( 'URL Contains', 'snipdrop' ),
				'category'  => 'url',
				'operators' => array( 'contains', 'not_contains' ),
				'values'    => 'text',
			),
			'url_is'          => array(
				'label'     => __( 'URL Is', 'snipdrop' ),
				'category'  => 'url',
				'operators' => array( 'is', 'is_not' ),
				'values'    => 'text',
			),
			'url_starts_with' => array(
				'label'     => __( 'URL Starts With', 'snipdrop' ),
				'category'  => 'url',
				'operators' => array( 'is', 'is_not' ),
				'values'    => 'text',
			),
			'schedule'        => array(
				'label'     => __( 'Date Range', 'snipdrop' ),
				'category'  => 'time',
				'operators' => array( 'between' ),
				'values'    => 'date_range',
			),
			'day_of_week'     => array(
				'label'     => __( 'Day of Week', 'snipdrop' ),
				'category'  => 'time',
				'operators' => array( 'is', 'is_not' ),
				'values'    => array(
					'1' => __( 'Monday', 'snipdrop' ),
					'2' => __( 'Tuesday', 'snipdrop' ),
					'3' => __( 'Wednesday', 'snipdrop' ),
					'4' => __( 'Thursday', 'snipdrop' ),
					'5' => __( 'Friday', 'snipdrop' ),
					'6' => __( 'Saturday', 'snipdrop' ),
					'0' => __( 'Sunday', 'snipdrop' ),
				),
			),
			'device_type'     => array(
				'label'     => __( 'Device Type', 'snipdrop' ),
				'category'  => 'device',
				'operators' => array( 'is' ),
				'values'    => array(
					'mobile'  => __( 'Mobile', 'snipdrop' ),
					'desktop' => __( 'Desktop', 'snipdrop' ),
				),
			),
		);

		if ( class_exists( 'WooCommerce' ) ) {
			$this->condition_types['wc_page'] = array(
				'label'     => __( 'WooCommerce Page', 'snipdrop' ),
				'category'  => 'woocommerce',
				'operators' => array( 'is', 'is_not' ),
				'values'    => array(
					'shop'             => __( 'Shop', 'snipdrop' ),
					'cart'             => __( 'Cart', 'snipdrop' ),
					'checkout'         => __( 'Checkout', 'snipdrop' ),
					'product'          => __( 'Single Product', 'snipdrop' ),
					'product_cat'      => __( 'Product Category Archive', 'snipdrop' ),
					'product_tag'      => __( 'Product Tag Archive', 'snipdrop' ),
					'account'          => __( 'My Account', 'snipdrop' ),
					'order_received'   => __( 'Order Received / Thank You', 'snipdrop' ),
					'order_pay'        => __( 'Order Pay', 'snipdrop' ),
					'view_order'       => __( 'View Order', 'snipdrop' ),
					'edit_account'     => __( 'Edit Account', 'snipdrop' ),
					'edit_address'     => __( 'Edit Address', 'snipdrop' ),
					'lost_password'        => __( 'Lost Password', 'snipdrop' ),
					'payment_methods'      => __( 'Payment Methods', 'snipdrop' ),
					'add_payment_method'   => __( 'Add Payment Method', 'snipdrop' ),
					'downloads'            => __( 'Downloads', 'snipdrop' ),
					'orders'               => __( 'Orders', 'snipdrop' ),
				),
			);

			$this->condition_types['wc_product'] = array(
				'label'     => __( 'Specific Product', 'snipdrop' ),
				'category'  => 'woocommerce',
				'operators' => array( 'is', 'is_not' ),
				'values'    => 'dynamic_wc_products',
			);

			$this->condition_types['wc_product_category'] = array(
				'label'     => __( 'Product Category', 'snipdrop' ),
				'category'  => 'woocommerce',
				'operators' => array( 'is', 'is_not' ),
				'values'    => 'dynamic_wc_categories',
			);

			$this->condition_types['wc_product_tag'] = array(
				'label'     => __( 'Product Tag', 'snipdrop' ),
				'category'  => 'woocommerce',
				'operators' => array( 'is', 'is_not' ),
				'values'    => 'dynamic_wc_tags',
			);

			$this->condition_types['wc_cart_empty'] = array(
				'label'     => __( 'Cart Empty', 'snipdrop' ),
				'category'  => 'woocommerce',
				'operators' => array( 'is' ),
				'values'    => array(
					'yes' => __( 'Empty', 'snipdrop' ),
					'no'  => __( 'Not Empty', 'snipdrop' ),
				),
			);

			$this->condition_types['wc_cart_total'] = array(
				'label'     => __( 'Cart Total', 'snipdrop' ),
				'category'  => 'woocommerce',
				'operators' => array( 'greater_than', 'less_than' ),
				'values'    => 'number',
			);

			$this->condition_types['wc_cart_contains'] = array(
				'label'     => __( 'Cart Contains Product', 'snipdrop' ),
				'category'  => 'woocommerce',
				'operators' => array( 'is', 'is_not' ),
				'values'    => 'dynamic_wc_products',
			);
		}
	}

	/**
	 * Get all registered condition types for the UI.
	 *
	 * @return array
	 */
	public function get_condition_types() {
		$types = array();

		foreach ( $this->condition_types as $key => $type ) {
			$entry = array(
				'key'       => $key,
				'label'     => $type['label'],
				'category'  => $type['category'],
				'operators' => $this->get_operator_labels( $type['operators'] ),
			);

			if ( is_array( $type['values'] ) ) {
				$entry['values'] = $type['values'];
			} else {
				$entry['valueType'] = $type['values'];
			}

			$types[] = $entry;
		}

		return $types;
	}

	/**
	 * Get condition type categories for the UI picker.
	 *
	 * @return array<string, string>
	 */
	public function get_categories() {
		$categories = array(
			'user'    => __( 'User', 'snipdrop' ),
			'content' => __( 'Content', 'snipdrop' ),
			'url'     => __( 'URL', 'snipdrop' ),
			'time'    => __( 'Time & Date', 'snipdrop' ),
			'device'  => __( 'Device', 'snipdrop' ),
		);

		if ( class_exists( 'WooCommerce' ) ) {
			$categories['woocommerce'] = __( 'WooCommerce', 'snipdrop' );
		}

		return $categories;
	}

	/**
	 * Get operator labels.
	 *
	 * @param array $operators Operator keys.
	 * @return array<string, string>
	 */
	private function get_operator_labels( $operators ) {
		$labels = array(
			'is'             => __( 'is', 'snipdrop' ),
			'is_not'         => __( 'is not', 'snipdrop' ),
			'contains'       => __( 'contains', 'snipdrop' ),
			'not_contains'   => __( 'does not contain', 'snipdrop' ),
			'between'        => __( 'is between', 'snipdrop' ),
			'greater_than'   => __( 'is greater than', 'snipdrop' ),
			'less_than'      => __( 'is less than', 'snipdrop' ),
		);

		$result = array();
		foreach ( $operators as $op ) {
			if ( isset( $labels[ $op ] ) ) {
				$result[ $op ] = $labels[ $op ];
			}
		}
		return $result;
	}

	/**
	 * Evaluate conditional rules for a snippet.
	 *
	 * @param array $rules The conditional_rules array from snippet data.
	 * @return bool Whether the snippet should run.
	 */
	public function evaluate( $rules ) {
		if ( empty( $rules ) || empty( $rules['enabled'] ) ) {
			return true;
		}

		if ( empty( $rules['groups'] ) || ! is_array( $rules['groups'] ) ) {
			return true;
		}

		$cache_key = md5( wp_json_encode( $rules ) );
		if ( isset( $this->cache[ $cache_key ] ) ) {
			return $this->cache[ $cache_key ];
		}

		$global_match = isset( $rules['match'] ) ? $rules['match'] : 'all';
		$group_results = array();

		foreach ( $rules['groups'] as $group ) {
			$group_results[] = $this->evaluate_group( $group );
		}

		if ( 'all' === $global_match ) {
			$result = ! in_array( false, $group_results, true );
		} else {
			$result = in_array( true, $group_results, true );
		}

		$this->cache[ $cache_key ] = $result;
		return $result;
	}

	/**
	 * Evaluate a single rule group.
	 *
	 * @param array $group Group with match type and rules.
	 * @return bool
	 */
	private function evaluate_group( $group ) {
		if ( empty( $group['rules'] ) || ! is_array( $group['rules'] ) ) {
			return true;
		}

		$match = isset( $group['match'] ) ? $group['match'] : 'all';
		$results = array();

		foreach ( $group['rules'] as $rule ) {
			$results[] = $this->evaluate_rule( $rule );
		}

		if ( 'all' === $match ) {
			return ! in_array( false, $results, true );
		}

		return in_array( true, $results, true );
	}

	/**
	 * Evaluate a single condition rule.
	 *
	 * @param array $rule Rule with type, operator, and value.
	 * @return bool
	 */
	private function evaluate_rule( $rule ) {
		if ( empty( $rule['type'] ) ) {
			return true;
		}

		$type     = $rule['type'];
		$operator = isset( $rule['operator'] ) ? $rule['operator'] : 'is';
		$value    = isset( $rule['value'] ) ? $rule['value'] : '';

		switch ( $type ) {
			case 'user_logged_in':
				return $this->eval_user_logged_in( $operator, $value );
			case 'user_role':
				return $this->eval_user_role( $operator, $value );
			case 'post_type':
				return $this->eval_post_type( $operator, $value );
			case 'page':
				return $this->eval_page( $operator, $value );
			case 'taxonomy_term':
				return $this->eval_taxonomy_term( $operator, $value );
			case 'url_contains':
				return $this->eval_url_contains( $operator, $value );
			case 'url_is':
				return $this->eval_url_is( $operator, $value );
			case 'url_starts_with':
				return $this->eval_url_starts_with( $operator, $value );
			case 'schedule':
				return $this->eval_schedule( $operator, $value );
			case 'day_of_week':
				return $this->eval_day_of_week( $operator, $value );
			case 'device_type':
				return $this->eval_device_type( $operator, $value );
			case 'wc_page':
				return $this->eval_wc_page( $operator, $value );
			case 'wc_product':
				return $this->eval_wc_product( $operator, $value );
			case 'wc_product_category':
				return $this->eval_wc_product_category( $operator, $value );
			case 'wc_product_tag':
				return $this->eval_wc_product_tag( $operator, $value );
			case 'wc_cart_empty':
				return $this->eval_wc_cart_empty( $operator, $value );
			case 'wc_cart_total':
				return $this->eval_wc_cart_total( $operator, $value );
			case 'wc_cart_contains':
				return $this->eval_wc_cart_contains( $operator, $value );
			default:
				return true;
		}
	}

	/**
	 * Evaluate login status condition.
	 *
	 * @param string $operator Operator.
	 * @param string $value    Expected value (yes/no).
	 * @return bool
	 */
	private function eval_user_logged_in( $operator, $value ) {
		$logged_in = is_user_logged_in();
		$expected  = ( 'yes' === $value );
		return ( $logged_in === $expected );
	}

	/**
	 * Evaluate user role condition.
	 *
	 * @param string       $operator Operator (is/is_not).
	 * @param string|array $value    Role slug(s).
	 * @return bool
	 */
	private function eval_user_role( $operator, $value ) {
		if ( ! is_user_logged_in() ) {
			return ( 'is_not' === $operator );
		}

		$user  = wp_get_current_user();
		$roles = (array) $value;
		$match = ! empty( array_intersect( $user->roles, $roles ) );

		return ( 'is' === $operator ) ? $match : ! $match;
	}

	/**
	 * Evaluate post type condition.
	 *
	 * @param string       $operator Operator (is/is_not).
	 * @param string|array $value    Post type slug(s).
	 * @return bool
	 */
	private function eval_post_type( $operator, $value ) {
		if ( ! is_singular() ) {
			return ( 'is_not' === $operator );
		}

		$current = get_post_type();
		$types   = (array) $value;
		$match   = in_array( $current, $types, true );

		return ( 'is' === $operator ) ? $match : ! $match;
	}

	/**
	 * Evaluate specific page/post condition.
	 *
	 * @param string       $operator Operator (is/is_not).
	 * @param string|array $value    Post ID(s).
	 * @return bool
	 */
	private function eval_page( $operator, $value ) {
		if ( ! is_singular() ) {
			return ( 'is_not' === $operator );
		}

		$current_id = get_queried_object_id();
		$ids        = array_map( 'absint', (array) $value );
		$match      = in_array( $current_id, $ids, true );

		return ( 'is' === $operator ) ? $match : ! $match;
	}

	/**
	 * Evaluate taxonomy term condition.
	 *
	 * @param string       $operator Operator (is/is_not).
	 * @param string|array $value    Values in "taxonomy:slug" format.
	 * @return bool
	 */
	private function eval_taxonomy_term( $operator, $value ) {
		if ( ! is_singular() ) {
			return ( 'is_not' === $operator );
		}

		$post_id = get_queried_object_id();
		$terms   = (array) $value;
		$match   = false;

		foreach ( $terms as $term_pair ) {
			$parts = explode( ':', $term_pair, 2 );
			if ( 2 !== count( $parts ) ) {
				continue;
			}
			if ( has_term( $parts[1], $parts[0], $post_id ) ) {
				$match = true;
				break;
			}
		}

		return ( 'is' === $operator ) ? $match : ! $match;
	}

	/**
	 * Evaluate URL contains condition.
	 *
	 * @param string $operator Operator (contains/not_contains).
	 * @param string $value    Substring to match.
	 * @return bool
	 */
	private function eval_url_contains( $operator, $value ) {
		$current_url = $this->get_current_url();
		$match       = ( false !== strpos( $current_url, $value ) );
		return ( 'contains' === $operator ) ? $match : ! $match;
	}

	/**
	 * Evaluate URL is condition.
	 *
	 * @param string $operator Operator (is/is_not).
	 * @param string $value    Exact URL path to match.
	 * @return bool
	 */
	private function eval_url_is( $operator, $value ) {
		$path  = $this->get_current_path();
		$match = ( rtrim( $path, '/' ) === rtrim( $value, '/' ) );
		return ( 'is' === $operator ) ? $match : ! $match;
	}

	/**
	 * Evaluate URL starts with condition.
	 *
	 * @param string $operator Operator (is/is_not).
	 * @param string $value    Prefix to match.
	 * @return bool
	 */
	private function eval_url_starts_with( $operator, $value ) {
		$path  = $this->get_current_path();
		$match = ( 0 === strpos( $path, $value ) );
		return ( 'is' === $operator ) ? $match : ! $match;
	}

	/**
	 * Evaluate date range schedule condition.
	 *
	 * @param string $operator Operator (between).
	 * @param array  $value    Array with 'start' and 'end' datetime strings.
	 * @return bool
	 */
	private function eval_schedule( $operator, $value ) {
		if ( empty( $value ) || ! is_array( $value ) ) {
			return true;
		}

		$start = isset( $value['start'] ) ? trim( $value['start'] ) : '';
		$end   = isset( $value['end'] ) ? trim( $value['end'] ) : '';

		if ( '' === $start && '' === $end ) {
			return true;
		}

		$now = current_time( 'timestamp' ); // phpcs:ignore WordPress.DateTime.CurrentTimeTimestamp.Requested

		if ( '' !== $start ) {
			$start_ts = strtotime( $start, $now );
			if ( $start_ts && $now < $start_ts ) {
				return false;
			}
		}

		if ( '' !== $end ) {
			$end_ts = strtotime( $end, $now );
			if ( $end_ts && $now > $end_ts ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Evaluate day of week condition.
	 *
	 * @param string       $operator Operator (is/is_not).
	 * @param string|array $value    Day number(s) (0=Sunday, 1=Monday, ... 6=Saturday).
	 * @return bool
	 */
	private function eval_day_of_week( $operator, $value ) {
		$today = (string) current_time( 'w' );
		$days  = (array) $value;
		$match = in_array( $today, $days, true );

		return ( 'is' === $operator ) ? $match : ! $match;
	}

	/**
	 * Evaluate device type condition.
	 *
	 * @param string $operator Operator (is).
	 * @param string $value    Device type (mobile/desktop).
	 * @return bool
	 */
	private function eval_device_type( $operator, $value ) {
		$is_mobile = wp_is_mobile();

		if ( 'mobile' === $value ) {
			return $is_mobile;
		}

		return ! $is_mobile;
	}

	/**
	 * Evaluate WooCommerce page condition.
	 *
	 * @param string       $operator Operator (is/is_not).
	 * @param string|array $value    WooCommerce page type(s).
	 * @return bool
	 */
	private function eval_wc_page( $operator, $value ) {
		if ( ! class_exists( 'WooCommerce' ) ) {
			return ( 'is_not' === $operator );
		}

		$pages = (array) $value;
		$match = false;

		foreach ( $pages as $page_type ) {
			switch ( $page_type ) {
				case 'shop':
					$match = is_shop();
					break;
				case 'cart':
					$match = is_cart() || $this->page_has_block( 'woocommerce/cart' );
					break;
				case 'checkout':
					$match = ( is_checkout() && ! is_wc_endpoint_url() ) || $this->page_has_block( 'woocommerce/checkout' );
					break;
				case 'product':
					$match = is_product();
					break;
				case 'product_cat':
					$match = is_product_category();
					break;
				case 'product_tag':
					$match = is_product_tag();
					break;
				case 'account':
					$match = is_account_page();
					break;
				case 'order_received':
					$match = ( function_exists( 'is_order_received_page' ) && is_order_received_page() )
						|| ( is_checkout() && is_wc_endpoint_url( 'order-received' ) )
						|| $this->page_has_block( 'woocommerce/order-confirmation' );
					break;
				case 'order_pay':
					$match = is_checkout_pay_page();
					break;
				case 'view_order':
					$match = is_wc_endpoint_url( 'view-order' );
					break;
				case 'edit_account':
					$match = is_wc_endpoint_url( 'edit-account' );
					break;
				case 'edit_address':
					$match = is_wc_endpoint_url( 'edit-address' );
					break;
				case 'lost_password':
					$match = is_wc_endpoint_url( 'lost-password' );
					break;
				case 'payment_methods':
					$match = is_wc_endpoint_url( 'payment-methods' );
					break;
				case 'add_payment_method':
					$match = is_wc_endpoint_url( 'add-payment-method' );
					break;
				case 'downloads':
					$match = is_wc_endpoint_url( 'downloads' );
					break;
				case 'orders':
					$match = is_wc_endpoint_url( 'orders' );
					break;
			}
			if ( $match ) {
				break;
			}
		}

		return ( 'is' === $operator ) ? $match : ! $match;
	}

	/**
	 * Check if the current page/post contains a specific block.
	 * Handles both Block Editor pages and classic pages.
	 *
	 * @param string $block_name Full block name (e.g. 'woocommerce/cart').
	 * @return bool
	 */
	private function page_has_block( $block_name ) {
		global $post;

		if ( ! $post || ! function_exists( 'has_block' ) ) {
			return false;
		}

		return has_block( $block_name, $post );
	}

	/**
	 * Evaluate specific WooCommerce product condition.
	 *
	 * @param string       $operator Operator (is/is_not).
	 * @param string|array $value    Product ID(s).
	 * @return bool
	 */
	private function eval_wc_product( $operator, $value ) {
		if ( ! class_exists( 'WooCommerce' ) || ! is_product() ) {
			return ( 'is_not' === $operator );
		}

		$current_id  = get_queried_object_id();
		$product_ids = array_map( 'absint', (array) $value );
		$match       = in_array( $current_id, $product_ids, true );

		return ( 'is' === $operator ) ? $match : ! $match;
	}

	/**
	 * Evaluate WooCommerce product category condition.
	 *
	 * @param string       $operator Operator (is/is_not).
	 * @param string|array $value    Category slug(s) or "product_cat:slug" format.
	 * @return bool
	 */
	private function eval_wc_product_category( $operator, $value ) {
		if ( ! class_exists( 'WooCommerce' ) ) {
			return ( 'is_not' === $operator );
		}

		$categories = (array) $value;
		$match      = false;

		if ( is_product_category( $categories ) ) {
			$match = true;
		} elseif ( is_product() ) {
			$product_id = get_queried_object_id();
			foreach ( $categories as $cat ) {
				if ( has_term( $cat, 'product_cat', $product_id ) ) {
					$match = true;
					break;
				}
			}
		}

		return ( 'is' === $operator ) ? $match : ! $match;
	}

	/**
	 * Evaluate WooCommerce product tag condition.
	 *
	 * @param string       $operator Operator (is/is_not).
	 * @param string|array $value    Tag slug(s).
	 * @return bool
	 */
	private function eval_wc_product_tag( $operator, $value ) {
		if ( ! class_exists( 'WooCommerce' ) ) {
			return ( 'is_not' === $operator );
		}

		$tags  = (array) $value;
		$match = false;

		if ( is_product_tag( $tags ) ) {
			$match = true;
		} elseif ( is_product() ) {
			$product_id = get_queried_object_id();
			foreach ( $tags as $tag ) {
				if ( has_term( $tag, 'product_tag', $product_id ) ) {
					$match = true;
					break;
				}
			}
		}

		return ( 'is' === $operator ) ? $match : ! $match;
	}

	/**
	 * Evaluate WooCommerce cart empty condition.
	 *
	 * @param string $operator Operator (is).
	 * @param string $value    Expected state (yes=empty, no=not empty).
	 * @return bool
	 */
	private function eval_wc_cart_empty( $operator, $value ) {
		if ( ! class_exists( 'WooCommerce' ) || ! WC()->cart ) {
			return true;
		}

		$is_empty = WC()->cart->is_empty();
		$expected = ( 'yes' === $value );

		return ( $is_empty === $expected );
	}

	/**
	 * Evaluate WooCommerce cart total condition.
	 *
	 * @param string $operator Operator (greater_than/less_than).
	 * @param string $value    Amount to compare against.
	 * @return bool
	 */
	private function eval_wc_cart_total( $operator, $value ) {
		if ( ! class_exists( 'WooCommerce' ) || ! WC()->cart ) {
			return false;
		}

		$total  = (float) WC()->cart->get_cart_contents_total();
		$amount = (float) $value;

		if ( 'greater_than' === $operator ) {
			return ( $total > $amount );
		}

		return ( $total < $amount );
	}

	/**
	 * Evaluate WooCommerce cart contains product condition.
	 *
	 * @param string       $operator Operator (is/is_not).
	 * @param string|array $value    Product ID(s) to check for.
	 * @return bool
	 */
	private function eval_wc_cart_contains( $operator, $value ) {
		if ( ! class_exists( 'WooCommerce' ) || ! WC()->cart ) {
			return ( 'is_not' === $operator );
		}

		$product_ids = array_map( 'absint', (array) $value );
		$cart_items  = WC()->cart->get_cart();
		$match       = false;

		foreach ( $cart_items as $item ) {
			if ( in_array( (int) $item['product_id'], $product_ids, true ) ) {
				$match = true;
				break;
			}
		}

		return ( 'is' === $operator ) ? $match : ! $match;
	}

	/**
	 * Convert legacy snippet conditions to the new rule format.
	 *
	 * @param array $snippet Legacy snippet data.
	 * @return array Conditional rules array.
	 */
	public function convert_legacy_conditions( $snippet ) {
		$rules  = array();
		$groups = array();

		$group_rules = array();

		// User condition.
		$user_cond = isset( $snippet['user_cond'] ) ? $snippet['user_cond'] : 'all';
		if ( 'all' !== $user_cond ) {
			$group_rules[] = array(
				'type'     => 'user_logged_in',
				'operator' => 'is',
				'value'    => ( 'logged_in' === $user_cond ) ? 'yes' : 'no',
			);
		}

		// Post types.
		$post_types = isset( $snippet['post_types'] ) ? (array) $snippet['post_types'] : array();
		if ( ! empty( $post_types ) ) {
			$group_rules[] = array(
				'type'     => 'post_type',
				'operator' => 'is',
				'value'    => $post_types,
			);
		}

		// Specific pages.
		$page_ids = isset( $snippet['page_ids'] ) ? trim( $snippet['page_ids'] ) : '';
		if ( '' !== $page_ids ) {
			$ids = array_filter( array_map( 'absint', explode( ',', $page_ids ) ) );
			if ( ! empty( $ids ) ) {
				$group_rules[] = array(
					'type'     => 'page',
					'operator' => 'is',
					'value'    => $ids,
				);
			}
		}

		// URL patterns -- each pattern becomes a rule in an OR group.
		$url_patterns = isset( $snippet['url_patterns'] ) ? trim( $snippet['url_patterns'] ) : '';
		if ( '' !== $url_patterns ) {
			$url_group_rules = array();
			$patterns        = array_filter( array_map( 'trim', explode( "\n", $url_patterns ) ) );
			foreach ( $patterns as $pattern ) {
				$url_group_rules[] = array(
					'type'     => 'url_contains',
					'operator' => 'contains',
					'value'    => str_replace( '*', '', $pattern ),
				);
			}
			if ( ! empty( $url_group_rules ) ) {
				$groups[] = array(
					'match' => 'any',
					'rules' => $url_group_rules,
				);
			}
		}

		// Taxonomy terms.
		$taxonomies = isset( $snippet['taxonomies'] ) ? (array) $snippet['taxonomies'] : array();
		if ( ! empty( $taxonomies ) ) {
			$group_rules[] = array(
				'type'     => 'taxonomy_term',
				'operator' => 'is',
				'value'    => $taxonomies,
			);
		}

		// Schedule.
		$start = isset( $snippet['schedule_start'] ) ? trim( $snippet['schedule_start'] ) : '';
		$end   = isset( $snippet['schedule_end'] ) ? trim( $snippet['schedule_end'] ) : '';
		if ( '' !== $start || '' !== $end ) {
			$group_rules[] = array(
				'type'     => 'schedule',
				'operator' => 'between',
				'value'    => array(
					'start' => $start,
					'end'   => $end,
				),
			);
		}

		if ( ! empty( $group_rules ) ) {
			array_unshift(
				$groups,
				array(
					'match' => 'all',
					'rules' => $group_rules,
				)
			);
		}

		if ( empty( $groups ) ) {
			return array();
		}

		return array(
			'enabled' => true,
			'match'   => 'all',
			'groups'  => $groups,
		);
	}

	/**
	 * Check if a snippet should run based on both legacy and new-style conditions.
	 *
	 * Supports both the old field-based conditions and the new conditional_rules format.
	 * If conditional_rules are present and enabled, they take precedence.
	 *
	 * @param array $snippet Full snippet data.
	 * @return bool
	 */
	public function should_run( $snippet ) {
		if ( ! empty( $snippet['conditional_rules'] ) && ! empty( $snippet['conditional_rules']['enabled'] ) ) {
			return $this->evaluate( $snippet['conditional_rules'] );
		}

		$legacy_rules = $this->convert_legacy_conditions( $snippet );
		if ( ! empty( $legacy_rules ) ) {
			return $this->evaluate( $legacy_rules );
		}

		return true;
	}

	/**
	 * Get dynamic values for a condition type.
	 *
	 * Used by the UI to populate selects.
	 *
	 * @param string $value_type The dynamic value type key.
	 * @return array
	 */
	public function get_dynamic_values( $value_type ) {
		switch ( $value_type ) {
			case 'dynamic_roles':
				return $this->get_roles();
			case 'dynamic_post_types':
				return $this->get_post_types();
			case 'dynamic_pages':
				return array();
			case 'dynamic_taxonomy':
				return $this->get_taxonomy_terms();
			case 'dynamic_wc_categories':
				return $this->get_wc_product_categories();
			case 'dynamic_wc_tags':
				return $this->get_wc_product_tags();
			case 'dynamic_wc_products':
				return array();
			default:
				return array();
		}
	}

	/**
	 * Get available user roles.
	 *
	 * @return array<string, string>
	 */
	private function get_roles() {
		$roles  = array();
		$wp_roles = wp_roles();
		foreach ( $wp_roles->role_names as $slug => $name ) {
			$roles[ $slug ] = translate_user_role( $name );
		}
		return $roles;
	}

	/**
	 * Get public post types.
	 *
	 * @return array<string, string>
	 */
	private function get_post_types() {
		$result     = array();
		$post_types = get_post_types( array( 'public' => true ), 'objects' );
		unset( $post_types['attachment'] );

		foreach ( $post_types as $pt ) {
			$result[ $pt->name ] = $pt->labels->singular_name;
		}
		return $result;
	}

	/**
	 * Get taxonomy terms grouped by taxonomy.
	 *
	 * @return array
	 */
	private function get_taxonomy_terms() {
		$result     = array();
		$taxonomies = get_taxonomies(
			array(
				'public'  => true,
				'show_ui' => true,
			),
			'objects'
		);
		unset( $taxonomies['post_format'] );

		foreach ( $taxonomies as $tax ) {
			$terms = get_terms(
				array(
					'taxonomy'   => $tax->name,
					'hide_empty' => true,
					'number'     => 100,
				)
			);

			if ( empty( $terms ) || is_wp_error( $terms ) ) {
				continue;
			}

			$group = array(
				'label' => $tax->labels->singular_name,
				'items' => array(),
			);

			foreach ( $terms as $term ) {
				$group['items'][ $tax->name . ':' . $term->slug ] = $term->name;
			}

			$result[] = $group;
		}

		return $result;
	}

	/**
	 * Get WooCommerce product categories.
	 *
	 * @return array<string, string>
	 */
	private function get_wc_product_categories() {
		if ( ! class_exists( 'WooCommerce' ) ) {
			return array();
		}

		$terms = get_terms(
			array(
				'taxonomy'   => 'product_cat',
				'hide_empty' => false,
				'number'     => 200,
				'orderby'    => 'name',
				'order'      => 'ASC',
			)
		);

		if ( empty( $terms ) || is_wp_error( $terms ) ) {
			return array();
		}

		$result = array();
		foreach ( $terms as $term ) {
			$result[ $term->slug ] = $term->name;
		}
		return $result;
	}

	/**
	 * Get WooCommerce product tags.
	 *
	 * @return array<string, string>
	 */
	private function get_wc_product_tags() {
		if ( ! class_exists( 'WooCommerce' ) ) {
			return array();
		}

		$terms = get_terms(
			array(
				'taxonomy'   => 'product_tag',
				'hide_empty' => false,
				'number'     => 200,
				'orderby'    => 'name',
				'order'      => 'ASC',
			)
		);

		if ( empty( $terms ) || is_wp_error( $terms ) ) {
			return array();
		}

		$result = array();
		foreach ( $terms as $term ) {
			$result[ $term->slug ] = $term->name;
		}
		return $result;
	}

	/**
	 * Get the current request URL.
	 *
	 * @return string
	 */
	private function get_current_url() {
		static $url = null;
		if ( null === $url ) {
			$url = isset( $_SERVER['REQUEST_URI'] )
				? esc_url_raw( wp_unslash( $_SERVER['REQUEST_URI'] ) )
				: '';
		}
		return $url;
	}

	/**
	 * Get the current request path (without query string).
	 *
	 * @return string
	 */
	private function get_current_path() {
		static $path = null;
		if ( null === $path ) {
			$url  = $this->get_current_url();
			$path = wp_parse_url( $url, PHP_URL_PATH );
			if ( null === $path ) {
				$path = '/';
			}
		}
		return $path;
	}
}
