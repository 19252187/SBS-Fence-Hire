<?php
/**
 * WooCommerce CyberSource
 *
 * This source file is subject to the GNU General Public License v3.0
 * that is bundled with this package in the file license.txt.
 * It is also available through the world-wide-web at this URL:
 * http://www.gnu.org/licenses/gpl-3.0.html
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@skyverge.com so we can send you a copy immediately.
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade WooCommerce CyberSource to newer
 * versions in the future. If you wish to customize WooCommerce CyberSource for your
 * needs please refer to http://docs.woocommerce.com/document/cybersource-payment-gateway/
 *
 * @package     WC-CyberSource/Gateway
 * @author      SkyVerge
 * @copyright   Copyright (c) 2012-2018, SkyVerge, Inc.
 * @license     http://www.gnu.org/licenses/gpl-3.0.html GNU General Public License v3.0
 */

defined( 'ABSPATH' ) or exit;

/**
 * Gateway class
 *
 * For test transactions:
 *
 * Card Type/Number:
 * Visa       / 4111111111111111
 * MasterCard / 5555555555554444
 * Amex       / 378282246310005
 * Discover   / 6011111111111117
 *
 * Expiration Date: in the future
 * Card Security Code: ignored
 */
class WC_Gateway_CyberSource extends WC_Payment_Gateway {

	/** @var string transaction endpoint for test mode */
	private $test_endpoint = "https://ics2wstesta.ic3.com/commerce/1.x/transactionProcessor/CyberSourceTransaction_1.26.wsdl";

	/** @var string transaction endpoint for production mode */
	private $live_endpoint = "https://ics2wsa.ic3.com/commerce/1.x/transactionProcessor/CyberSourceTransaction_1.26.wsdl";

	/** @var string gateway environment, one of 'test' or 'production' */
	private $environment;

	/** @var string transaction type: authorize only (authorize) or authorize and capture (charge).  defaults to charge */
	private $transaction_type;

	/** @var array the accepted card types */
	private $card_types;

	/** @var string the account merchant id */
	private $merchantid;

	/** @var string whether the card security code is enabled, one of 'yes' or 'no', defaults to 'yes' */
	private $enable_csc;

	/** @var string test transaction key */
	private $transactionkeytest;

	/** @var string live transaction key */
	private $transactionkeylive;

	/** @var string 4 options for debug mode - off, checkout, log, both */
	private $debug_mode;

	/** @var array Associative array of card id to card name */
	private $card_type_options;

	/** @var array associative array of card id to card type */
	private $card_id_to_type;


	/**
	 * Initialize the payment gateway
	 *
	 * @see WC_Payment_Gateway::__construct()
	 */
	public function __construct() {

		$this->id                 = WC_CyberSource::PLUGIN_ID;
		$this->method_title       = __( 'CyberSource', 'woocommerce-gateway-cybersource' );
		$this->method_description = __( 'CyberSource Simple Order (SOAP) provides a seamless and secure checkout process for your customers', 'woocommerce-gateway-cybersource' );

		$this->supports = array( 'products' );

		$this->has_fields = true;

		$this->icon = apply_filters( 'woocommerce_cybersource_icon', '' );

		// define the default card type options, and allow plugins to add in additional ones.
		//  Additional display names can be associated with a single card type by using the
		//  following convention: 001: Visa, 001-1: Visa Debit, etc
		$this->card_type_options = apply_filters( 'woocommerce_cybersource_card_types',
			array(
				'001' => 'Visa',
				'002' => 'MasterCard',
				'003' => 'American Express',
				'004' => 'Discover',
			)
		);

		$this->card_id_to_type = array(
			'001' => 'visa',
			'002' => 'mc',
			'003' => 'amex',
			'004' => 'disc',
		);

		// Load the form fields.
		$this->init_form_fields();

		// Load the settings.
		$this->init_settings();

		// Define user set variables
		foreach ( $this->settings as $setting_key => $setting ) {
			$this->$setting_key = $setting;
		}

		if ( ! $this->is_production_environment() ) {
			$this->description .= ' ' . __( 'TEST MODE ENABLED', 'woocommerce-gateway-cybersource' );
		}

		// pay page fallback
		add_action( 'woocommerce_receipt_' . $this->id, create_function( '$order', 'echo "<p>" . __( "Thank you for your order.", "woocommerce-gateway-cybersource" ) . "</p>";' ) );

		if ( is_admin() ) {
			add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
		}
	}


	/**
	 * Initialize Settings Form Fields
	 *
	 * Add an array of fields to be displayed
	 * on the gateway's settings screen.
	 *
	 * @see WC_Settings_API::init_form_fields()
	 */
	public function init_form_fields() {

		$this->form_fields = array(

			'enabled' => array(
				'title'       => __( 'Enable / Disable', 'woocommerce-gateway-cybersource' ),
				'label'       => __( 'Enable CyberSource', 'woocommerce-gateway-cybersource' ),
				'type'        => 'checkbox',
				'default'     => 'no'
			),

			'title' => array(
				'title'    => __( 'Title', 'woocommerce-gateway-cybersource' ),
				'type'     => 'text',
				'desc_tip' => __( 'Payment method title that the customer will see on your website.', 'woocommerce-gateway-cybersource' ),
				'default'  => __( 'Credit Card', 'woocommerce-gateway-cybersource' )
			),

			'description' => array(
				'title'    => __( 'Description', 'woocommerce-gateway-cybersource' ),
				'type'     => 'textarea',
				'desc_tip' => __( 'Payment method description that the customer will see on your website.', 'woocommerce-gateway-cybersource' ),
				'default'  => __( 'Pay securely using your credit card.', 'woocommerce-gateway-cybersource' )
			),

			'transaction_type' => array(
				'title'    => __( 'Transaction Type', 'woocommerce-gateway-cybersource' ),
				'type'     => 'select',
				'desc_tip' => __( 'Select how transactions should be processed. Charge submits all transactions for settlement, Authorization simply authorizes the order total for capture later.', 'woocommerce-gateway-cybersource' ),
				'default'  => 'charge',
				'options'  => array(
					'charge'        => __( 'Charge', 'woocommerce-gateway-cybersource' ),
					'authorization' => __( 'Authorization', 'woocommerce-gateway-cybersource' ),
				),
			),

			'enable_csc' => array(
				'title'   => __( 'Card Verification (CSC)', 'woocommerce-gateway-cybersource' ),
				'label'   => __( 'Display the Card Security Code (CV2) field on checkout', 'woocommerce-gateway-cybersource' ),
				'type'    => 'checkbox',
				'default' => 'yes',
			),

			'card_types' => array(
				'title'    => __( 'Accepted Cards', 'woocommerce-gateway-cybersource' ),
				'type'     => 'multiselect',
				'class'    => 'wc-enhanced-select',
				'css'         => 'width: 350px;',
				'desc_tip' => __( 'Select which card types to accept.', 'woocommerce-gateway-cybersource' ),
				'default'  => '',  // No default because these must also be configured in the CyberSource EBC
				'options'  => $this->card_type_options,
			),

			'environment' => array(
				'title'    => __( 'Environment', 'woocommerce-gateway-cybersource' ),
				'type'     => 'select',
				'default'  => 'production',
				'desc_tip' => __( 'The production environment should be used unless you have a separate test account.', 'woocommerce-gateway-cybersource' ),
				'options'  => array(
					'production' => __( 'Production', 'woocommerce-gateway-cybersource' ),
					'test'       => __( 'Test', 'woocommerce-gateway-cybersource' ),
				),
			),

			'merchantid' => array(
				'title'    => __( 'Merchant ID', 'woocommerce-gateway-cybersource' ),
				'type'     => 'text',
				'desc_tip' => __('Your CyberSource merchant id.  This is what you use to log into the CyberSource Business Center.', 'woocommerce-gateway-cybersource' ),
			),

			'transactionkeytest' => array(
				'title'    => __( 'Test Transaction Security Key', 'woocommerce-gateway-cybersource' ),
				'type'     => 'password',
				'class'    => 'test-field',
				'desc_tip' => __("The transaction security key for your test account.  Find this by logging into your Test CyberSource Business Center and going to Account Management &gt; Transaction Security Keys &gt; Security Keys for the SOAP Toolkit API and clicking 'Generate'.", 'woocommerce-gateway-cybersource'),
			),

			'transactionkeylive' => array(
				'title'    => __( 'Live Transaction Security Key', 'woocommerce-gateway-cybersource' ),
				'type'     => 'password',
				'class'    => 'live-field',
				'desc_tip' => __("The transaction security key for your live account.  Find this by logging into your Live CyberSource Business Center and going to Account Management &gt; Transaction Security Keys &gt; Security Keys for the SOAP Toolkit API and clicking 'Generate'.", 'woocommerce-gateway-cybersource'),
			),

			'debug_mode' => array(
				'title'       => __( 'Debug Mode', 'woocommerce-gateway-cybersource' ),
				'type'        => 'select',
				'desc_tip'    => __( 'Show Detailed Error Messages and API requests / responses on the checkout page and/or save them to the log for debugging purposes.', 'woocommerce-gateway-cybersource' ),
				'default'     => 'off',
				'options' => array(
					'off'      => __( 'Off', 'woocommerce-gateway-cybersource' ),
					'checkout' => __( 'Show on Checkout Page', 'woocommerce-gateway-cybersource' ),
					'log'      => __( 'Save to Log', 'woocommerce-gateway-cybersource' ),
					'both'     => __( 'Both', 'woocommerce-gateway-cybersource' )
				),
			),
		);
	}


	/**
	 * Display settings page with some additional javascript for hiding conditional fields
	 *
	 * @since 1.1.1
	 * @see WC_Settings_API::admin_options()
	 */
	public function admin_options() {

		parent::admin_options();

		// add inline javascript
		ob_start();
		?>
			$( '#woocommerce_cybersource_environment' ).change( function() {

				var environment = $( this ).val();

				if ( 'production' == environment ) {
					$( '.live-field' ).closest( 'tr' ).show();
					$( '.test-field' ).closest( 'tr' ).hide();
				} else {
					$( '.test-field' ).closest( 'tr' ).show();
					$( '.live-field' ).closest( 'tr' ).hide();
				}
			} ).change();
		<?php

		wc_enqueue_js( ob_get_clean() );
	}


	/**
	 * Checks for proper gateway configuration (required fields populated, etc)
	 * and that there are no missing dependencies
	 *
	 * @see WC_Payment_Gateway::is_available()
	 */
	public function is_available() {

		// is enabled check
		$is_available = parent::is_available();

		// proper configuration
		if ( ! $this->get_merchant_id() || ! $this->get_transaction_key() ) {
			$is_available = false;
		}

		// all dependencies met
		if ( count( wc_cybersource()->get_missing_dependencies() ) > 0 ) {
			$is_available = false;
		}

		return $is_available;
	}


	/**
	 * Add selected card icons to payment method label
	 *
	 * @see WC_Payment_Gateway::get_icon()
	 * @return string card icons
	 */
	public function get_icon() {

		$icon = '';

		if ( $this->icon ) {

			// default behavior
			$icon = '<img src="' . esc_url( WC_HTTPS::force_https_url( $this->icon ) ) . '" alt="' . esc_attr( $this->title ) . '" />';

		} elseif ( $this->card_types ) {

			// display icons for the selected card types
			foreach ( $this->card_types as $cardtype ) {

				if ( file_exists( wc_cybersource()->get_plugin_path() . '/assets/images/card-' . strtolower( $cardtype ) . '.png' ) ) {
					$icon .= '<img src="' . esc_url( WC_HTTPS::force_https_url( wc_cybersource()->get_plugin_url() . '/assets/images/card-' . strtolower( $cardtype ) . '.png' ) ) . '" alt="' . esc_attr( strtolower( $cardtype ) ) . '" />';
				}

			}

		}

		return apply_filters( 'woocommerce_gateway_icon', $icon, $this->id );
	}


	/**
	 * Payment fields for CyberSource.
	 *
	 * @see WC_Payment_Gateway::payment_fields()
	 */
	public function payment_fields() {
		?>
		<style type="text/css">#payment ul.payment_methods li label[for='payment_method_cybersource'] img:nth-child(n+2) { margin-left:1px; }</style>
		<fieldset>
			<?php if ( $this->get_description() ) : ?><?php echo wpautop( wptexturize( $this->get_description() ) ); ?><?php endif; ?>

			<p class="form-row form-row-first">
				<label for="cybersource_accountNumber"><?php esc_html_e( 'Credit Card number', 'woocommerce-gateway-cybersource' ) ?> <span class="required">*</span></label>
				<input type="text" class="input-text" id="cybersource_accountNumber" name="cybersource_accountNumber" maxlength="19" autocomplete="off" />
			</p>
			<p class="form-row form-row-last">
				<label for="cybersource_cardType"><?php esc_html_e( 'Card Type', 'woocommerce-gateway-cybersource' ); ?> <span class="required">*</span></label>
				<select name="cybersource_cardType" style="width:auto;"><br />
					<option value="">
					<?php
						foreach ( $this->card_types as $type ) :
							if ( isset( $this->card_type_options[ $type ] ) ) :
								?>
								<option value="<?php echo esc_attr( preg_replace( '/-.*$/', '', $type ) ); ?>" rel="<?php echo esc_attr( $type ); ?>"><?php esc_html_e( $this->card_type_options[ $type ], 'woocommerce-gateway-cybersource' ); ?></option>
								<?php
							endif;
						endforeach;
					?>
				</select>
			</p>
			<div class="clear"></div>

			<p class="form-row form-row-first">
				<label for="cybersource_expirationMonth"><?php esc_html_e( 'Expiration date', 'woocommerce-gateway-cybersource' ) ?> <span class="required">*</span></label>
				<select name="cybersource_expirationMonth" id="cybersource_expirationMonth" class="woocommerce-select woocommerce-cc-month" style="width:auto;">
					<option value=""><?php esc_html_e( 'Month', 'woocommerce-gateway-cybersource' ) ?></option>
					<?php foreach ( range( 1, 12 ) as $month ) : ?>
						<option value="<?php echo sprintf( '%02d', $month ) ?>"><?php echo sprintf( '%02d', $month ) ?></option>
					<?php endforeach; ?>
				</select>
				<select name="cybersource_expirationYear" id="cybersource_expirationYear" class="woocommerce-select woocommerce-cc-year" style="width:auto;">
					<option value=""><?php _e( 'Year', 'woocommerce-gateway-cybersource' ) ?></option>
					<?php foreach ( range( date( 'Y' ), date( 'Y' ) + 20 ) as $year ) : ?>
						<option value="<?php echo $year ?>"><?php echo $year ?></option>
					<?php endforeach; ?>
				</select>
			</p>
			<?php if ( $this->is_cvv_required() ) : ?>

				<p class="form-row form-row-last">
					<label for="cybersource_cvNumber"><?php esc_html_e( 'Card security code', 'woocommerce-gateway-cybersource') ?> <span class="required">*</span></label>
					<input type="text" class="input-text" id="cybersource_cvNumber" name="cybersource_cvNumber" maxlength="4" style="width:60px" autocomplete="off" />
				</p>
			<?php endif; ?>

			<div class="clear"></div>
		</fieldset>
		<?php
	}


	/**
	 * Process the payment and return the result
	 *
	 * @see WC_Payment_Gateway::process_payment()
	 * @param int $order_id the order identifier
	 */
	public function process_payment( $order_id ) {

		require_once( wc_cybersource()->get_plugin_path() . '/classes/class-wc-cybersource-api.php' );

		$order = wc_get_order( $order_id );

		try {

			$request = new stdClass();

			$request->merchantID = $this->get_merchant_id();

			$request->merchantReferenceCode = ltrim( $order->get_order_number(), _x( '#', 'hash before order number', 'woocommerce-gateway-cybersource' ) );

			$request->clientLibrary = "PHP";
			$request->clientLibraryVersion = phpversion();
			$request->clientEnvironment = php_uname();

			// always authorize
			$cc_auth_service = new stdClass();
			$cc_auth_service->run = "true";
			$request->ccAuthService = $cc_auth_service;

			// capture?
			if ( $this->perform_credit_card_charge() ) {
				$cc_capture_service = new stdClass();
				$cc_capture_service->run = "true";
				$request->ccCaptureService = $cc_capture_service;
			}

			$bill_to = new stdClass();
			$bill_to->firstName   = SV_WC_Order_Compatibility::get_prop( $order, 'billing_first_name' );
			$bill_to->lastName    = SV_WC_Order_Compatibility::get_prop( $order, 'billing_last_name' );
			$bill_to->company     = SV_WC_Order_Compatibility::get_prop( $order, 'billing_company' );
			$bill_to->street1     = SV_WC_Order_Compatibility::get_prop( $order, 'billing_address_1' );
			$bill_to->street2     = SV_WC_Order_Compatibility::get_prop( $order, 'billing_address_2' );
			$bill_to->city        = SV_WC_Order_Compatibility::get_prop( $order, 'billing_city' );
			$bill_to->state       = SV_WC_Order_Compatibility::get_prop( $order, 'billing_state' );
			$bill_to->postalCode  = SV_WC_Order_Compatibility::get_prop( $order, 'billing_postcode' );
			$bill_to->country     = SV_WC_Order_Compatibility::get_prop( $order, 'billing_country' );
			$bill_to->phoneNumber = SV_WC_Order_Compatibility::get_prop( $order, 'billing_phone' );
			$bill_to->email       = SV_WC_Order_Compatibility::get_prop( $order, 'billing_email' );

			if ( $order->get_user_id() ) {
				$bill_to->customerID = $order->get_user_id();
			}

			$bill_to->ipAddress = SV_WC_Order_Compatibility::get_prop( $order, 'customer_ip_address' );

			$request->billTo = $bill_to;

			$card = new stdClass();
			$card->accountNumber   = $this->get_post( 'cybersource_accountNumber' );
			$card->expirationMonth = $this->get_post( 'cybersource_expirationMonth' );
			$card->expirationYear  = $this->get_post( 'cybersource_expirationYear' );
			$card->cvNumber        = $this->get_post( 'cybersource_cvNumber' );
			$card->cardType        = $this->get_post( 'cybersource_cardType' );
			$request->card = $card;

			$purchase_totals = new stdClass();
			$purchase_totals->currency = SV_WC_Order_Compatibility::get_prop( $order, 'currency', 'view' );
			$purchase_totals->grandTotalAmount = number_format( $order->get_total(), 2, '.', '' );
			$request->purchaseTotals  = $purchase_totals;

			$items = array();

			foreach ( SV_WC_Helper::get_order_line_items( $order ) as $line_item ) {

				$item              = new stdClass();
				$item->productName = $line_item->name;

				// if we have a product object, add the SKU if available
				if ( $line_item->product instanceof WC_Product && $line_item->product->get_sku() ) {
					$item->productSKU = $line_item->product->get_sku();
				}

				$item->unitPrice = $line_item->item_total;
				$item->quantity  = $line_item->quantity;
				$item->id        = count( $items );

				$items[] = $item;
			}

			if ( ! empty( $items ) ) {
				$request->item = $items;
			}

			$enable_xdebug = false;

			if ( function_exists( 'xdebug_is_enabled' ) && xdebug_is_enabled() ) {

				$enable_xdebug = true;

				if ( function_exists( 'xdebug_disable' ) ) {
					xdebug_disable();
				}
			}

			/**
			 * Filter the CyberSource API (SoapClient) options array
			 *
			 * @since 1.6.1
			 * @param array $api_options the options array
			 */
			$api_options = apply_filters( 'wc_cybersource_api_options', array() );

			$soap_client = @new CyberSourceAPI( $this->get_endpoint_url(), $api_options );

			if ( $enable_xdebug && function_exists( 'xdebug_enable' ) ) {
				xdebug_enable();
			}

			$soap_client->set_ws_security( $this->get_merchant_id(), $this->get_transaction_key() );

			// perform the transaction
			if ( $this->log_enabled() ) {

				// if logging is enabled, log the transaction request, masking the sensitive account number
				$request->card->accountNumber = $this->mask_account( $request->card->accountNumber );

				wc_cybersource()->log( "Request:\n" . print_r( $request, true ) );

				$request->card->accountNumber = $this->get_post( 'cybersource_accountNumber' );
			}

			/**
			 * Filter the request object
			 *
			 * @since 1.6.2
			 * @param object $request the request object
			 * @param \WC_Order $order
			 */
			$request = apply_filters( 'wc_cybersource_request_object', $request, $order );

			$response = $soap_client->runTransaction( $request );

			// if debug mode load the cybersource response into the messages object
			if ( $this->is_debug_mode() ) {
				$this->response_debug_message( $response );
			}

			if ( $this->log_enabled() ) {
				wc_cybersource()->log( "Response:\n" . print_r( $response, true ) );
			}

			// store the payment information in the order, regardless of success or failure
			update_post_meta( SV_WC_Order_Compatibility::get_prop( $order, 'id' ), '_wc_cybersource_trans_id',         $response->requestID );
			update_post_meta( SV_WC_Order_Compatibility::get_prop( $order, 'id' ), '_transaction_id',                  $response->requestID );
			update_post_meta( SV_WC_Order_Compatibility::get_prop( $order, 'id' ), '_wc_cybersource_environment',      $this->get_environment() );
			update_post_meta( SV_WC_Order_Compatibility::get_prop( $order, 'id' ), '_wc_cybersource_card_type',        isset( $request->card->cardType ) ? $this->card_id_to_type[ $request->card->cardType ] : '' );
			update_post_meta( SV_WC_Order_Compatibility::get_prop( $order, 'id' ), '_wc_cybersource_account_four',     isset( $request->card->accountNumber ) ? substr( $request->card->accountNumber, -4 ) : '' );
			update_post_meta( SV_WC_Order_Compatibility::get_prop( $order, 'id' ), '_wc_cybersource_card_expiry_date', isset( $request->card->expirationMonth ) && isset( $request->card->expirationYear ) ? $request->card->expirationYear . '-' . $request->card->expirationMonth : '' );
			update_post_meta( SV_WC_Order_Compatibility::get_prop( $order, 'id' ), '_wc_cybersource_trans_date',       current_time( 'mysql' ) );

			if ( isset( $response->ccAuthReply->authorizationCode ) ) {
				update_post_meta( SV_WC_Order_Compatibility::get_prop( $order, 'id' ), '_wc_cybersource_authorization_code', $response->ccAuthReply->authorizationCode );
			}

			if ( 'ACCEPT' == $response->decision ) {

				// Successful payment:
				update_post_meta( SV_WC_Order_Compatibility::get_prop( $order, 'id' ), '_wc_cybersource_charge_captured', $this->perform_credit_card_charge() ? 'yes' : 'no' );

				$order_note = $this->is_production_environment() ?
								__( 'Credit Card Transaction Approved: %s ending in %s (%s)', 'woocommerce-gateway-cybersource' ) :
								__( 'TEST MODE Credit Card Transaction Approved: %s ending in %s (%s)', 'woocommerce-gateway-cybersource' );
				$order->add_order_note( sprintf( $order_note,
												$this->card_type_options[ $request->card->cardType ], substr( $request->card->accountNumber, -4 ), $request->card->expirationMonth . '/' . $request->card->expirationYear ) );
				$order->payment_complete();

			} elseif ( 'REVIEW' == $response->decision ) {

				// Transaction requires review:

				// admin message
				$error_message = "";

				if ( 230 == $response->reasonCode ) {
					$error_message = __( "The authorization request was approved by the issuing bank but declined by CyberSource because it did not pass the CVN check.  You must log into your CyberSource account and decline or settle the transaction.", 'woocommerce-gateway-cybersource' );
				}

				if ( $error_message ) {
					$error_message = " - " . $error_message;
				}

				// Mark on-hold
				$order_note = sprintf( __( 'Transaction requires review: code %s%s', 'woocommerce-gateway-cybersource' ), $response->reasonCode, $error_message );
				if ( ! $order->has_status( 'on-hold' ) ) {
					$order->update_status( 'on-hold', $order_note );
				} else {
					// otherwise, make sure we add the order note so we can detect when someone fails to check out multiple times
					$order->add_order_note( $order_note );
				}

				// user message:
				// specific messages based on reason code
				if ( 230 == $response->reasonCode ) {
					wc_add_notice( __( "This order is being placed on hold for review due to an incorrect card verification number.  You may contact the store to complete the transaction.", 'woocommerce-gateway-cybersource' ), 'error' );
				}

				// provide some default error message as needed
				if ( 0 == wc_notice_count( 'error' ) ) {
					wc_add_notice( __( "This order is being placed on hold for review.  You may contact the store to complete the transaction.", 'woocommerce-gateway-cybersource' ), 'error' );
				}

			} else {

				// Failure:
				// admin error message, and set status to 'failed'
				$order_note = __( 'CyberSource Credit Card payment failed', 'woocommerce-gateway-cybersource' ) . ' (Reason Code: ' . $response->reasonCode . ').';

				$this->mark_order_as_failed( $order, $order_note );

				// user error message
				switch( $response->reasonCode ) {
					case 202: wc_add_notice( __( "The provided card is expired, please use an alternate card or other form of payment.", 'woocommerce-gateway-cybersource' ), 'error' ); break;
					case 203: wc_add_notice( __( "The provided card was declined, please use an alternate card or other form of payment.", 'woocommerce-gateway-cybersource' ), 'error' ); break;
					case 204: wc_add_notice( __( "Insufficient funds in account, please use an alternate card or other form of payment.", 'woocommerce-gateway-cybersource' ), 'error' ); break;
					case 208: wc_add_notice( __( "The card is inactivate or not authorized for card-not-present transactions, please use an alternate card or other form of payment.", 'woocommerce-gateway-cybersource' ), 'error' ); break;
					case 210: wc_add_notice( __( "The credit limit for the card has been reached, please use an alternate card or other form of payment.", 'woocommerce-gateway-cybersource' ), 'error' ); break;
					case 211: wc_add_notice( __( "The card verification number is invalid, please try again.", 'woocommerce-gateway-cybersource' ), 'error' ); break;
					case 231: wc_add_notice( __( "The provided card number was invalid, or card type was incorrect.  Please try again.", 'woocommerce-gateway-cybersource' ), 'error' ); break;
					case 232: wc_add_notice( __( "That card type is not accepted, please use an alternate card or other form of payment.", 'woocommerce-gateway-cybersource' ), 'error' ); break;
					case 240: wc_add_notice( __( "The card type is invalid or does not correlate with the credit card number.  Please try again or use an alternate card or other form of payment.", 'woocommerce-gateway-cybersource' ), 'error' ); break;
				}

				// provide some default error message
				if ( 0 == wc_notice_count( 'error' ) ) {
					// decision will be ERROR or REJECT
					if ( 'ERROR' == $response->decision ) {
						wc_add_notice( __( "An error occurred, please try again or try an alternate form of payment", 'woocommerce-gateway-cybersource' ), 'error' );
					} else {
						wc_add_notice( __( "We cannot process your order with the payment information that you provided.  Please use a different payment account or an alternate payment method.", 'woocommerce-gateway-cybersource' ), 'error' );
					}
				}

				// done, stay on page and display any messages
				return;
			}

			// success or review, empty the cart and redirect to thank-you page
			WC()->cart->empty_cart();

			// Return thank you redirect
			return array(
				'result'   => 'success',
				'redirect' => $this->get_return_url( $order ),
			);

		} catch( SoapFault $e ) {

			wc_add_notice( sprintf( __( 'Connection error: "%s"', 'woocommerce-gateway-cybersource' ), $e->getMessage() ), 'error' );

			if ( $this->log_enabled() ) {
				wc_cybersource()->log( "Connection error: " . $e->getMessage() );
			}
		}

	}


	/**
	 * Validate payment form fields
	 *
	 * @see WC_Payment_Gateway::validate_fields()
	 */
	public function validate_fields() {

		$card_type        = $this->get_post( 'cybersource_cardType' );
		$account_number   = $this->get_post( 'cybersource_accountNumber' );
		$cv_number        = $this->get_post( 'cybersource_cvNumber' );
		$expiration_month = $this->get_post( 'cybersource_expirationMonth' );
		$expiration_year  = $this->get_post( 'cybersource_expirationYear' );

		// check card type
		if ( empty( $card_type ) ) {
			wc_add_notice( __( 'Please select a card type', 'woocommerce-gateway-cybersource' ), 'error' );
			return false;
		}

		if ( $this->is_cvv_required() ) {
			// check security code
			if ( empty( $cv_number ) ) {
				wc_add_notice( __( 'Card security code is missing', 'woocommerce-gateway-cybersource' ), 'error' );
				return false;
			}

			if ( ! ctype_digit( $cv_number ) ) {
				wc_add_notice( __( 'Card security code is invalid (only digits are allowed)', 'woocommerce-gateway-cybersource' ), 'error' );
				return false;
			}

			if ( ( 3 != strlen( $cv_number ) && in_array( $card_type, array( '001', '002', '004' ) ) ) || ( 4 != strlen( $cv_number ) && $card_type == '003' ) ) {
				wc_add_notice( __( 'Card security code is invalid (wrong length)', 'woocommerce-gateway-cybersource' ), 'error' );
				return false;
			}
		}

		// check expiration data
		$current_year = date( 'Y' );

		// apparently some processors will accept expired cards, so we don't check the month
		if ( ! ctype_digit( $expiration_month ) || ! ctype_digit( $expiration_year ) ||
			 $expiration_month > 12 ||
			 $expiration_month < 1 ||
			 $expiration_year < $current_year ||
			 $expiration_year > $current_year + 20
		) {
			wc_add_notice( __( 'Card expiration date is invalid', 'woocommerce-gateway-cybersource' ), 'error' );
			return false;
		}

		// check card number
		$account_number = str_replace( array( ' ', '-' ), '', $account_number );

		if ( empty( $account_number ) || ! ctype_digit( $account_number ) ||
			strlen( $account_number ) < 12 || strlen( $account_number ) > 19 ||
			! $this->luhn_check( $account_number ) ) {
			wc_add_notice( __( 'Card number is invalid', 'woocommerce-gateway-cybersource' ), 'error' );
			return false;
		}

		return true;
	}


	/** Helper methods ******************************************************/


	/**
	 * Mark the given order as failed, and set the order note
	 *
	 * @param WC_Order $order the order
	 * @param string $order_note the order note to set
	 */
	private function mark_order_as_failed( $order, $order_note ) {
		if ( ! $order->has_status( 'failed' ) ) {
			$order->update_status( 'failed', $order_note );
		} else {
			// otherwise, make sure we add the order note so we can detect when someone fails to check out multiple times
			$order->add_order_note( $order_note );
		}
	}


	/**
	 * Mask all but the first and last four digits of the given account
	 * number
	 *
	 * @param string $account_number the account number to mask
	 * @return string the masked account number
	 */
	private function mask_account( $account_number ) {
		return substr( $account_number, 0, 1 ) . str_repeat( '*', strlen( $account_number ) - 5 ) . substr( $account_number, -4 );
	}


	/**
	 * Perform standard luhn check.  Algorithm:
	 *
	 * 1. Double the value of every second digit beginning with the second-last right-hand digit.
	 * 2. Add the individual digits comprising the products obtained in step 1 to each of the other digits in the original number.
	 * 3. Subtract the total obtained in step 2 from the next higher number ending in 0.
	 * 4. This number should be the same as the last digit (the check digit). If the total obtained in step 2 is a number ending in zero (30, 40 etc.), the check digit is 0.
	 *
	 * @param string $account_number the credit card number to check
	 * @return boolean true if $account_number passes the check, false otherwise
	 */
	private function luhn_check( $account_number ) {

		$sum = 0;
		for ( $i = 0, $ix = strlen( $account_number ); $i < $ix - 1; $i++) {
			$weight = substr( $account_number, $ix - ( $i + 2 ), 1 ) * ( 2 - ( $i % 2 ) );
			$sum += $weight < 10 ? $weight : $weight - 9;
		}

		return substr( $account_number, $ix - 1 ) == ( ( 10 - $sum % 10 ) % 10 );
	}


	/**
	 * Add the cybersource POST response to the woocommerce message object
	 */
	private function response_debug_message( $response ) {

		$debug_message = '<p>' . __( 'CyberSource Response:', 'woocommerce-gateway-cybersource' ) . '</p><ul>';

		foreach ( $response as $key => $value ) {

			if ( is_object( $value ) ) {
				$debug_message .= '<li>' . $key . ' => ' . print_r( $value, true ) . '</li>';
			} else {
				$debug_message .= '<li>' . $key . ' => ' . $value . '</li>';
			}

		}

		$debug_message .= '</ul>';

		wc_add_notice( $debug_message );
	}


	/**
	 * Safely get post data if set
	 *
	 * @param string $name the name of the post variable to get
	 * @return string the value of $name, or null if not found
	 */
	private function get_post( $name ) {

		if ( isset( $_POST[ $name ] ) ) {
			return trim( $_POST[ $name ] );
		}

		return null;
	}


	/** Getter methods ******************************************************/


	/**
	 * Returns the merchant id
	 *
	 * @return string merchant id
	 */
	private function get_merchant_id() {
		return $this->merchantid;
	}


	/**
	 * Returns the WSDL endpoint URL for the current mode (test/live)
	 *
	 * @return string WSDL endpoint URL
	 */
	private function get_endpoint_url() {
		return $this->is_production_environment() ? $this->live_endpoint : $this->test_endpoint;
	}


	/**
	 * Returns the Transaction Key for the current mode (test/live)
	 *
	 * @return string transaction security key
	 */
	private function get_transaction_key() {
		return $this->is_production_environment() ? $this->transactionkeylive : $this->transactionkeytest;
	}


	/**
	 * Is test mode enabled?
	 *
	 * @return boolean true if test mode is enabled
	 */
	private function is_production_environment() {
		return 'production' == $this->get_environment();
	}


	/**
	 * Returns the environment setting, one of 'production' or 'test'
	 *
	 * @since 1.2
	 * @return string the configured environment name
	 */
	public function get_environment() {
		return $this->environment;
	}


	/**
	 * Is debug mode enabled?
	 *
	 * @return boolean true if debug mode is enabled
	 */
	private function is_debug_mode() {
		return 'both' == $this->debug_mode || 'checkout' == $this->debug_mode;
	}


	/**
	 * Should CyberSource communication be logged?
	 *
	 * @return boolean true if log mode is enabled
	 */
	private function log_enabled() {
		return 'both' == $this->debug_mode || 'log' == $this->debug_mode;
	}


	/**
	 * Returns true if the card security code is required
	 *
	 * @since 1.1.1
	 * @return boolena true if CVV is required, false otherwise
	 */
	private function is_cvv_required() {
		return 'yes' == $this->enable_csc;
	}


	/**
	 * Returns true if a credit card charge should be performed, false if an
	 * authorization should be
	 *
	 * @since 1.2
	 * @return boolean true if a charge should be performed
	 */
	public function perform_credit_card_charge() {

		return  'charge' == $this->transaction_type;
	}


	/**
	 * Returns true if a credit card authorization should be performed, false if aa
	 * charge should be
	 *
	 * @since 1.2
	 * @return boolean true if an authorization should be performed
	 */
	public function perform_credit_card_authorization() {

		return 'authorization' == $this->transaction_type;
	}


	/**
	 * Returns the CyberSource business center transaction URL for the given order
	 *
	 * @see WC_Payment_Gateway::get_transaction_url()
	 * @param WC_Order $order the order object
	 * @return string cybersource transaction url or empty string
	 */
	public function get_transaction_url( $order ) {

		// build the URL to the test/production environment
		if ( 'test' === SV_WC_Order_Compatibility::get_meta( $order, '_wc_cybersource_environment' ) ) {
			$this->view_transaction_url = "https://ebctest.cybersource.com/ebctest/transactionsearch/TransactionSearchDetailsLoad.do?requestId=%s";
		} else {
			$this->view_transaction_url = "https://ebc.cybersource.com/ebc/transactionsearch/TransactionSearchDetailsLoad.do?requestId=%s";
		}

		return parent::get_transaction_url( $order );
	}


}
