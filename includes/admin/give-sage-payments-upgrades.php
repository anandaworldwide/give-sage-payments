<?php

/**
 * Sage Payments Upgrades
 *
 * @package     Give
 * @copyright   Copyright (c) 2015, WordImpress
 * @license     http://opensource.org/licenses/gpl-2.0.php GNU Public License
 * @since       1.2
 */
class Give_Sage_Payments_Upgrades {

	public function __construct() {
		//Activation
		register_activation_hook( GIVE_SAGE_PAYMENTS_PLUGIN_FILE, array( $this, 'version_check' ) );
	}

	/**
	 * Version check
	 */
	public function version_check() {

		$previous_version = get_option( 'give_sage_payments_version' );

		//No version option saved
		if ( version_compare( '1.2', $previous_version, '>' ) || empty( $previous_version ) ) {
			$this->update_v12_default_billing_fields();
		}

		//Update the version # saved in DB after version checks above
		update_option( 'give_sage_payments_version', GIVE_SAGE_PAYMENTS_VERSION );

	}

	/**
	 * Update 1.2 Collect Billing Details
	 *
	 * @description: Sets the default option to display Billing Details as to not mess with any donation forms without consent
	 * @see        : https://github.com/WordImpress/Give-Authorize-Gateway/issues/16
	 */
	private function update_v12_default_billing_fields() {
		give_update_option( 'sage_payments_collect_billing', 'on' );
	}

}

new Give_Sage_Payments_Upgrades();