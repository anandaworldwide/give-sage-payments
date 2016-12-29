<?php
/**
 *  Give Sage Payments Gateway Activation
 *
 * @description:
 * @copyright  : http://opensource.org/licenses/gpl-2.0.php GNU Public License
 * @since      : 1.0
 */

/**
 * Give Sage Payments Activation Banner
 *
 * @description: Includes and initializes the activation banner class; only runs in WP admin
 * @hook       admin_init
 */
function give_sage_payments_activation_banner() {

	if ( defined( 'GIVE_PLUGIN_FILE' ) ) {
		$give_plugin_basename = plugin_basename( GIVE_PLUGIN_FILE );
		$is_give_active       = is_plugin_active( $give_plugin_basename );
	} else {
		$is_give_active = false;
	}

	//Check to see if Give is activated, if it isn't deactivate and show a banner
	if ( is_admin() && current_user_can( 'activate_plugins' ) && ! $is_give_active ) {

		add_action( 'admin_notices', 'give_sage_payments_child_plugin_notice' );

		//Don't let this plugin activate
		deactivate_plugins( GIVE_SAGE_PAYMENTS_BASENAME );

		if ( isset( $_GET['activate'] ) ) {
			unset( $_GET['activate'] );
		}

		return false;

	}


	//Check for activation banner inclusion
	if ( ! class_exists( 'Give_Addon_Activation_Banner' ) && file_exists( GIVE_PLUGIN_DIR . 'includes/admin/class-addon-activation-banner.php' ) ) {
		include GIVE_PLUGIN_DIR . 'includes/admin/class-addon-activation-banner.php';
	} else {
		//bail if class & file not found
		return false;
	}

	//Only runs on admin
	$args = array(
		'file'              => __FILE__,
		//Directory path to the main plugin file
		'name'              => __( 'Sage Payments Gateway', 'give-sage-payments' ),
		//name of the Add-on
		'version'           => GIVE_SAGE_PAYMENTS_VERSION,
		//The most current version
		'documentation_url' => '',
		'support_url'       => '',
		//Location of Add-on settings page, leave blank to hide
		'testing'           => false,
		//Never leave as "true" in production!!!
	);

	new Give_Addon_Activation_Banner( $args );

	return false;

}

add_action( 'admin_init', 'give_sage_payments_activation_banner' );

/**
 * Notice for No Core Activation
 */
function give_sage_payments_child_plugin_notice() {

	echo '<div class="error"><p>' . __( '<strong>Activation Error:</strong> We noticed that Give is not active. Please activate Give in order to use the Sage Payments Gateway.', 'give-sage-payments' ) . '</p></div>';
}