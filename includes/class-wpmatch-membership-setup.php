<?php
/**
 * WPMatch Membership Setup Handler
 *
 * Handles creation and management of WooCommerce membership products.
 *
 * @package WPMatch
 * @since   1.0.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class WPMatch_Membership_Setup
 *
 * Creates and manages WooCommerce products for membership tiers.
 *
 * @since 1.0.0
 */
class WPMatch_Membership_Setup {

	/**
	 * Process membership setup form.
	 *
	 * @since 1.0.0
	 */
	public static function process_setup_form() {
		// Check if form was submitted.
		if ( ! isset( $_POST['action'] ) || 'setup_default_memberships' !== $_POST['action'] ) {
			return;
		}

		// Verify nonce.
		if ( ! isset( $_POST['wpmatch_setup_nonce'] ) || ! wp_verify_nonce( $_POST['wpmatch_setup_nonce'], 'wpmatch_setup_memberships' ) ) {
			wp_die( esc_html__( 'Security check failed.', 'wpmatch' ) );
		}

		// Check user capabilities.
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to perform this action.', 'wpmatch' ) );
		}

		// Check WooCommerce is active.
		if ( ! class_exists( 'WooCommerce' ) ) {
			add_settings_error(
				'wpmatch_membership_setup',
				'no_woocommerce',
				__( 'WooCommerce must be installed and activated to create membership products.', 'wpmatch' ),
				'error'
			);
			return;
		}

		$created_products = array();
		$billing_period   = sanitize_text_field( $_POST['billing_period'] );
		$trial_days       = absint( $_POST['trial_days'] );

		// Create Basic membership.
		if ( isset( $_POST['create_basic'] ) && '1' === $_POST['create_basic'] ) {
			$basic_price = floatval( $_POST['basic_price'] );
			$basic_id    = self::create_membership_product(
				'Basic Membership',
				'basic',
				$basic_price,
				$billing_period,
				$trial_days,
				array(
					'daily_likes'      => 50,
					'advanced_search'  => true,
					'see_who_liked'    => false,
					'profile_visitors' => false,
					'read_receipts'    => false,
					'profile_boost'    => false,
				)
			);

			if ( $basic_id ) {
				$created_products[] = $basic_id;
			}
		}

		// Create Gold membership.
		if ( isset( $_POST['create_gold'] ) && '1' === $_POST['create_gold'] ) {
			$gold_price = floatval( $_POST['gold_price'] );
			$gold_id    = self::create_membership_product(
				'Gold Membership',
				'gold',
				$gold_price,
				$billing_period,
				$trial_days,
				array(
					'daily_likes'      => 200,
					'advanced_search'  => true,
					'see_who_liked'    => true,
					'profile_visitors' => true,
					'read_receipts'    => false,
					'profile_boost'    => 'monthly',
					'profile_badge'    => 'gold',
				)
			);

			if ( $gold_id ) {
				$created_products[] = $gold_id;
			}
		}

		// Create Platinum membership.
		if ( isset( $_POST['create_platinum'] ) && '1' === $_POST['create_platinum'] ) {
			$platinum_price = floatval( $_POST['platinum_price'] );
			$platinum_id    = self::create_membership_product(
				'Platinum Membership',
				'platinum',
				$platinum_price,
				$billing_period,
				$trial_days,
				array(
					'daily_likes'      => 'unlimited',
					'advanced_search'  => true,
					'see_who_liked'    => true,
					'profile_visitors' => true,
					'read_receipts'    => true,
					'profile_boost'    => 'weekly',
					'priority_support' => true,
					'profile_badge'    => 'platinum',
				)
			);

			if ( $platinum_id ) {
				$created_products[] = $platinum_id;
			}
		}

		// Save created product IDs.
		if ( ! empty( $created_products ) ) {
			$existing = get_option( 'wpmatch_membership_products', array() );
			$updated  = array_merge( $existing, $created_products );
			update_option( 'wpmatch_membership_products', array_unique( $updated ) );

			add_settings_error(
				'wpmatch_membership_setup',
				'products_created',
				sprintf(
					/* translators: %d: number of products created */
					__( 'Successfully created %d membership products.', 'wpmatch' ),
					count( $created_products )
				),
				'success'
			);
		}
	}

	/**
	 * Create a WooCommerce membership product.
	 *
	 * @param string $name           Product name.
	 * @param string $level          Membership level (basic, gold, platinum).
	 * @param float  $price          Product price.
	 * @param string $billing_period Billing period for subscriptions.
	 * @param int    $trial_days     Trial period in days.
	 * @param array  $features       Membership features.
	 * @return int|false Product ID on success, false on failure.
	 * @since 1.0.0
	 */
	private static function create_membership_product( $name, $level, $price, $billing_period = 'month', $trial_days = 0, $features = array() ) {
		try {
			// Check if WooCommerce Subscriptions is active.
			$use_subscriptions = class_exists( 'WC_Subscriptions' );

			// Create product.
			if ( $use_subscriptions ) {
				$product = new WC_Product_Subscription();
			} else {
				$product = new WC_Product_Simple();
			}

			// Set basic product data.
			$product->set_name( $name );
			$product->set_status( 'publish' );
			$product->set_catalog_visibility( 'visible' );
			$product->set_regular_price( $price );
			$product->set_virtual( true );
			$product->set_sold_individually( true );

			// Set description.
			$description = self::generate_product_description( $level, $features );
			$product->set_description( $description );
			$product->set_short_description( self::generate_short_description( $level ) );

			// Set SKU.
			$product->set_sku( 'wpmatch-' . $level );

			// Set subscription data if using WooCommerce Subscriptions.
			if ( $use_subscriptions ) {
				// Subscription period.
				update_post_meta( $product->get_id(), '_subscription_period', $billing_period );
				update_post_meta( $product->get_id(), '_subscription_period_interval', 1 );

				// Trial period.
				if ( $trial_days > 0 ) {
					update_post_meta( $product->get_id(), '_subscription_trial_length', $trial_days );
					update_post_meta( $product->get_id(), '_subscription_trial_period', 'day' );
				}

				// Limit to one active subscription.
				update_post_meta( $product->get_id(), '_subscription_limit', 'active' );
			}

			// Save the product.
			$product_id = $product->save();

			// Add custom meta for WPMatch.
			update_post_meta( $product_id, '_wpmatch_membership_level', $level );
			update_post_meta( $product_id, '_wpmatch_membership_features', $features );

			// Create or assign category.
			$category_id = self::get_or_create_membership_category();
			if ( $category_id ) {
				$product->set_category_ids( array( $category_id ) );
				$product->save();
			}

			return $product_id;

		} catch ( Exception $e ) {
			error_log( 'WPMatch: Failed to create membership product: ' . $e->getMessage() );
			return false;
		}
	}

	/**
	 * Get or create the membership category.
	 *
	 * @return int|false Category ID or false on failure.
	 * @since 1.0.0
	 */
	private static function get_or_create_membership_category() {
		// Check if category exists.
		$term = get_term_by( 'slug', 'wpmatch-memberships', 'product_cat' );

		if ( $term ) {
			return $term->term_id;
		}

		// Create category.
		$result = wp_insert_term(
			'WPMatch Memberships',
			'product_cat',
			array(
				'slug'        => 'wpmatch-memberships',
				'description' => 'Dating site membership plans',
			)
		);

		if ( is_wp_error( $result ) ) {
			return false;
		}

		return $result['term_id'];
	}

	/**
	 * Generate product description.
	 *
	 * @param string $level    Membership level.
	 * @param array  $features Membership features.
	 * @return string Product description.
	 * @since 1.0.0
	 */
	private static function generate_product_description( $level, $features ) {
		$descriptions = array(
			'basic'    => 'Get more from your dating experience with our Basic membership. Perfect for casual daters who want enhanced features without breaking the bank.',
			'gold'     => 'Unlock premium dating features with Gold membership. See who likes you, get more visibility, and find your perfect match faster.',
			'platinum' => 'The ultimate dating experience. Unlimited possibilities, maximum visibility, and exclusive features for serious daters.',
		);

		$description = isset( $descriptions[ $level ] ) ? $descriptions[ $level ] : '';
		$description .= "\n\n<strong>Features included:</strong>\n<ul>\n";

		// Add features to description.
		if ( isset( $features['daily_likes'] ) ) {
			$likes_text = 'unlimited' === $features['daily_likes'] ? 'Unlimited' : $features['daily_likes'];
			$description .= "<li>{$likes_text} daily likes</li>\n";
		}

		if ( ! empty( $features['see_who_liked'] ) ) {
			$description .= "<li>See who liked your profile</li>\n";
		}

		if ( ! empty( $features['advanced_search'] ) ) {
			$description .= "<li>Advanced search filters</li>\n";
		}

		if ( ! empty( $features['profile_visitors'] ) ) {
			$description .= "<li>See who viewed your profile</li>\n";
		}

		if ( ! empty( $features['read_receipts'] ) ) {
			$description .= "<li>Message read receipts</li>\n";
		}

		if ( ! empty( $features['profile_boost'] ) ) {
			$boost_text = ucfirst( $features['profile_boost'] ) . ' profile boosts';
			$description .= "<li>{$boost_text}</li>\n";
		}

		if ( ! empty( $features['priority_support'] ) ) {
			$description .= "<li>Priority customer support</li>\n";
		}

		if ( ! empty( $features['profile_badge'] ) ) {
			$badge_text = ucfirst( $features['profile_badge'] ) . ' profile badge';
			$description .= "<li>{$badge_text}</li>\n";
		}

		$description .= "</ul>\n";

		return $description;
	}

	/**
	 * Generate short description.
	 *
	 * @param string $level Membership level.
	 * @return string Short description.
	 * @since 1.0.0
	 */
	private static function generate_short_description( $level ) {
		$descriptions = array(
			'basic'    => 'Enhanced dating features for casual daters',
			'gold'     => 'Premium features to find matches faster',
			'platinum' => 'Ultimate dating experience with all features unlocked',
		);

		return isset( $descriptions[ $level ] ) ? $descriptions[ $level ] : '';
	}

	/**
	 * Get membership features by level.
	 *
	 * @param string $level Membership level.
	 * @return array Features array.
	 * @since 1.0.0
	 */
	public static function get_membership_features( $level ) {
		$features = array(
			'free'     => array(
				'daily_likes'      => 10,
				'advanced_search'  => false,
				'see_who_liked'    => false,
				'profile_visitors' => false,
				'read_receipts'    => false,
				'profile_boost'    => false,
				'priority_support' => false,
				'profile_badge'    => false,
			),
			'basic'    => array(
				'daily_likes'      => 50,
				'advanced_search'  => true,
				'see_who_liked'    => false,
				'profile_visitors' => false,
				'read_receipts'    => false,
				'profile_boost'    => false,
				'priority_support' => false,
				'profile_badge'    => false,
			),
			'gold'     => array(
				'daily_likes'      => 200,
				'advanced_search'  => true,
				'see_who_liked'    => true,
				'profile_visitors' => true,
				'read_receipts'    => false,
				'profile_boost'    => 'monthly',
				'priority_support' => false,
				'profile_badge'    => 'gold',
			),
			'platinum' => array(
				'daily_likes'      => 'unlimited',
				'advanced_search'  => true,
				'see_who_liked'    => true,
				'profile_visitors' => true,
				'read_receipts'    => true,
				'profile_boost'    => 'weekly',
				'priority_support' => true,
				'profile_badge'    => 'platinum',
			),
		);

		return isset( $features[ $level ] ) ? $features[ $level ] : $features['free'];
	}
}