<?php
/**
 * Plugin Name: Give - Sage Payments Gateway
 * Plugin URL: 
 * Description: Give add-on gateway for Sage Payments USA
 * Version: 1.0
 * Author: Ananda
 * Author URI: 
 * Contributors: ananda
 * GitHub Plugin URI: 
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

//Constants
if ( ! defined( 'GIVE_SAGE_PAYMENTS_VERSION' ) ) {
	define( 'GIVE_SAGE_PAYMENTS_VERSION', '1.0' );
}

if ( ! defined( 'GIVE_SAGE_PAYMENTS_PLUGIN_FILE' ) ) {
	define( 'GIVE_SAGE_PAYMENTS_PLUGIN_FILE', __FILE__ );
}

if ( ! defined( 'GIVE_SAGE_PAYMENTS_PLUGIN_DIR' ) ) {
	define( 'GIVE_SAGE_PAYMENTS_PLUGIN_DIR', dirname( GIVE_SAGE_PAYMENTS_PLUGIN_FILE ) );
}

if ( ! defined( 'GIVE_SAGE_PAYMENTS_PLUGIN_URL' ) ) {
	define( 'GIVE_SAGE_PAYMENTS_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
}

if ( ! defined( 'GIVE_SAGE_PAYMENTS_BASENAME' ) ) {
	define( 'GIVE_SAGE_PAYMENTS_BASENAME', plugin_basename( __FILE__ ) );
}

//Upgrades
if ( file_exists( dirname( __FILE__ ) . '/includes/admin/give-sage-payments-upgrades.php' ) ) {
	include( dirname( __FILE__ ) . '/includes/admin/give-sage-payments-upgrades.php' );
}

//Give_Sage_Payments
final class Give_Sage_Payments {

	/** Singleton *************************************************************/

	/**
	 * @var Give_Sage The one true Give_Sage
	 * @since 1.0
	 */
	private static $instance;

	/**
	 * Main Give_Sage Instance
	 *
	 * Insures that only one instance of Give_Sage exists in memory at any one
	 * time. Also prevents needing to define globals all over the place.
	 *
	 * @staticvar array $instance
	 * @return object The one true Give_Sage
	 */
	public static function instance() {
		if ( ! isset( self::$instance ) && ! ( self::$instance instanceof Give_Sage_Payments ) ) {
			self::$instance = new Give_Sage_Payments;
			self::$instance->includes();
			self::$instance->actions();

			//Class Instances
			self::$instance->payments = new Give_Sage_Payments_Processor();

			//Admin only
			if ( is_admin() ) {

			}


		}

		return self::$instance;
	}

	/**
	 * Defines all the actions used throughout plugin
	 *
	 * @since 1.2
	 * @return void
	 */
	private function actions() {


		add_filter( 'give_payment_gateways', array( $this, 'register_gateway' ) );
		add_action( 'plugins_loaded', array( $this, 'give_add_sage_payments_licensing' ) );
		add_action( 'give_gateway_checkout_label', array( $this, 'customize_payment_label' ), 10, 2 );

	}


	/**
	 * Include all files
	 *
	 * @since 1.0.0
	 * @return void
	 */
	private function includes() {
		self::includes_general();
		self::includes_admin();
	}

	/**
	 * Load general files
	 *
	 * @return void
	 */
	private function includes_general() {
		$files = array(
			'class-give-sage-payments-processor.php',
		);

		foreach ( $files as $file ) {
			require( sprintf( '%s/includes/%s', untrailingslashit( GIVE_SAGE_PAYMENTS_PLUGIN_DIR ), $file ) );
		}
	}

	/**
	 * Load admin files
	 *
	 * @return void
	 */
	private function includes_admin() {
		if ( is_admin() ) {
			$files = array(
				'give-sage-payments-activation.php',
				'give-sage-payments-settings.php',
			);

			foreach ( $files as $file ) {
				require( sprintf( '%s/includes/admin/%s', untrailingslashit( GIVE_SAGE_PAYMENTS_PLUGIN_DIR ), $file ) );
			}
		}
	}

	// registers the gateway
	function register_gateway( $gateways ) {
		// Format: ID => Name
		$gateways['sage_payments'] = array(
			'admin_label'    => __( 'Sage Payments', 'give' ),
			'checkout_label' => __( 'Credit Card', 'give' )
		);

		return $gateways;
	}

	/**
	 * SagePayments Licensing
	 */
	public function add_sage_payments_licensing() {
		if ( class_exists( 'Give_License' ) && is_admin() ) {
			$license = new Give_License( __FILE__, 'Sage Payments Gateway', GIVE_SAGE_PAYMENTS_VERSION, 'Unknown' );
		}
	}

	/**
	 * Sage Payments Licensing
	 *
	 * @param $label
	 * @param $gateway
	 *
	 * @return string $label
	 */
	public function customize_payment_label( $label, $gateway ) {

		if ( $gateway == 'sage_payments' ) {
			$label = __( 'Credit Card', 'give' );
		}

		return $label;
	}


}

/**
 * The main function responsible for returning the one true Give_Sage
 * Instance to functions everywhere.
 *
 * Use this function like you would a global variable, except without needing
 * to declare the global.
 *
 * @since 1.1
 * @return object The one true Give_Form_Fields_Manager Instance
 */

function Give_Sage_Payments() {
	return Give_Sage_Payments::instance();
}

add_action( 'plugins_loaded', 'Give_Sage_Payments' );

