<?php
/*
Plugin Name: Events Manager Pro - WorldPay Gateway
Plugin URI: http://wp-events-plugin.com
Description: WorldPay Hosted Payment Page (HTML Redirect) gateway plugin for Events Manager Pro
Version: 1.2
Depends: Events Manager Pro
Author: Andy Place
Author URI: http://www.andyplace.co.uk
*/

include_once( ABSPATH . 'wp-admin/includes/plugin.php' );

class EM_Pro_Worldpay {

	function EM_Pro_Worldpay() {
		global $wpdb;
		//Set when to run the plugin : after EM is loaded.
		add_action( 'plugins_loaded', array(&$this,'init'), 100 );
	}

	function init() {
		//add-ons
		if( is_plugin_active('events-manager/events-manager.php') && is_plugin_active('events-manager-pro/events-manager-pro.php') ) {
			//add-ons
			include('add-ons/gateways/gateway.worldpay.php');
		}else{
			add_action( 'admin_notices', array(&$this,'not_activated_error_notice') );
		}
	}

	function not_activated_error_notice() {
		$class = "error";
		$message = __('Please ensure both Events Manager and Events Manager Pro are enabled for the WorldPay Gateway to work.', 'em-pro');
		echo '<div class="'.$class.'"> <p>'.$message.'</p></div>';
	}
}

// Start plugin
global $EM_Pro_Worldpay;
$EM_Pro_Worldpay = new EM_Pro_Worldpay();