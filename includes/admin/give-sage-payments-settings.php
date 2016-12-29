<?php
/**
 *  Give Authorize Settings
 *
 * @description:
 * @copyright  : http://opensource.org/licenses/gpl-2.0.php GNU Public License
 * @since      : 1.1
 * @created    : 11/16/2015
 */


// adds the settings to the Payment Gateways section
function give_add_sage_payments_settings( $settings ) {

	$give_settings = array(
		array(
			'name' => '<strong>' . __( 'Sage Payments Gateway', 'give-sage-payments' ) . '</strong>',
			'desc' => '<hr>',
			'type' => 'give_title',
			'id'   => 'give_title_sage_payments',
		),
		array(
			'id'   => 'give_sage_payments_merchant_id',
			'name' => __( 'Live Merchant ID', 'give-sage-payments' ),
			'desc' => __( 'Please enter your Sage Payments Merchant ID (MID).', 'give-sage-payments' ),
			'type' => 'text'
		),
		array(
			'id'   => 'give_sage_payments_merchant_key',
			'name' => __( 'Live Merchant Key', 'give-sage-payments' ),
			'desc' => __( 'Please enter your Sage Payments merchant key.', 'give-sage-payments' ),
			'type' => 'text'
		),
		array(
			'id'   => 'give_sage_payments_recurring_group_id',
			'name' => __( 'Recurring Group ID', 'give-sage-payments' ),
			'desc' => __( 'Please enter your Recurring Group ID under which the recurring transaction (if applicable) will be added.', 'give-sage-payments' ),
			'type' => 'text'
		),
		array(
			'id'   => 'give_sage_payments_test_merchant_id',
			'name' => __( 'Test Merchant ID', 'give-sage-payments' ),
			'desc' => __( 'Please enter your <em>test</em> Sage Payments Merchant ID testing purposes.', 'give-sage-payments' ),
			'type' => 'text'
		),
		array(
			'id'   => 'give_sage_payments_test_merchant_key',
			'name' => __( 'Test Merchant Key', 'give-sage-payments' ),
			'desc' => __( 'Plase enter your <em>test</em> Sage Payments merchant key for testing purposes.', 'give-sage-payments' ),
			'type' => 'text'
		),
		array(
			'id'   => 'give_sage_payments_client_id',
			'name' => __( 'Client ID', 'give-sage-payments' ),
			'desc' => __( 'Please enter your Client ID (part of your developer credentials).', 'give-sage-payments' ),
			'type' => 'text'
		),
		array(
			'id'   => 'give_sage_payments_client_secret',
			'name' => __( 'Client Secret', 'give-sage-payments' ),
			'desc' => __( 'Please enter your Client Secret (part of your developer credentials).', 'give-sage-payments' ),
			'type' => 'text'
		),
		array(
			'name' => __( 'Collect Billing Details', 'give' ),
			'desc' => sprintf(__( 'This option will enable the billing details section for Sage Payments which requires the donor\'s address to complete the donation. These fields are not required by Sage Payments to process the transaction, but you may have the need to collect the data.', 'give' ) ),
			'id'   => 'give_sage_payments_collect_billing',
			'type' => 'checkbox'
		),
	);

	return array_merge( $settings, $give_settings );
}

add_filter( 'give_settings_gateways', 'give_add_sage_payments_settings' );
