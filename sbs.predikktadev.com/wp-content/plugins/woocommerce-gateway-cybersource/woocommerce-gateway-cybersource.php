<?php
/**
 * Plugin Name: WooCommerce CyberSource Gateway
 * Plugin URI: http://www.woocommerce.com/products/cybersource-payment-gateway/
 * Description: Accept credit cards in WooCommerce with the CyberSource (SOAP) payment gateway
 * Author: SkyVerge
 * Author URI: http://www.woocommerce.com/
 * Version: 1.8.1
 * Text Domain: woocommerce-gateway-cybersource
 * Domain Path: /i18n/languages/
 *
 * Copyright: (c) 2012-2018, SkyVerge, Inc. (info@skyverge.com)
 *
 * License: GNU General Public License v3.0
 * License URI: http://www.gnu.org/licenses/gpl-3.0.html
 *
 * @package   WC-CyberSource
 * @author    SkyVerge
 * @category  Payment-Gateways
 * @copyright Copyright (c) 2012-2018, SkyVerge, Inc.
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GNU General Public License v3.0
 *
 * Woo: 18690:3083c0ed00f4a1a2acc5f9044442a7a8
 * WC requires at least: 2.6.14
 * WC tested up to: 3.4.0
 */

defined( 'ABSPATH' ) or exit;

// Required functions
if ( ! function_exists( 'woothemes_queue_update' ) ) {
	require_once( plugin_dir_path( __FILE__ ) . 'woo-includes/woo-functions.php' );
}

// Plugin updates
woothemes_queue_update( plugin_basename( __FILE__ ), '3083c0ed00f4a1a2acc5f9044442a7a8', '18690' );

// WC active check
if ( ! is_woocommerce_active() ) {
	return;
}

// Required library class
if ( ! class_exists( 'SV_WC_Framework_Bootstrap' ) ) {
	require_once( plugin_dir_path( __FILE__ ) . 'lib/skyverge/woocommerce/class-sv-wc-framework-bootstrap.php' );
}

SV_WC_Framework_Bootstrap::instance()->register_plugin( '4.9.0', __( 'WooCommerce CyberSource Gateway', 'woocommerce-gateway-cybersource' ), __FILE__, 'init_woocommerce_gateway_cybersource', array(
	'minimum_wc_version'   => '2.6.14',
	'minimum_wp_version'   => '4.4',
	'backwards_compatible' => '4.4',
) );

function init_woocommerce_gateway_cybersource() {

/**
 * The main class for the CyberSource Gateway.  This class handles all the
 * non-gateway tasks such as verifying dependencies are met, loading the text
 * domain, etc.  It also loads the CyberSource Gateway when needed now that the
 * gateway is only created on the checkout & settings pages / api hook.  The gateway is
 * also loaded in the following instances:
 *
 * + On the Order Edit Admin page to display the link to CyberSource when this gateway was used as the payment method
 *
 */
class WC_CyberSource extends SV_WC_Plugin {


	/** version number */
	const VERSION = '1.8.1';

	/** @var WC_CyberSource single instance of this plugin */
	protected static $instance;

	/** gateway id */
	const PLUGIN_ID = 'cybersource';

	/** plugin text domain, DEPRECATED as of 1.5.0 */
	const TEXT_DOMAIN = 'woocommerce-gateway-cybersource';

	/** class name to load as gateway, can be base or subscriptions class */
	const GATEWAY_CLASS_NAME = 'WC_Gateway_CyberSource';


	/**
	 * Initialize the main plugin class
	 *
	 * @see SV_WC_Plugin::__construct()
	 */
	public function __construct() {

		parent::__construct(
			self::PLUGIN_ID,
			self::VERSION,
			array(
				'text_domain'        => 'woocommerce-gateway-cybersource',
				'dependencies'       => array( 'soap', 'dom' ),
				'display_php_notice' => true,
			)
		);

		// Load the gateway
		add_action( 'sv_wc_framework_plugins_loaded', array( $this, 'load_classes' ) );
	}


	/**
	 * Loads Gateway class once parent class is available
	 */
	public function load_classes() {

		// CyberSource gateway
		require_once( $this->get_plugin_path() . '/classes/class-wc-gateway-cybersource.php' );

		// Add class to WC Payment Methods
		add_filter( 'woocommerce_payment_gateways', array( $this, 'load_gateway' ) );
	}


	/**
	 * Adds gateway to the list of available payment gateways
	 *
	 * @param array $gateways array of gateway names or objects
	 * @return array $gateways array of gateway names or objects
	 */
	public function load_gateway( $gateways ) {

		$gateways[] = self::GATEWAY_CLASS_NAME;

		return $gateways;
	}


	/**
	 * Gets the plugin documentation url, which for CyberSource is non-standard
	 *
	 * @since 1.2
	 * @see SV_WC_Plugin::get_documentation_url()
	 * @return string documentation URL
	 */
	public function get_documentation_url() {
		return 'http://docs.woocommerce.com/document/cybersource-payment-gateway/';
	}


	/**
	 * Gets the plugin support URL
	 *
	 * @since 1.4.0
	 * @see SV_WC_Plugin::get_support_url()
	 * @return string
	 */
	public function get_support_url() {

		return 'https://woocommerce.com/my-account/marketplace-ticket-form/';
	}

	/**
	 * Returns the admin configuration url for the gateway with class name
	 * $gateway_class_name
	 *
	 * @since 2.2.0-1
	 * @return string admin configuration url for the gateway
	 */
	public function get_payment_gateway_configuration_url() {

		return admin_url( 'admin.php?page=wc-settings&tab=checkout&section=' . self::PLUGIN_ID );
	}


	/**
	 * Returns true if the current page is the admin configuration page for the
	 * gateway with class name $gateway_class_name
	 *
	 * @since 2.2.0-1
	 * @return boolean true if the current page is the admin configuration page for the gateway
	 */
	public function is_payment_gateway_configuration_page() {

		return isset( $_GET['page'] ) && 'wc-settings' == $_GET['page'] &&
		isset( $_GET['tab'] ) && 'checkout' == $_GET['tab'] &&
		isset( $_GET['section'] ) && self::PLUGIN_ID === $_GET['section'];
	}


	/**
	 * Get the gateway's settings screen section ID.
	 *
	 * @since 1.6.0
	 * @deprecated 1.8.0
	 *
	 * @return string
	 */
	public function get_payment_gateway_configuration_section() {

		SV_WC_Plugin_Compatibility::wc_doing_it_wrong( 'WC_CyberSource::get_payment_gateway_configuration_section()', 'Deprecated! Use the plain gateway ID instead.', '1.8.0' );

		return strtolower( self::PLUGIN_ID );
	}


	/**
	 * Gets the gateway configuration URL
	 *
	 * @since 1.2
	 * @see SV_WC_Plugin::get_settings_url()
	 * @param string $plugin_id the plugin identifier.  Note that this can be a
	 *        sub-identifier for plugins with multiple parallel settings pages
	 *        (ie a gateway that supports both credit cards and echecks)
	 * @return string plugin settings URL
	 */
	public function get_settings_url( $plugin_id = null ) {
		return $this->get_payment_gateway_configuration_url();
	}


	/**
	 * Returns true if on the gateway settings page
	 *
	 * @since 1.2
	 * @see SV_WC_Plugin::is_plugin_settings()
	 * @return boolean true if on the admin gateway settings page
	 */
	public function is_plugin_settings() {
		return $this->is_payment_gateway_configuration_page();
	}


	/**
	 * Checks if required PHP extensions are loaded and SSL is enabled. Adds an admin notice if either check fails.
	 * Also gateway settings are checked as well.
	 *
	 * @since 1.2.2
	 * @see SV_WC_Plugin::add_delayed_admin_notices()
	 */
	public function add_delayed_admin_notices() {

		parent::add_delayed_admin_notices();

		// show a notice for any settings/configuration issues
		$this->add_ssl_required_admin_notice();
	}


	/**
	 * Render the SSL Required notice, as needed
	 *
	 * @since 1.2.2
	 */
	private function add_ssl_required_admin_notice() {

		// check settings:  gateway active and SSl enabled
		$settings = get_option( 'woocommerce_cybersource_settings' );

		if ( isset( $settings['enabled'] ) && 'yes' == $settings['enabled'] && isset( $settings['environment'] ) && 'production' == $settings['environment'] ) {
			// SSL check if gateway enabled/production mode
			if ( 'no' === get_option( 'woocommerce_force_ssl_checkout' ) ) {
				$message = sprintf(
					__( "%CyberSource Error%s: WooCommerce is not being forced over SSL; your customer's credit card data is at risk.", 'woocommerce-gateway-cybersource' ),
					'<strong>', '</strong>'
				);
				$this->get_admin_notice_handler()->add_admin_notice( $message, 'ssl-required' );
			}
		}
	}


	/** Helper methods ******************************************************/


	/**
	 * Main CyberSource Instance, ensures only one instance is/can be loaded
	 *
	 * @since 1.3.0
	 * @see wc_cybersource()
	 * @return WC_CyberSource
	 */
	public static function instance() {
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}
		return self::$instance;
	}


	/**
	 * Returns the plugin name, localized
	 *
	 * @since 1.2
	 * @see SV_WC_Payment_Gateway::get_plugin_name()
	 * @return string the plugin name
	 */
	public function get_plugin_name() {
		return __( 'WooCommerce CyberSource', 'woocommerce-gateway-cybersource' );
	}


	/**
	 * Returns __FILE__
	 *
	 * @since 1.2
	 * @return string the full path and filename of the plugin file
	 */
	protected function get_file() {
		return __FILE__;
	}


	/** Lifecycle methods ******************************************************/


	/**
	 * Handles upgrades
	 *
	 * @since 1.1.1
	 * @see SV_WC_Plugin::upgrade()
	 * @param string $installed_version the currently installed version
	 */
	protected function upgrade( $installed_version ) {

		global $wpdb;

		$settings = get_option( 'woocommerce_' . self::PLUGIN_ID . '_settings' );

		// standardize debug_mode setting
		if ( version_compare( $installed_version, '1.1.1', '<' ) && $settings ) {

			// previous settings
			$log_enabled   = isset( $settings['log'] )   && 'yes' == $settings['log']   ? true : false;
			$debug_enabled = isset( $settings['debug'] ) && 'yes' == $settings['debug'] ? true : false;

			// logger -> debug_mode
			if ( $log_enabled && $debug_enabled ) {
				$settings['debug_mode'] = 'both';
			} elseif ( ! $log_enabled && ! $debug_enabled ) {
				$settings['debug_mode'] = 'off';
			} elseif ( $log_enabled ) {
				$settings['debug_mode'] = 'log';
			} else {
				$settings['debug_mode'] = 'checkout';
			}

			unset( $settings['log'] );
			unset( $settings['debug'] );

			// set the updated options array
			update_option( 'woocommerce_' . self::PLUGIN_ID . '_settings', $settings );
		}

		// standardize enable_csc setting
		if ( version_compare( $installed_version, '1.1.2', '<' ) && $settings ) {

			$enable_csc = ! isset( $settings['cvv'] ) || 'yes' == $settings['cvv'] ? 'yes' : 'no';

			$settings['enable_csc'] = $enable_csc;

			unset( $settings['cvv'] );

			// set the updated options array
			update_option( 'woocommerce_' . self::PLUGIN_ID . '_settings', $settings );

		}

		// standardize order meta names and values (with the exception of the cc expiration, which just isn't worth the effort at the moment)
		if ( version_compare( $installed_version, '1.2', '<' ) ) {

			if ( $settings ) {
				// testmode -> environment
				if ( isset( $settings['testmode'] ) && 'yes' == $settings['testmode'] ) {
					$settings['environment'] = 'test';
				} else {
					$settings['environment'] = 'production';
				}
				unset( $settings['testmode'] );

				// cardtypes -> card_types
				if ( isset( $settings['cardtypes'] ) ) {
					$settings['card_types'] = $settings['cardtypes'];
				}
				unset( $settings['cardtypes'] );

				// salemethod -> transaction_type, 'AUTH_ONLY' -> 'authorization', 'AUTH_CAPTURE' -> 'charge'
				if ( isset( $settings['salemethod'] ) ) {
					$settings['transaction_type'] = 'AUTH_ONLY' == $settings['salemethod'] ? 'authorization' : 'charge';
				}
				unset( $settings['salemethod'] );

				// set the updated options array
				update_option( 'woocommerce_' . self::PLUGIN_ID . '_settings', $settings );
			}

			$wpdb->query( "UPDATE {$wpdb->postmeta} SET meta_key='_wc_cybersource_trans_id'         WHERE meta_key='_cybersource_request_id'" );

			$wpdb->query( "UPDATE {$wpdb->postmeta} SET meta_value='test'                           WHERE meta_key='_cybersource_orderpage_environment' AND meta_value='TEST'" );
			$wpdb->query( "UPDATE {$wpdb->postmeta} SET meta_value='production'                     WHERE meta_key='_cybersource_orderpage_environment' AND meta_value='PRODUCTION'" );
			$wpdb->query( "UPDATE {$wpdb->postmeta} SET meta_key='_wc_cybersource_environment'      WHERE meta_key='_cybersource_orderpage_environment'" );

			$wpdb->query( "UPDATE {$wpdb->postmeta} SET meta_value='visa'                           WHERE meta_key='_cybersource_card_type' AND meta_value='Visa'" );
			$wpdb->query( "UPDATE {$wpdb->postmeta} SET meta_value='mc'                             WHERE meta_key='_cybersource_card_type' AND meta_value='MasterCard'" );
			$wpdb->query( "UPDATE {$wpdb->postmeta} SET meta_value='amex'                           WHERE meta_key='_cybersource_card_type' AND meta_value='American Express'" );
			$wpdb->query( "UPDATE {$wpdb->postmeta} SET meta_value='disc'                           WHERE meta_key='_cybersource_card_type' AND meta_value='Discover'" );
			$wpdb->query( "UPDATE {$wpdb->postmeta} SET meta_key='_wc_cybersource_card_type'        WHERE meta_key='_cybersource_card_type'" );

			$wpdb->query( "UPDATE {$wpdb->postmeta} SET meta_key='_wc_cybersource_account_four'     WHERE meta_key='_cybersource_card_last4'" );

			$wpdb->query( "UPDATE {$wpdb->postmeta} SET meta_key='_wc_cybersource_card_expiry_date' WHERE meta_key='_cybersource_card_expiration'" );  // older entries will be in the form MM/YYYY
		}
	}

} // end WC_CyberSource


/**
 * Returns the One True Instance of CyberSource
 *
 * @since 1.3.0
 * @return WC_CyberSource
 */
function wc_cybersource() {
	return WC_CyberSource::instance();
}

// fire it up!
wc_cybersource();

} // init_woocommerce_gateway_cybersource()
