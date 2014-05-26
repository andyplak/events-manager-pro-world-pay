<?php

class EM_Gateway_Worldpay extends EM_Gateway {

	var $gateway = 'worldpay';
	var $title = 'WorldPay';
	var $status = 4;
	var $status_txt = 'Awaiting WorldPay Payment';
	var $button_enabled = true;
	var $payment_return = true;
	var $supports_multiple_bookings = true;

	public function __construct() {
		parent::__construct();

		if($this->is_active()) {

			add_action('em_gateway_js', array(&$this,'em_gateway_js'));

			//set up cron for booking timeouts
			$timestamp = wp_next_scheduled('emp_worldpay_cron');
			if( absint(get_option('em_worldpay_booking_timeout')) > 0 && !$timestamp ){
				$result = wp_schedule_event(time(),'em_minute','emp_worldpay_cron');
			}elseif( !$timestamp ){
				wp_unschedule_event($timestamp, 'emp_worldpay_cron');
			}
		}else{
			// Unschedule the cron as gateway is not active
			wp_clear_scheduled_hook('emp_worldpay_cron');
		}
	}


	/*
	 * --------------------------------------------------
	 * Booking UI - modifications to booking pages and tables containing WorldPay bookings
	 * --------------------------------------------------
	 */


	/**
	 * Outputs some JavaScript during the em_gateway_js action, which is run inside a script html tag, located in gateways/gateway.worldpay.js
	 */
	function em_gateway_js(){
		include(dirname(__FILE__).'/gateway.worldpay.js');
	}



	/*
	 * --------------------------------------------------
	 * Booking Interception - functions that modify booking object behaviour
	 * --------------------------------------------------
	 */

	/**
	 * Intercepts return data after a booking has been made and adds WorldPay vars, modifies feedback message.
	 * @param array $return
	 * @param EM_Booking $EM_Booking
	 * @return array
	 */
	function booking_form_feedback( $return, $EM_Booking = false ){

		//Double check $EM_Booking is an EM_Booking object and that we have a booking awaiting payment.
		if( is_object($EM_Booking) && $this->uses_gateway($EM_Booking) ){
			if( !empty($return['result']) && $EM_Booking->get_price() > 0 && $EM_Booking->booking_status == $this->status ){
				$return['message'] = get_option('em_worldpay_booking_feedback');
				$worldpay_url = $this->get_worldpay_url();
				$worldpay_vars = $this->get_worldpay_vars($EM_Booking);
				$worldpay_return = array('worldpay_url'=>$worldpay_url, 'worldpay_vars'=>$worldpay_vars);
				$return = array_merge($return, $worldpay_return);
			}else{
				//returning a free message
				$return['message'] = get_option('em_worldpay_booking_feedback_free');
			}
		}
		return $return;
	}


	/*
	 * ------------------------------------------------------------
	 * WorldPay Functions - functions specific to WorldPay payments
	 * ------------------------------------------------------------
	 */

	/**
	 * Retreive the WorldPay vars needed to send to the gatway to proceed with payment
	 * @param EM_Booking $EM_Booking
	 */
	function get_worldpay_vars( $EM_Booking ) {
		global $wp_rewrite, $EM_Notices;

		$worldpay_vars = array(
			'instId' => get_option('em_'. $this->gateway . "_instId" ),
			'cartId' => 'EM-'.$EM_Booking->booking_id,
			'currency' => get_option('dbem_bookings_currency', 'USD'),
			'amount' => number_format( $EM_Booking->get_price(), 2),
			'desc' => __('Event Tickets for ', 'em-pro') . $EM_Booking->get_event()->event_name
		);

		if( get_option('em_'. $this->gateway . "_mode" ) == 'test' ) {
			$worldpay_vars['testMode'] = 100;
		}

		return apply_filters('em_gateway_worldpay_get_worldpay_vars', $worldpay_vars, $EM_Booking, $this);
	}

	/**
	 * gets worldpay gateway url
	 * @returns string
	 */
	function get_worldpay_url(){
		return 'https://secure-test.worldpay.com/wcc/purchase';
	}

	/*
	 * --------------------------------------------------
	 * Gateway Settings Functions
	 * --------------------------------------------------
	 */

	function mysettings() {
		global $EM_options;
		?>
	<table class="form-table">
		<tbody>
			<tr valign="top">
				<th scope="row"><?php _e('Success Message', 'em-pro') ?></th>
			 	<td>
					<input type="text" name="worldpay_booking_feedback" value="<?php esc_attr_e(get_option('em_'. $this->gateway . "_booking_feedback" )); ?>" style='width: 40em;' /><br />
					<em><?php _e('The message that is shown to a user when a booking is successful whilst being redirected to WorldPay for payment.','em-pro'); ?></em>
				</td>
			</tr>
			<tr valign="top">
				<th scope="row"><?php _e('Success Free Message', 'em-pro') ?></th>
				<td>
					<input type="text" name="worldpay_booking_feedback_free" value="<?php esc_attr_e(get_option('em_'. $this->gateway . "_booking_feedback_free" )); ?>" style='width: 40em;' /><br />
					<em><?php _e('If some cases if you allow a free ticket (e.g. pay at gate) as well as paid tickets, this message will be shown and the user will not be redirected to WorldPay.','em-pro'); ?></em>
				</td>
		  	</tr>
			<tr valign="top">
				<th scope="row"><?php _e('Thank You Message', 'em-pro') ?></th>
				<td>
					<input type="text" name="worldpay_booking_feedback_thanks" value="<?php esc_attr_e(get_option('em_'. $this->gateway . "_booking_feedback_thanks" )); ?>" style='width: 40em;' /><br />
					<em><?php _e('If you choose to return users to the default Events Manager thank you page after a user has paid on WorldPay, you can customize the thank you message here.','em-pro'); ?></em>
			  	</td>
			</tr>
		</tbody>
	</table>

	<h3><?php echo sprintf(__('%s Options','em-pro'),'WorldPay'); ?></h3>

	<table class="form-table">
		<tbody>
			<tr valign="top">
				<th scope="row"><?php _e('WorldPay Installation ID', 'em-pro') ?></th>
					<td><input type="text" name="worldpay_instId" value="<?php esc_attr_e( get_option('em_'. $this->gateway . "_instId" )); ?>" />
						<br />
						<em><?php _e('Set this value to the Installation ID assigned to you by WorldPay', 'em-pro'); ?></em>
					</td>
				</tr>
				<tr valign="top">
				<th scope="row"><?php _e('WolrdPay Mode', 'em-pro') ?></th>
				<td>
					<select name="worldpay_mode">
						<option value="live" <?php if (get_option('em_'. $this->gateway . "_mode" ) == 'live') echo 'selected="selected"'; ?>><?php _e('Live', 'em-pro') ?></option>
						<option value="test" <?php if (get_option('em_'. $this->gateway . "_mode" ) == 'test') echo 'selected="selected"'; ?>><?php _e('Test', 'em-pro') ?></option>
					</select>
					<br />
				</td>
			</tr>
			<tr valign="top">
				<th scope="row"><?php _e('Return Success URL', 'em-pro') ?></th>
				<td>
					<input type="text" name="worldpay_return_success" value="<?php esc_attr_e(get_option('em_'. $this->gateway . "_return_success" )); ?>" style='width: 40em;' /><br />
					<em><?php _e('Once a payment is completed, users will sent to the My Bookings page which confirms that the payment has been made. If you would to customize the thank you page, create a new page and add the link here. Leave blank to return to default booking page with the thank you message specified above.', 'em-pro'); ?></em>
			  	</td>
			</tr>
			<tr valign="top">
				<th scope="row"><?php _e('Return Fail URL', 'em-pro') ?></th>
				<td>
					<input type="text" name="worldpay_return_fail" value="<?php esc_attr_e(get_option('em_'. $this->gateway . "_return_fail" )); ?>" style='width: 40em;' /><br />
					<em><?php _e('If a payment is unsucessful or if a user cancels, they will be redirected to the my bookings page. If you want a custom page instead, create a new page and add the link here.', 'em-pro'); ?></em>
			  	</td>
		  	</tr>
			<tr valign="top">
				<th scope="row"><?php _e('Delete Bookings Pending Payment', 'em-pro') ?></th>
				<td>
					<input type="text" name="worldpay_booking_timeout" style="width:50px;" value="<?php esc_attr_e(get_option('em_'. $this->gateway . "_booking_timeout" )); ?>" style='width: 40em;' /> <?php _e('minutes','em-pro'); ?><br />
					<em><?php _e('Once a booking is started and the user is taken to WorldPay, Events Manager stores a booking record in the database to identify the incoming payment. These spaces may be considered reserved if you enable <em>Reserved unconfirmed spaces?</em> in your Events &gt; Settings page. If you would like these bookings to expire after x minutes, please enter a value above (note that bookings will be deleted, and any late payments will need to be refunded manually via WorldPay).','em-pro'); ?></em>
				</td>
			</tr>
			<tr valign="top">
				<th scope="row"><?php _e('Manually approve completed transactions?', 'em-pro') ?></th>
				<td>
					<input type="checkbox" name="worldpay_manual_approval" value="1" <?php echo (get_option('em_'. $this->gateway . "_manual_approval" )) ? 'checked="checked"':''; ?> /><br />
					<em><?php _e('By default, when someone pays for a booking, it gets automatically approved once the payment is confirmed. If you would like to manually verify and approve bookings, tick this box.','em-pro'); ?></em><br />
					<em><?php echo sprintf(__('Approvals must also be required for all bookings in your <a href="%s">settings</a> for this to work properly.','em-pro'),EM_ADMIN_URL.'&amp;page=events-manager-options'); ?></em>
				</td>
		 	</tr>
		</tbody>
	</table>
		<?php
	}

	function update() {
		parent::update();
		$gateway_options = array(
			$this->gateway . "_mode" => $_REQUEST[ $this->gateway.'_mode'],
			$this->gateway . "_instId" => $_REQUEST[ $this->gateway.'_instId' ],
			$this->gateway . "_manual_approval" => $_REQUEST[ $this->gateway.'_manual_approval' ],
			$this->gateway . "_booking_feedback" => wp_kses_data($_REQUEST[ $this->gateway.'_booking_feedback' ]),
			$this->gateway . "_booking_feedback_free" => wp_kses_data($_REQUEST[ $this->gateway.'_booking_feedback_free' ]),
			$this->gateway . "_booking_feedback_thanks" => wp_kses_data($_REQUEST[ $this->gateway.'_booking_feedback_thanks' ]),
			$this->gateway . "_booking_timeout" => $_REQUEST[ $this->gateway.'_booking_timeout' ],
			$this->gateway . "_return_success" => $_REQUEST[ $this->gateway.'_return_success' ],
			$this->gateway . "_return_fail" => $_REQUEST[ $this->gateway.'_return_fail' ],
		);
		foreach($gateway_options as $key=>$option){
			update_option('em_'.$key, stripslashes($option));
		}
		return true;
	}
}

EM_Gateways::register_gateway('worldpay', 'EM_Gateway_Worldpay');

/**
 * Deletes bookings pending payment that are more than x minutes old, defined by WorldPay options.
 * This is lifted straight from PayPal Gateway in EM Pro. This doesn't take into account Gateway,
 * so PayPal bookings could be deleted by the WorldPay cron and vice versa.
 */
function em_gateway_worldpay_booking_timeout(){
	global $wpdb;
	//Get a time from when to delete
	$minutes_to_subtract = absint(get_option('em_worldpay_booking_timeout'));
	if( $minutes_to_subtract > 0 ){
		//get booking IDs without pending transactions
		$booking_ids = $wpdb->get_col('SELECT b.booking_id FROM '.EM_BOOKINGS_TABLE.' b LEFT JOIN '.EM_TRANSACTIONS_TABLE." t ON t.booking_id=b.booking_id  WHERE booking_date < TIMESTAMPADD(MINUTE, -{$minutes_to_subtract}, NOW()) AND booking_status=4 AND transaction_id IS NULL" );
		if( count($booking_ids) > 0 ){
			//first delete ticket_bookings with expired bookings
			$sql = "DELETE FROM ".EM_TICKETS_BOOKINGS_TABLE." WHERE booking_id IN (".implode(',',$booking_ids).");";
			$wpdb->query($sql);
			//then delete the bookings themselves
			$sql = "DELETE FROM ".EM_BOOKINGS_TABLE." WHERE booking_id IN (".implode(',',$booking_ids).");";
			$wpdb->query($sql);
		}
	}
}
add_action('emp_worldpay_cron', 'em_gateway_worldpay_booking_timeout');