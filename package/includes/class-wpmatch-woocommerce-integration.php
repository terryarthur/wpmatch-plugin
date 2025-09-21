<?php
/**
 * WPMatch WooCommerce Integration
 *
 * Handles integration with WooCommerce for premium memberships and features.
 *
 * @package WPMatch
 * @since   1.0.0
 */

// Security check.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * WPMatch WooCommerce Integration Class
 *
 * Manages WooCommerce integration for premium features.
 */
class WPMatch_WooCommerce_Integration {

	/**
	 * Initialize WooCommerce integration.
	 */
	public static function init() {
		// Check if WooCommerce is active.
		add_action( 'plugins_loaded', array( __CLASS__, 'check_woocommerce_compatibility' ) );

		// Create products on activation.
		register_activation_hook( WPMATCH_PLUGIN_FILE, array( __CLASS__, 'create_products_on_activation' ) );
	}

	/**
	 * Check if WooCommerce is active and compatible.
	 */
	public static function check_woocommerce_compatibility() {
		if ( ! class_exists( 'WooCommerce' ) ) {
			add_action( 'admin_notices', array( __CLASS__, 'woocommerce_missing_notice' ) );
			return false;
		}

		// Check WooCommerce version.
		if ( version_compare( WC()->version, '7.0.0', '<' ) ) {
			add_action( 'admin_notices', array( __CLASS__, 'woocommerce_version_notice' ) );
		}

		return true;
	}

	/**
	 * Show WooCommerce missing notice.
	 */
	public static function woocommerce_missing_notice() {
		echo '<div class="notice notice-error"><p>';
		esc_html_e( 'WPMatch requires WooCommerce to be installed and activated for premium features.', 'wpmatch' );
		echo '</p></div>';
	}

	/**
	 * Show WooCommerce version notice.
	 */
	public static function woocommerce_version_notice() {
		echo '<div class="notice notice-warning"><p>';
		esc_html_e( 'WPMatch recommends WooCommerce 7.0.0 or higher for optimal compatibility.', 'wpmatch' );
		echo '</p></div>';
	}

	/**
	 * Create products on plugin activation.
	 */
	public static function create_products_on_activation() {
		if ( ! class_exists( 'WooCommerce' ) ) {
			return;
		}

		self::create_membership_products();
		self::create_feature_products();
	}

	/**
	 * Create premium membership products.
	 */
	public static function create_membership_products() {
		if ( ! class_exists( 'WC_Product_Simple' ) ) {
			return false;
		}

		$products = array(
			'wpmatch_basic_premium'    => array(
				'name'        => __( 'WPMatch Basic Premium', 'wpmatch' ),
				'description' => __( 'Basic premium membership with enhanced features', 'wpmatch' ),
				'price'       => '9.99',
				'duration'    => 30,
				'features'    => array( 'unlimited_likes', 'see_who_liked_you' ),
			),
			'wpmatch_gold_premium'     => array(
				'name'        => __( 'WPMatch Gold Premium', 'wpmatch' ),
				'description' => __( 'Gold premium membership with advanced features', 'wpmatch' ),
				'price'       => '19.99',
				'duration'    => 30,
				'features'    => array( 'unlimited_likes', 'see_who_liked_you', 'super_likes', 'boost_profile' ),
			),
			'wpmatch_platinum_premium' => array(
				'name'        => __( 'WPMatch Platinum Premium', 'wpmatch' ),
				'description' => __( 'Platinum premium membership with all features', 'wpmatch' ),
				'price'       => '29.99',
				'duration'    => 30,
				'features'    => array( 'unlimited_likes', 'see_who_liked_you', 'super_likes', 'boost_profile', 'priority_support', 'advanced_filters' ),
			),
		);

		foreach ( $products as $product_key => $product_data ) {
			// Check if product already exists.
			$existing_product = get_posts(
				array(
					'post_type'      => 'product',
					'meta_key'       => '_wpmatch_product_type',
					'meta_value'     => $product_key,
					'posts_per_page' => 1,
				)
			);

			if ( ! empty( $existing_product ) ) {
				continue;
			}

			// Create WooCommerce product using CRUD.
			$product = new WC_Product_Simple();

			// Basic product data.
			$product->set_name( sanitize_text_field( $product_data['name'] ) );
			$product->set_description( sanitize_textarea_field( $product_data['description'] ) );
			$product->set_short_description( sanitize_textarea_field( $product_data['description'] ) );
			$product->set_status( 'publish' );
			$product->set_featured( false );
			$product->set_catalog_visibility( 'visible' );

			// Virtual product settings.
			$product->set_virtual( true );
			$product->set_downloadable( false );

			// Pricing.
			$product->set_regular_price( sanitize_text_field( $product_data['price'] ) );
			$product->set_price( sanitize_text_field( $product_data['price'] ) );

			// Stock management.
			$product->set_manage_stock( false );
			$product->set_stock_status( 'instock' );

			// Save the product.
			$product_id = $product->save();

			if ( $product_id ) {
				// Add custom meta data for WPMatch.
				update_post_meta( $product_id, '_wpmatch_product_type', sanitize_text_field( $product_key ) );
				update_post_meta( $product_id, '_wpmatch_membership_duration', absint( $product_data['duration'] ) );
				update_post_meta( $product_id, '_wpmatch_features', array_map( 'sanitize_text_field', $product_data['features'] ) );

				// Set product category.
				wp_set_object_terms( $product_id, 'Dating Memberships', 'product_cat' );
			}
		}

		return true;
	}

	/**
	 * Create individual feature products.
	 */
	public static function create_feature_products() {
		$features = array(
			'super_likes_pack' => array(
				'name'        => __( 'Super Likes Pack (5)', 'wpmatch' ),
				'description' => __( '5 Super Likes to stand out from the crowd', 'wpmatch' ),
				'price'       => '2.99',
				'quantity'    => 5,
			),
			'profile_boost'    => array(
				'name'        => __( 'Profile Boost (24h)', 'wpmatch' ),
				'description' => __( 'Boost your profile visibility for 24 hours', 'wpmatch' ),
				'price'       => '4.99',
				'duration'    => 24,
			),
			'premium_filters'  => array(
				'name'        => __( 'Advanced Filters (7 days)', 'wpmatch' ),
				'description' => __( 'Access advanced search filters for 7 days', 'wpmatch' ),
				'price'       => '1.99',
				'duration'    => 168, // 7 days in hours.
			),
		);

		foreach ( $features as $feature_key => $feature_data ) {
			$existing = get_posts(
				array(
					'post_type'      => 'product',
					'meta_key'       => '_wpmatch_feature_type',
					'meta_value'     => $feature_key,
					'posts_per_page' => 1,
				)
			);

			if ( ! empty( $existing ) ) {
				continue;
			}

			$product = new WC_Product_Simple();
			$product->set_name( sanitize_text_field( $feature_data['name'] ) );
			$product->set_description( sanitize_textarea_field( $feature_data['description'] ) );
			$product->set_virtual( true );
			$product->set_regular_price( sanitize_text_field( $feature_data['price'] ) );
			$product->set_price( sanitize_text_field( $feature_data['price'] ) );
			$product->set_manage_stock( false );
			$product->set_stock_status( 'instock' );

			$product_id = $product->save();

			if ( $product_id ) {
				update_post_meta( $product_id, '_wpmatch_feature_type', sanitize_text_field( $feature_key ) );
				if ( isset( $feature_data['quantity'] ) ) {
					update_post_meta( $product_id, '_wpmatch_feature_quantity', absint( $feature_data['quantity'] ) );
				}
				if ( isset( $feature_data['duration'] ) ) {
					update_post_meta( $product_id, '_wpmatch_feature_duration', absint( $feature_data['duration'] ) );
				}
				wp_set_object_terms( $product_id, 'Dating Features', 'product_cat' );
			}
		}
	}

	/**
	 * Get membership product by type.
	 *
	 * @param string $product_type Product type.
	 * @return WC_Product|null
	 */
	public static function get_membership_product( $product_type ) {
		$products = get_posts(
			array(
				'post_type'      => 'product',
				'meta_key'       => '_wpmatch_product_type',
				'meta_value'     => sanitize_text_field( $product_type ),
				'posts_per_page' => 1,
			)
		);

		if ( empty( $products ) ) {
			return null;
		}

		return wc_get_product( $products[0]->ID );
	}

	/**
	 * Get feature product by type.
	 *
	 * @param string $feature_type Feature type.
	 * @return WC_Product|null
	 */
	public static function get_feature_product( $feature_type ) {
		$products = get_posts(
			array(
				'post_type'      => 'product',
				'meta_key'       => '_wpmatch_feature_type',
				'meta_value'     => sanitize_text_field( $feature_type ),
				'posts_per_page' => 1,
			)
		);

		if ( empty( $products ) ) {
			return null;
		}

		return wc_get_product( $products[0]->ID );
	}
}
