<?php

/**
 *  Give_Sage_Payments_Processor
 *
 * @description:
 * @copyright  : http://opensource.org/licenses/gpl-2.0.php GNU Public License
 * @since      : 1.0
 */
class Give_Sage_Payments_Processor {
	
	/**
	 * Configuration variables
	 *
	 * @var string $verb
	 * @var string $url
	 */
	private $verb = "POST";
	private $url = "https://api-cert.sagepayments.com/bankcard/v1/charges?type=Sale";

	/**
	 * Configuration options
	 *
	 * @var string $merchant_id
	 * @var string $merchant_key
	 * @var string $recurring_group_id
	 * @var string $developer_client_id
	 * @var string $developer_client_secret
	 */
	private $merchant_id = '';
	private $merchant_key = '';
	private $recurring_group_id = '';
	private $developer_client_id = '';
	private $developer_client_secret = '';
	
	/**
	 * Stored information
	 *
	 * @var object $payment_data
	 * @var int $payment_id
	 */
	private $payment_data = null;
	private $payment_id = 0;

	/**
	 * Hook to actions
	 */
	function __construct() {

		add_action( 'give_gateway_sage_payments', array( $this, 'give_process_sage_payments_payment' ), 10, 1 );
		add_action( 'give_sage_payments_cc_form', array( $this, 'optional_billing_fields' ), 10, 1 );

	}

	/**
	 * Process donation through Sage Payments
	 *
	 * - If it succeeds, redirect to the success page.
	 * - If it fails, set errors and send the user back to the donation form.
	 *
	 * @since 1.0
	 *
	 * @param $purchase_data
	 */
	public function give_process_sage_payments_payment( $purchase_data ) {

		/**
		 * Setup
		 */
		if ( ! $this->validate_card_information() ) {
			give_send_back_to_checkout( '?payment-mode=' . $purchase_data['post_data']['give-gateway'] );
			return;
		}
			
		if ( ! $this->get_configuration_values() ) {
			return; // Exit the function if values were missing	
		}
		
		// Store donation data
		$this->payment_data = $purchase_data;
		
		/**
		 * Send data to API and get the response
		 */
		$request_data = $this->get_donation_request_object( $this->payment_data );
		$context = $this->get_stream_context( $request_data );
		$result = file_get_contents( $this->url, false, $context );
		
		/**
		 * @var object $reponse The response object.
		 * @see https://developer.sagepayments.com/bankcard/apis/post/charges for documentation
		 */
		$response = json_decode( $result );		
		
		/**
		 * Handle error or success
		 */
		if ( isset( $response->code ) && ( false === strpos( $response->message, 'APPROVED' ) ) ) {
			
			$this->handle_response_error( $response );
			
		} else {
			
			$payment = $this->insert_payment_data();
			
			if ( $payment ) {
				
				give_update_payment_status( $payment, 'publish' );
				give_send_to_success_page();
				
			} else {
				
				// There's a problem here in that the card has been processed by this point. Sending them back to donate
				// again isn't a good idea.
				give_set_error( 'authorize_error', __( 'Error: The donation was successful, but the payment could not be recorded.', 'give' ) );
				//give_send_back_to_checkout( '?payment-mode=' . $purchase_data['post_data']['give-gateway'] );
				
				give_send_to_success_page();
				
			}
			
		}
		
	}
	
	/**
	 * Check to see if there are errors with the card data
	 *
	 * @since 1.0
	 * 
	 * @return bool False if there are errors.
	 */
	private function validate_card_information() {
		
		if ( ! isset( $_POST['card_number'] ) || $_POST['card_number'] == '' ) {
			give_set_error( 'empty_card', __( 'You must enter a card number', 'give' ) );
		}
		if ( ! isset( $_POST['card_name'] ) || $_POST['card_name'] == '' ) {
			give_set_error( 'empty_card_name', __( 'You must enter the name on your card', 'give' ) );
		}
		if ( ! isset( $_POST['card_exp_month'] ) || $_POST['card_exp_month'] == '' ) {
			give_set_error( 'empty_month', __( 'You must enter an expiration month', 'give' ) );
		}
		if ( ! isset( $_POST['card_exp_year'] ) || $_POST['card_exp_year'] == '' ) {
			give_set_error( 'empty_year', __( 'You must enter an expiration year', 'give' ) );
		}
		if ( ! isset( $_POST['card_cvc'] ) || $_POST['card_cvc'] == '' || strlen( $_POST['card_cvc'] ) < 3 ) {
			give_set_error( 'empty_cvc', __( 'You must enter a valid CVC', 'give' ) );
		}

		$card_errors = give_get_errors();
		
		if ( $card_errors ) return false;
		
		return true;
		
	}


	/**
	 * Get configuration values and store them as properties
	 *
	 * @since 1.0
	 *
	 * @return bool False if data is missing.
	 */
	private function get_configuration_values() {
		
		/**
		 * Merchant information
		 */
		if ( ! give_is_test_mode() ) {
			
			// LIVE
			$this->merchant_id = give_get_option( 'give_sage_payments_merchant_id' );
			$this->merchant_key = give_get_option( 'give_sage_payments_merchant_key' );
			
		} else {
			
			// TEST
			$this->merchant_id = give_get_option( 'give_sage_payments_test_merchant_id' );
			$this->merchant_key = give_get_option( 'give_sage_payments_test_merchant_key' );
			
		}
		
		$this->recurring_group_id = give_get_option( 'give_sage_payments_recurring_group_id' );
		
		// Validate merchant information
		if ( empty( $this->merchant_id ) || empty( $this->merchant_key ) ) {

			give_set_error( 'error_id_here', __( 'Error: Missing merchant ID or key. Please enter them in the plugin settings.', 'give-sage-payments' ) );
			return false;

		}
		
		/**
		 * Developer API information
		 */
		$this->developer_client_id = give_get_option( 'give_sage_payments_client_id' );
		$this->developer_client_secret = give_get_option( 'give_sage_payments_client_secret' );
		
		// Validate developer API information
		if ( empty( $this->developer_client_id ) || empty( $this->developer_client_secret ) ) {

			give_set_error( 'error_id_here', __( 'Error: Missing Client ID or Client Secret. These are part of the developer API, and you\'ll need to enter them in the plugin settings.', 'give-sage-payments' ) );
			return false;

		}
		
		return true;
			
	}
	
	/**
	 * Return a generic object with the data needed for the API request
	 *
	 * @since 1.0
	 * 
	 * @param object $purchase_data
	 * @return object
	 */
	private function get_donation_request_object( $purchase_data ) {
		
		/**
		 * Card info
		 */
		$card_info  = $purchase_data['card_info'];
		$card_names = explode( ' ', $card_info['card_name'] );
		
		$first_name = isset( $card_names[0] ) ? $card_names[0] : $purchase_data['user_info']['first_name'];
		if ( ! empty( $card_names[1] ) ) {
			unset( $card_names[0] );
			$last_name = implode( ' ', $card_names );
		} else {
			$last_name = $purchase_data['user_info']['last_name'];
		}
		
		// $description = give_get_purchase_summary( $purchase_data );
		
		$expiration_month = str_pad( strip_tags( trim( $card_info['card_exp_month'] ) ), 2, "0", STR_PAD_LEFT ); // e.g. "08" and not "8"
		$expiration_year = substr( strip_tags( trim( $card_info['card_exp_year'] ) ), -2 ); // e.g. "19" and not "2019"
		
		$expiration = $expiration_month . $expiration_year; // e.g. 0819 for 08/2019
		
		/**
		 * Recurring info
		 *
		 * If it's a recurring pledge, get that information and set it up
		 */
		$is_recurring = false;
		
		if ( isset( $purchase_data["period"] ) ) {
			
			$is_recurring = true;
			
			/**
			 * @var string $recurring_period
			 * @var string $recurring_times
			 * @var string $recurring_length
			 */
			$recurring_period = $purchase_data["period"]; // "month"
			$recurring_times = $purchase_data["times"]; // "0", this probably has to do with recurring
			$recurring_length = $purchase_data["pledge_monthly_for"][0]; // e.g. "2 years", "Until the temple is finished"
			
			/**
			 * @var string $recurring_start_date
			 */
			$recurring_start_date = $timestamp;
						
			// Turn recurring length into usable data
			if ( $recurring_times <= 0 ) {
				
				if ( $recurring_length == '2 years' ) {
					$recurring_times = 24;
				} elseif ( $recurring_length == '1 year' ) {
					$recurring_times = 12;
				} else {
					$recurring_times = null; // null is infinite
				}
				
			}
			
		}
		 
		/**
		 * Create the request
		 * 
		 * @see https://developer.sagepayments.com/bankcard/apis/post/charges
		 */
		$request_data = [
			"transactionID" => uniqid(),
		    "eCommerce" => [
		        "orderNumber" => $purchase_data['purchase_key'],
		        "amounts" => [
		            "total" => $purchase_data['price']
		        ],
		        "cardData" => [
		            "number" => strip_tags( trim( $card_info['card_number'] ) ),
		            "expiration" => $expiration, // e.g. 0819
		            "cvv" => strip_tags( trim( $card_info['card_cvc'] ) )
		            // priorReference: The presence of this value indicates that Number is an encrypted number belonging to the transaction with this reference. 
		        ],
		        "customer" => [
			        "email" => $purchase_data['user_email'],
			        // "telephone" => 
		        ],
		        "billing" => [
			        "name" => $first_name . ' ' . $last_name,
			        "address" => $card_info['card_address'] . ' ' . $card_info['card_address_2'],
			        "city" => $card_info['card_city'],
			        "state" => $card_info['card_state'],
			        "postalCode" => $card_info['card_zip'],
			        "country" => $card_info['card_country']
		        ],
		        "isRecurring" => $is_recurring
		    ]
		];
		
		// Set up recurring information
		if ( $is_recurring ) {
			
			$request_data[ "eCommerce" ]["recurringSchedule"] = [
	            "amount" => $purchase_data['price'],
	            "frequency" => "", // What should this be?
	            "interval" => 1, // Once per time period
	            "nonBusinessDaysHandling" => 2, // 0 = After, 1 = Before, 2 = That Day
	            "startDate" => $recurring_start_date,
	            "totalCount" => $recurring_times,
	            "groupId" => $this->recurring_group_id
			];
			
		}
		
		return $request_data;
		
	}
	
	/**
	 * Return the context information for the file_open_url() (headers, etc.)
	 *
	 * @since 1.0
	 * 
	 * @param object $request_data
	 * @return object
	 */
	private function get_stream_context( $request_data ) {
		
		/**
		 * Set up environment for the request
		 */
		// the nonce can be any unique identifier -- guids and timestamps work well
		$nonce = uniqid();
		
		// a standard unix timestamp. a request must be received within 60s
		// of its timestamp header.
		$timestamp = (string)time();
		
		// convert to json for transport
		$payload = json_encode( $request_data );
		
		// the request is authorized via an HMAC header that we generate by
		// concatenating certain info, and then hashing it using our client key
		$to_be_hashed = $this->verb . $this->url . $payload . $this->merchant_id . $nonce . $timestamp;
		$hmac = $this->get_hmac( $to_be_hashed, $this->developer_client_secret );
		
		// ok, let's make the request! cURL is always an option, of course,
		// but i find that file_get_contents is a bit more intuitive.
		$config = [
		    "http" => [
		        "header" => [
		            "clientId: " . $this->developer_client_id,
		            "merchantId: " . $this->merchant_id,
		            "merchantKey: " . $this->merchant_key,
		            "nonce: " . $nonce,
		            "timestamp: " . $timestamp,
		            "authorization: " . $hmac,
		            "content-type: application/json",
		        ],
		        "method" => $this->verb,
		        "content" => $payload,
		        "ignore_errors" => true // exposes response body on 4XX errors
		    ]
		];
		
		$context = stream_context_create( $config );
		
		return $context;
		
	}
	
	/**
	 * Get an authorization token (HMAC), described as:
	 * verb + url + body + merchantId + nonce + timestamp  
	 *
	 * @since 1.0
	 * @link https://github.com/SagePayments/Direct-API/blob/master/php/shared.php
	 */
	private function get_hmac( $toBeHashed, $privateKey ) {
   
	    $hmac = hash_hmac(
	        "sha512", // use the SHA-512 algorithm...
	        $toBeHashed, // ... to hash the combined string...
	        $privateKey, // .. using your private dev key to sign it.
	        true // (php returns hexits by default; override this)
	    );
	    // convert to base-64 for transport
	    $hmac_b64 = base64_encode($hmac);
	    return $hmac_b64;
	    
	}
	
	/**
	 * Handle response error
	 *
	 * - Set errors
	 * - Log error (email)
	 * - Return user to donate form
	 *
	 * @since 1.0
	 * 
	 * @param object $response
	 */
	private function handle_response_error( $response ) {
		
		// We need the payment data to be available for email_admins_on_error()'s do_action( 'give_admin_donation_email' )
		$this->insert_payment_data( 'failed' );
		
		/**
		 * Error Codes
		 *
		 * @var string $code The error code
		 * 
		 * @see https://developer.sagepayments.com/docs/errors
		 */
		$error_code = $response->code;
		
		/**
		 * @var string $default_donor_message
		 */
		$default_donor_message = __( "There was an error processing your card. We apologize for the inconvenience. Try again, and if you continue to have problems, please contact us.", 'give' );
		
		/**
		 * @var string $default_admin_message May contain sensitive security information and / or be confusing.
		 */
		if ( $response->info ) {
			$default_admin_message = "<a href='" . $response->info . "'>" . __( "Error", 'give' ) . " " . $error_code . "</a>: ";
		} else {	
			$default_admin_message = __( "Error ", 'give' ) . $error_code . ": ";
		}
		
		$default_admin_message .= $response->message;
		
		if ( $response->detail ) {
			$default_admin_message .= "\r\n" . $response->detail . "\r\n";
			$default_admin_message = str_replace( "Please see 'detail' for more.", '', $default_admin_message ); // Since we're adding the detail info, remove this message
		}
		
		/**
		 * Send an email to admins about the error with the full information
		 */
		give_set_error( 'error', $default_admin_message );
		$this->email_admins_on_error();
		
		/*
			Other possibilities for give_set_error(), for reference.

			if ( strpos( strtolower( $error ), 'the credit card number is invalid' ) !== false ) {
				give_set_error( 'invalid_card', __( 'Your card number is invalid', 'give' ) );
			} elseif ( strpos( strtolower( $error ), 'this transaction has been declined' ) !== false ) {
				give_set_error( 'invalid_card', __( 'Your card has been declined', 'give' ) );
			} elseif ( isset( $response->response_reason_text ) ) {
				give_set_error( 'api_error', $response->response_reason_text );
			} elseif ( isset( $response->error_message ) ) {
				give_set_error( 'api_error', $response->error_message );
			} else {
				give_set_error( 'api_error', sprintf( __( 'An error occurred. Error data: %s', 'give' ), print_r( $response, true ) ) );
			}
		*/
		
		/**
		 * Set the error messages for the user.
		 * 
		 * This lets us customize the error messages if desired.
		 *
		 * @link https://developer.sagepayments.com/docs/errors
		 */
		give_clear_errors();
		
		if ( false !== strpos( $response->message, 'DECLINED' ) ) {
			
			give_set_error( 'card_error', __( "Your card was declined. If you think you shouldn't be getting this error, please try again. Let us know if you continue to have trouble.", 'give' ) );
			
		} elseif ( false !== strpos( $response->message, 'CARD EXP' ) ) {
			
			give_set_error( 'card_error', __( "The credit card expiration date had an error. If you think you shouldn't be getting this notice, please let us know.", 'give' ) );
			
		} else {
			
			// Write error responses in a more helpful and friendly way
			switch ( $error_code ) {
				
				case '910003':
					give_set_error( 'card_error', __( "We're sorry, we aren't able to accept American Express cards right now.", 'give' ) );
					break;
				
				case '910004':
					give_set_error( 'card_error', __( "We're sorry, we aren't able to accept Discover cards right now.", 'give' ) );
					break;
				
				// Most errors are either irrelevant to the user or could be a security hazard
				default:
					give_set_error( 'generic_error', $default_donor_message );
					break;
					
			}
			
		}
		
		/**
		 * Return to donation page
		 */
		give_send_back_to_checkout( '?payment-mode=' . $purchase_data['post_data']['give-gateway'] );
		
	}
	
	/**
	 * Handle response success
	 *
	 * @since 1.0
	 * @param string $status
	 * @return int The payment ID
	 */
	private function insert_payment_data( $status = 'pending' ) {
		
		$purchase_data = $this->payment_data;
			
		$payment_data = array(
			'price'           => $purchase_data['price'],
			'give_form_title' => $purchase_data['post_data']['give-form-title'],
			'give_form_id'    => intval( $purchase_data['post_data']['give-form-id'] ),
			'price_id'        => isset( $purchase_data['post_data']['give-price-id'] ) ? intval( $purchase_data['post_data']['give-price-id'] ) : '',
			'date'            => $purchase_data['date'],
			'user_email'      => $purchase_data['user_email'],
			'purchase_key'    => $purchase_data['purchase_key'],
			'currency'        => give_get_currency(),
			'user_info'       => $purchase_data['user_info'],
			'status'          => $status,
			'gateway'         => 'sage_payments'
		);

		$this->payment_id = give_insert_payment( $payment_data );
		
		return $this->payment_id;
		
	}
	
	/**
	 * Email admins when there is an error
	 *
	 * @since 1.0
	 *
	 * @param int $payment_id Payment ID (default: 0)
	 * @param array $payment_data Payment Meta and Data
	 */
	private function email_admins_on_error() {
	
		/**
		 * Filters the donation notification subject.
		 *
		 * @since 1.0
		 */
		add_filter( 'give_admin_donation_notification_subject', function( $subject, $payment_id ) {
			return "Donation Error: Donation #{$payment_id}";
		}, 100, 2 );
		
		/**
		 * Replaces the email heading
		 *
		 * @since 1.0
		 */
		add_filter( 'give_email_heading', function( $heading ) {
			return "Donation Error";
		});
		
		/**
		 * Filters the donation notification content.
		 *
		 * @since 1.0
		 * @see give_get_donation_notification_body_content() $default_email_body copied from here
		 */
		add_filter( 'give_donation_notification', function( $email_body, $payment_id, $payment_data ) {

			$default_email_body = esc_html__( 'Hello', 'give' ) . "\n\n";
			$default_email_body .= esc_html__( 'A donation has been made.', 'give' ) . "\n\n";
			$default_email_body .= esc_html__( 'Donation:', 'give' ) . "\n\n";
			$default_email_body .= esc_html__( 'Donor:', 'give' ) . ' ' . html_entity_decode( $name, ENT_COMPAT, 'UTF-8' ) . "\n";
			$default_email_body .= esc_html__( 'Amount:', 'give' ) . ' ' . html_entity_decode( give_currency_filter( give_format_amount( give_get_payment_amount( $payment_id ) ) ), ENT_COMPAT, 'UTF-8' ) . "\n";
			$default_email_body .= esc_html__( 'Payment Method:', 'give' ) . ' ' . $gateway . "\n\n";
			$default_email_body .= esc_html__( 'Thank you', 'give' );
		
			$email = give_get_option( 'donation_notification' );
			$email = isset( $email ) ? stripslashes( $email ) : $default_email_body;	
			
			$email_body = give_do_email_tags( $email, $payment_id );
			
			$errors = give_get_errors();
			
			if ( $errors ) {
				
				$email_body .= __( 'Errors', 'give' ) . "\n\n";
				
				foreach( $errors as $id => $error ) {
					$email_body .= $error . "\n";
				}
				
			} else {
				$email_body .= __( "Errors seem to have happened during the donation, but we don't know what they are.", 'give' );
			}
	
			return wpautop( $email_body );
			
		}, 100, 3 );
		
		/**
		 * Send the admin donation email, but filtered to show errors
		 */
		do_action( 'give_admin_donation_email', $this->get_payment_id(), $this->payment_data );
		
	}
	
	/**
	 * Return the payment ID based on the payment data
	 * 
	 * @since 1.0
	 *
	 * @return int Payment ID
	 */
	private function get_payment_id() {
		
		if ( $this->payment_id ) {
			return absint( $this->payment_id );
		} else {
			return 0;
		}
		
	}

	/**
	 * Optional Billing Fields
	 *
	 * @since 1.0
	 *
	 * @param $form_id
	 */
	public function optional_billing_fields( $form_id ) {

		//Remove Address Fields if user has option enabled
		if ( ! give_get_option( 'sage_payments_collect_billing' ) ) {
			remove_action( 'give_after_cc_fields', 'give_default_cc_address_fields' );
		}

		//Ensure CC field is in place properly
		do_action( 'give_cc_form', $form_id );

	}


}

