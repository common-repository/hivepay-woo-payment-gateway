<?php

/*
Plugin Name: HivePay Payment Gateway
Plugin URI: https://hivepay.io
Description: WooCommerce HivePay Payment Gateway
Version: 1.0
Author: HivePay.IO
Author URI: https://hivepay.io
Text Domain: hivepay-woo-payment-gateway
License:
*/

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

add_filter( 'woocommerce_payment_gateways', 'hivepay_add_gateway_class' );
function hivepay_add_gateway_class( $gateways ) {
	$gateways[] = 'WC_Gateway_Hivepay';
	return $gateways;
}

add_action( 'plugins_loaded', 'hivepay_init_gateway_class' );
function hivepay_init_gateway_class() {
	define( 'HIVEPAY_URI', plugin_dir_url( __FILE__ ) );
	class WC_Gateway_Hivepay extends WC_Payment_Gateway {
		
		/**
		 * Whether or not logging is enabled
		 *
		 * @var bool
		 */
		public static $log_enabled = false;
		
		/**
		 * Logger instance
		 *
		 * @var WC_Logger
		 */
		public static $log = false;
		
		
		public $icon = HIVEPAY_URI.'images/hivepay_logo.png';
		
		/**
		 * Class constructor
		 */
		public function __construct() {
			
			$this->id = 'hivepay';
			$this->has_fields = false;
			$this->method_title = __( 'Hivepay Gateway', 'hivepay-gateway' );
			$this->method_description = __( 'HivePay Payment Gateway for WooCommerce', 'woocommerce-hivepay' );
			
			$this->supports = array(
				'products'
			);
			
			$this->init_form_fields();
			
			$this->init_settings();
			$this->title = $this->get_option( 'title' );
			$this->icon = $this->get_option( 'icon' );
			$this->description = $this->get_option( 'description' );
			$this->instructions = $this->get_option( 'instructions' );
			$this->enabled = $this->get_option( 'enabled' );
			$this->debug = $this->get_option( 'debug' );
			$this->merchant = $this->get_option( 'merchant' );
			$this->merchant_name = $this->get_option( 'merchant_name' );
			$this->merchant_email = $this->get_option( 'merchant_email' );
			$this->pay_currency = $this->get_option( 'pay_currency' );
			self::$log_enabled    = $this->debug;
			
			add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
			add_action( 'woocommerce_thankyou_' . $this->id, array( $this, 'thankyou_page' ) );
			add_action( 'woocommerce_api_hivepayipn', array( $this, 'webhook' ) );
		}
		
		public function init_form_fields(){
			
			$this->form_fields = array(
				'enabled' => array(
					'title'       => __( 'Enable/Disable', 'woocommerce-hivepay' ),
					'label'       => __( 'Enable HivePay Gateway', 'woocommerce-hivepay' ),
					'type'        => 'checkbox',
					'description' => '',
					'default'     => 'no'
				),
				'debug'                 => array(
					'title'       => __( 'Debug log', 'woocommerce-hivepay' ),
					'type'        => 'checkbox',
					'label'       => __( 'Enable logging', 'woocommerce-hivepay' ),
					'default'     => 'no',
					/* translators: %s: URL */
					'description' => sprintf( __( 'Log HivePay events, such as IPN requests, inside <br/> %s <br/>Note: this may log personal information. We recommend using this for debugging purposes only and deleting the logs when finished.', 'woocommerce-hivepay' ), '<code>' . WC_Log_Handler_File::get_log_file_path( 'hivepay' ) . '</code>' ),
				),
				'icon' => array(
					'title'       => __( 'Icon URL', 'woocommerce-hivepay' ),
					'type'        => 'text',
					'description' => __( 'This controls the icon which the user sees during checkout. Don\'t show if empty', 'woocommerce-hivepay' ),
					'default'     => HIVEPAY_URI.'images/woocommerce.png',
				),
				'title' => array(
					'title'       => __( 'Title', 'woocommerce-hivepay' ),
					'type'        => 'text',
					'description' => __( 'This controls the title which the user sees during checkout.', 'woocommerce-hivepay' ),
					'default'     => 'HivePay',
				),
				'description' => array(
					'title'       => __( 'Description', 'woocommerce-hivepay' ),
					'type'        => 'textarea',
					'description' => __( 'This controls the description which the user sees during checkout.', 'woocommerce-hivepay' ),
					'default'     => 'Pay with Hive and Hive-Engine Tokens.',
				),
				'instructions'       => array(
					'title'       => __( 'Instructions', 'woocommerce-hivepay' ),
					'type'        => 'textarea',
					'description' => __( 'Instructions that will be added to the thank you page.', 'woocommerce-hivepay' ),
					'default'     => __( '', 'woocommerce' ),
				),
				'merchant' => array(
					'title'       => __( 'HIVE Username', 'woocommerce-hivepay' ),
					'type'        => 'text'
				),
				'merchant_name' => array(
					'title'       => __( 'Business name', 'woocommerce-hivepay' ),
					'type'        => 'text',
					'description' => __( 'Will be shown on HivePay checkout page as BUSINESSNAME(@HIVEUSERNAME).', 'woocommerce-hivepay' ),
				),
				'merchant_email' => array(
					'title'       => __( 'Merchant email (optional)', 'woocommerce-hivepay' ),
					'type'        => 'text',
					'description' => __( 'If you want to receive emails upon payment completion', 'woocommerce-hivepay' ),
				),
				'pay_currency' => array(
					'title'       => __( 'Pay Tokens', 'woocommerce-hivepay' ),
					'type'        => 'text',
					'description' => __( 'Comma separated list of accepted Hive/Hive-Engine Tokens. All if empty. (CTP,HIVE,LEO,TOP10T)', 'woocommerce-hivepay' ),
					'default' => ''
				),
			);
		
		}
		
		/**
		 * Output for the order received page.
		 */
		public function thankyou_page() {
			if ( $this->instructions ) {
				echo wp_kses_post( wpautop( wptexturize( $this->instructions ) ) );
			}
		}
		
		public function validate_fields() {
			return true;
		}
		

		public function process_payment( $order_id ) {
			
			$order = wc_get_order( $order_id );
			$order_items = $order->get_items( array( 'line_item', 'shipping' ) );
			$items = array( );
			
			if ( !is_wp_error( $order_items ) ) {
				foreach ( $order_items as $item ) {
					if ( $item->get_type() === 'shipping' ) {
						$items[0]['shipping'] = number_format( (float) $item->get_total( ), 2, '.', '' );
					} else {
						$temp_item = array(
							'name' => $this->get_order_item_name( $order, $item ), //$item->get_name( ),
							'description' => null,
							'amount' => number_format( (float) $item->get_total( ) / $item->get_quantity( ), 2, '.', '' ),
							'quantity' => $item->get_quantity( ),
							'image' => null,
							'shipping' => null,
						);
						$items[] = $temp_item;
					}

				}
			}
			
			$hivepay_order_sign = wp_generate_password(15, false);
			$notify_url = WC()->api_request_url( 'hivepayipn' );
			$return_url = $this->get_return_url( $order );
			$cancel_url = $order->get_cancel_order_url_raw( );
			
			$data = array(
				'hivepay.checkout.create' => array(
					'notify_url' => $notify_url,
					'return_url' => $return_url,
					'cancel_url' => $cancel_url,
					'merchant' => $this->merchant,
					'base_currency' => get_woocommerce_currency(),
					'memo' => 'Order #' . $order_id,
					'items' => $items,
					'metadata' => array(
						'wc_order_id' => $order_id,
						'wc_order_key' => $order->get_order_key(),
						'hivepay_order_sign' => $hivepay_order_sign,
					),
					'additional_fields' => array(),
					'merchant_name' => ! empty( $this->merchant_name ) ? $this->merchant_name : null, // optional
					'merchant_email' => ! empty( $this->merchant_email ) ? $this->merchant_email : null, // optional
					'pay_currency' => $this->pay_currency,
					'require_shipping_address' => false,
				)
			);
			
			WC_Gateway_Hivepay::log(
				'hivepay.checkout.request, Order #' . $order->get_id()
				, 'info'
				, $data
			);
			
			$order->add_meta_data( 'hivepay.checkout.request', $data, true );
			$order->save();
			
			$response = wp_remote_post( 'https://api.hivepay.io',
				array(
					'headers' => array('Content-Type' => 'application/json'),
					'body' => json_encode( $data ),
				)
			);
			
			if( !is_wp_error( $response ) ) {
				
				$body = json_decode( $response['body'], true );
				
				if ( json_last_error() ) {
					WC_Gateway_Hivepay::log(
						'hivepay.checkout.response, Order #' . $order->get_id() . ' | JSON Error: ' . json_last_error_msg()
						, 'error'
						, $response['body']
					);
					wc_add_notice( 'JSON Error: ' . json_last_error_msg(), 'error' );
					return false;
				}
				
				if ( isset($body['error']) ) {
					WC_Gateway_Hivepay::log(
						'hivepay.checkout.response, Order #' . $order->get_id() . ' | Error: ' . $body['error']
						, 'error'
						, $body
					);
					wc_add_notice( $body['error'], 'error' );
					return false;
				}
				
				WC_Gateway_Hivepay::log(
					'hivepay.checkout.response, Order #' . $order->get_id()
					, 'info'
					, $body
				);
				
				$order->add_meta_data( 'hivepay_order_sign', $hivepay_order_sign, true );
				$order->add_meta_data( 'hivepay.checkout.response', $body, true );
				$order->save();
				
				return array(
					'result' => 'success',
					'redirect' => 'https://hivepay.io/checkout/?id=' . $body['id']
				);
				
			}
			WC_Gateway_Hivepay::log(
				'hivepay.checkout.response, Order #' . $order->get_id() . ' | Connection error. '
				, 'error'
			);
			
			wc_add_notice(  'Connection error.', 'error' );
			return false;
		}
		
		/*
		 * IPN
		 */
		public function webhook() {
			$body = file_get_contents('php://input');
			$data = json_decode($body, TRUE);
			
			if ( ! json_last_error() ) {
				$payment_details = isset( $data['payment_details'] ) ? $data['payment_details'] : false;
				$order = $this->get_hivepay_order( $data );
				
				if ( $order ) {
					if ( $order->has_status( wc_get_is_paid_statuses() ) ) {
						WC_Gateway_Hivepay::log(
							'Aborting, Order #' . $order->get_id() . ' is already complete.',
							'info',
							$data
						);
						die();
					}

					$verification_result = $this->verifyHivePay( $data, $order );
					
					if ( $verification_result === true ) {
						WC_Gateway_Hivepay::log(
							'Payment compete, Order #' . $order->get_id()
							, 'info'
							, $data
						);
						
						$order->payment_complete( $payment_details['txid'] );
						$order->add_meta_data( 'hivepay_ipn_details', $data, true );
						$order->save();
						WC()->cart->empty_cart();
					} else {
						
						WC_Gateway_Hivepay::log(
							'Verification error, Order #' . $order->get_id() . ' ' . $verification_result['error']
							, 'error'
							, $data
						);
						WC_Gateway_Hivepay::log(
							'Verification error, Order #' . $order->get_id() . ' ' . $verification_result['error']
							, 'error'
							, $verification_result
						);
						
					}
				} else {
					WC_Gateway_Hivepay::log(
						'Order ID and key were not found in meta.'
						, 'error'
						, $data
					);
					
				}
			} else {
				WC_Gateway_Hivepay::log( 'IPN body error', 'error', $body );
			}
			
			die();
		}
		
		private function verifyHivePay( $data, $order ) {
			
			$payment_details = $data['payment_details'];
			
			$verify = array(
				'hivepay_ipn' => 'notification',
				'txid' => $payment_details['txid'],
				'token' => $payment_details['token'],
				'token_amount' => $payment_details['token_amount'],
				'buyer' => $payment_details['buyer'],
				'merchant' => $payment_details['merchant'],
				'amount_received' => $payment_details['amount_received']
			);
			
			$payload = json_encode( array( 'verify_data' => $verify ) );
			
			$response = wp_remote_post( 'https://hivepay.io/verify/',
				array(
					'headers' => array('Content-Type' => 'application/json'),
					'body' => $payload,
				)
			);

			if( !is_wp_error( $response ) ) {
				
				$body = json_decode( $response['body'], true );
				
				if ( json_last_error() ) {
					return array(
						'error' => 'Verify JSON Error: ' . json_last_error_msg(),
						$response['body']
					);
				}
				
				if ( isset($body['error']) ) {
					return array('error' => 'Verify error: ' . $body['error'], $body );
				}
				
				 if ( $payment_details['txid'] == $body['verify_txid']
					 && $order->get_meta( 'hivepay_order_sign' , true ) == $data['metadata']['hivepay_order_sign']
				 ) {
				    return true;
				 }
				
			} else {
				return array('error' => 'Verify error', $response );
			}

			return array('error' => 'Verify error', $response );
		}
		
		
		/**
		 * Get the order from the HivePay metadata
		 *
		 * @param  array $data
		 * @return bool|WC_Order object
		 */
		protected function get_hivepay_order( $data ) {

			if ( $data && is_array( $data ) ) {
				$order_id  = isset( $data['metadata']['wc_order_id'] ) ? $data['metadata']['wc_order_id'] : '';
				$order_key = isset( $data['metadata']['wc_order_key'] ) ? $data['metadata']['wc_order_key'] : '';
			} else {
				// Nothing was found.
				return false;
			}
			
			$order = wc_get_order( $order_id );
			
			if ( ! $order ) {
				// We have an invalid $order_id, probably because invoice_prefix has changed.
				$order_id = wc_get_order_id_by_order_key( $order_key );
				$order    = wc_get_order( $order_id );
			}
			
			if ( ! $order OR ! hash_equals( $order->get_order_key(), $order_key ) ) {
				WC_Gateway_Hivepay::log(
					'Order Keys do not match.'
					, 'error'
				);
				return false;
			}
			
			return $order;
		}
		
		/**
		 * Get order item names as a string.
		 *
		 * @param  WC_Order      $order Order object.
		 * @param  WC_Order_Item $item Order item object.
		 * @return string
		 */
		protected function get_order_item_name( $order, $item ) {
			$item_name = $item->get_name();
			$item_meta = wp_strip_all_tags(
				wc_display_item_meta(
					$item,
					array(
						'before'    => '',
						'separator' => ', ',
						'after'     => '',
						'echo'      => false,
						'autop'     => false,
					)
				)
			);
			
			if ( $item_meta ) {
				$item_name .= ' - ' . $item_meta . '';
			}
			
			return apply_filters( 'woocommerce_hivepay_get_order_item_name', $item_name, $order, $item );
		}
		
		/**
		 * Logging method.
		 *
		 * @param string $message Log message.
		 * @param string $level Optional. Default 'info'. Possible values:
		 *                      emergency|alert|critical|error|warning|notice|info|debug.
		 * @param string|array $additional
		 */
		public static function log( $message, $level = 'info', $additional = null ) {
			if ( self::$log_enabled ) {
				if ( empty( self::$log ) ) {
					self::$log = wc_get_logger();
				}
				self::$log->log( $level, $message, array( 'source' => 'hivepay' ) );
				if ( isset( $additional ) ) {
					self::$log->log( $level, 'Additional: ' . json_encode( $additional ), array( 'source' => 'hivepay' ) );
				}
			}
		}
	}
}