<?php
/*
Plugin Name: Events Manager Pro - WorldPay Gateway
Plugin URI: http://wp-events-plugin.com
Description: WorldPay Hosted Payment Page (HTML Redirect) gateway plugin for Events Manager Pro
Version: 1.0
Depends: Events Manager Pro
Author: Andy Place
Author URI: http://www.andyplace.co.uk
*/

class EM_Pro_Worldpay {

	function EM_Pro_Worldpay() {
		global $wpdb;
		//Set when to run the plugin : after EM is loaded.
		add_action( 'plugins_loaded', array(&$this,'init'), 100 );
	}

	function init() {
		//add-ons
		include('add-ons/gateways/gateway.worldpay.php');
	}
}

// Start plugin
global $EM_Pro_Worldpay;
$EM_Pro_Worldpay = new EM_Pro_Worldpay();