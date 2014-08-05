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
			add_action('em_template_my_bookings_header',array(&$this,'say_thanks')); //say thanks on my_bookings page
			add_filter('em_booking_validate', array(&$this, 'em_booking_validate'),10,2); // Hook into booking validation

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
	 * Intercepts return data after a booking has been made
	 * Add payment method choices if setting is enabled via gateway settings
	 * @param array $return
	 * @param EM_Booking $EM_Booking
	 * @return array
	 */

	/**
	 * Outputs custom booking form content and credit card information.
	 */
	function booking_form(){

		if( get_option('em_'. $this->gateway . "_payment_method_selection" ) ) {
			ob_start();
			?>
			<p>
				<label><?php  _e('Payment Method','em-pro'); ?></label>
				<select name="paymentType">
					<option value="">Payment method</option>
					<option value="AMEX">American Express</option>
					<option value="DINS">Diners</option>
					<option value="ELV">ELV</option>
					<option value="JCB">JCB</option>
					<option value="MSCD">Mastercard</option>
					<option value="DMC">Mastercard Debit</option>
					<option value="LASR">Laser</option>
					<option value="MAES">Maestro</option>
					<option value="VISA">Visa</option>
					<option value="VISD">Visa Debit</option>
					<option value="VIED">Visa Electron</option>
					<option value="VISP">Visa Purchasing</option>
					<option value="VME">V.me</option>
				</select>
			</p>
			<?php
			echo apply_filters('em_gateway_'.$this->gateway.'_booking_form', ob_get_clean() );
		}
	}

	/**
	 * Hook into booking validation and check validate payment type if present
	 * @param boolean $result
	 * @param EM_Booking $EM_Booking
	 */
	function em_booking_validate($result, $EM_Booking) {
		if( isset( $_POST['paymentType'] ) && empty( $_POST['paymentType'] ) ) {
			$EM_Booking->add_error('Please specify payment method');
			$result = false;
		}
		return $result;
	}


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

		$currency = get_option('dbem_bookings_currency', 'USD');
		$currency = apply_filters('em_gateway_worldpay_get_currency', $currency, $EM_Booking );

		$amount = $EM_Booking->get_price();
		$amount = apply_filters('em_gateway_worldpay_get_amount', $amount, $EM_Booking, $_REQUEST );

		$worldpay_vars = array(
			'instId' => get_option('em_'. $this->gateway . "_instId" ),
			'cartId' => $EM_Booking->booking_id,
			'currency' => $currency,
			'amount' => number_format( $amount, 2),
			'desc' => $EM_Booking->get_event()->event_name
		);

		if( get_option('em_'. $this->gateway . "_mode" ) == 'test' ) {
			$worldpay_vars['testMode'] = 100;
		}

		if( get_option('em_'. $this->gateway . "_hide_currency" ) ) {
			$worldpay_vars['hideCurrency'] = 1;
		}

		if( get_option('em_'. $this->gateway . "_hide_language" ) ) {
			$worldpay_vars['noLanguageMenu'] = 1;
		}

		if( get_option('em_'. $this->gateway . "_payment_method_selection" ) ) {
			$worldpay_vars['paymentType'] = $_REQUEST['paymentType'];
		}

		// Build MD5 signature if configured for use in Gateway settings
		if( get_option('em_'. $this->gateway . "_md5_key" ) != '' ) {
			$signature = get_option('em_'. $this->gateway . "_md5_key" );
			$signature.= ":".get_option('em_'. $this->gateway . "_instId" );
			$signature.= ":".number_format( $amount, 2);
			$signature.= ":".$currency;
			$signature.= ":".$EM_Booking->booking_id;
			$worldpay_vars['signature'] = md5( $signature );
		}

		return apply_filters('em_gateway_worldpay_get_worldpay_vars', $worldpay_vars, $EM_Booking, $this);
	}

	/**
	 * gets worldpay gateway url
	 * @returns string
	 */
	function get_worldpay_url(){
		if( get_option('em_'. $this->gateway . "_mode" ) == 'test' ) {
			return 'https://secure-test.worldpay.com/wcc/purchase';
		}
		return 'https://secure.worldpay.com/wcc/purchase';
	}

	/**
	 * Return thanks message on My Bookings page if GET var set
	 */
	function say_thanks(){
		if( $_REQUEST['thanks'] == 1 ){
			echo "<div class='em-booking-message em-booking-message-success'>".get_option('em_'.$this->gateway.'_booking_feedback_thanks').'</div>';
		}
	}


	/**
	 * Runs when WorldPay Payment Response is enabled and sends payment details accross. Bookings are updated and transactions are recorded accordingly.
	 */
	function handle_payment_return() {

// For testing only
//$_POST = $_GET;
//error_log( print_r( $_POST, true  ) );
//var_dump( $_POST );

		// Security check if Payment Response Password is configured
		if( get_option('em_'. $this->gateway . "_callback_pw" ) != '' ) {
			if( get_option('em_'. $this->gateway . "_callback_pw" ) != $_POST['callbackPW'] ) {
				status_header( 403 );
				echo 'Permisson denied. callbackPW incorrect.';
				return;
			}
		}

		if( isset( $_POST['cartId'] ) && isset( $_POST['transTime'] ) && isset( $_POST['transStatus'] ) ) {

			// Lookup booking
			$EM_Booking = em_get_booking( $_POST['cartId'] );

			$amount = $_POST['amount'];
			$currency = $_POST['currency'];
			$timestamp = date('Y-m-d H:i:s', $_POST['transTime'] / 1000 ); // WorldPay timestamp is miliseconds since epoch

			if( !empty($EM_Booking->booking_id) ){

				if( $_POST['transStatus'] == 'Y' ) {
					// Payment successful
					$this->record_transaction($EM_Booking, $amount, $currency, $timestamp, $_POST['transId'], $_POST['transStatus'], '');

					if( $amount >= $EM_Booking->get_price() && (!get_option('em_'.$this->gateway.'_manual_approval', false) ) ){
						$EM_Booking->approve(true, true); //approve and ignore spaces
					}else{
						//TODO do something if worldpay payment not enough
						$EM_Booking->set_status(0); //Set back to normal "pending"
					}
					do_action('em_payment_processed', $EM_Booking, $this);

					// Display thanks message and link back to custom page or my bookings
					$continue_link = get_option('em_'. $this->gateway . '_return_success');
					if( empty( $continue_link ) ) {
						$continue_link = get_permalink(get_option("dbem_my_bookings_page")).'?thanks=1';
					}
					echo '<WPDISPLAY FILE=header.html>';
					echo '<WPDISPLAY ITEM=banner>';
					echo '<p>'.get_option('em_'.$this->gateway.'_booking_feedback_thanks').'</p>';
					echo '<p><strong>Item:</strong> '.$EM_Booking->get_event()->event_name.'<br />';
					echo '<strong>Amount:</strong> '.$currency.$amount.'<br />';
					echo '<strong>Booking Reference:</strong> '.$EM_Booking->booking_id.'<br /></p>';
					echo '<p>Return to <a href="'.$continue_link.'">'.get_bloginfo('name').'</a>';
					echo '<WPDISPLAY FILE=footer.html>';
					return;

				}else {
					if( $_POST['transStatus'] == 'C' ) {
						// Payment Cancelled

						$note = 'Transaction cancelled: '.$_POST['rawAuthMessage'];
						$this->record_transaction($EM_Booking, $amount, $currency, $timestamp, $_POST['tranId'], $_POST['tranStatus'], $note);

						$EM_Booking->cancel();
						do_action('em_payment_cancelled', $EM_Booking, $this);

						if( empty( $continue_link ) ) {
							$continue_link = get_permalink(get_option("dbem_my_bookings_page")).'?fail='.$strStatus;
						}
						echo '<WPDISPLAY FILE=header.html>';
						echo '<WPDISPLAY ITEM=banner>';
						echo '<p>Payment cancelled.</p>';
						echo 'Return to <a href="'.$continue_link.'">'.get_bloginfo('name').'</a>';
						echo '<WPDISPLAY FILE=footer.html>';
						return;

					}else{
						echo 'Error: Unrecognised Status received';
					}
				}
			}else{

				// Handle case of no booking found
				if( is_numeric( $_POST['cartId'] ) && $_POST['transStatus'] == 'Y' ){
					$message = apply_filters('em_gateway_worldpay_bad_booking_email',"
A Payment has been received by WorldPay for a non-existent booking.

It may be that this user's booking has timed out yet they proceeded with payment at a later stage.

In some cases, it could be that other payments not related to Events Manager are triggering this error.

To refund this transaction, you must go to your WorldPay account and search for this transaction:

Transaction ID : %transaction_id%
Email : %payer_email%

When viewing the transaction details, you should see an option to issue a refund.

If there is still space available, the user must book again.

Sincerely,
Events Manager
					", $booking_id, $event_id);
					$EM_Event = new EM_Event($event_id);
					$message  = str_replace(array('%transaction_id%','%payer_email%'), array($_POST['transId'], $_POST['email'] ), $message);
					wp_mail(get_option('em_'. $this->gateway . "_email" ), __('Unprocessed payment needs refund'), $message);
				}else{
					//header('Status: 404 Not Found');
					echo 'Error: Bad WorldPay request. No booking found.';
					exit;
				}
			}
		} else {
			// Did not find expected POST variables. Possible access attempt from a non WorldPay site.
			echo 'Error: Missing POST variables. Identification is not possible. If you are not WorldPay and are visiting this page directly in your browser, this error does not indicate a problem, but simply means EM is correctly set up and ready to receive notifications from WorldPay only.';
			exit;
		}
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

	<p>
		<strong><?php _e('Important:','em-pro'); ?></strong>
		<?php _e('In order to connect WorldPay with your site, you need to enable Payment Response on your WorldPay account.', 'em-pro'); ?><br />
		<?php echo " ". sprintf(__('Your Payment Response Url is %s', 'em-pro'),'<code>'.$this->get_payment_return_url().'</code>'); ?>
	</p>

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
				<th scope="row"><?php _e('WorldPay Mode', 'em-pro') ?></th>
				<td>
					<select name="worldpay_mode">
						<option value="live" <?php if (get_option('em_'. $this->gateway . "_mode" ) == 'live') echo 'selected="selected"'; ?>><?php _e('Live', 'em-pro') ?></option>
						<option value="test" <?php if (get_option('em_'. $this->gateway . "_mode" ) == 'test') echo 'selected="selected"'; ?>><?php _e('Test', 'em-pro') ?></option>
					</select>
					<br />
				</td>
			</tr>
			<tr valign="top">
				<th scope="row"><?php _e('MD5 secret for transactions', 'em-pro') ?></th>
				<td>
					<input type="text" name="worldpay_md5_key" value="<?php esc_attr_e( get_option('em_'. $this->gateway . "_md5_key" )); ?>" />
					<br />
					<em><?php _e('Recommended, but optional, secret key for MD5 Encryption. This can be anything you want, but if set, must also be configured under your WorldPay installation setup.', 'em-pro'); ?></em><br />
					<em><?php _e('If using MD5 Encryption, specify the following SignatureField in your installation setup:', 'em-pro'); ?></em>
					<code>instId:amount:currency:cartId</code>
				</td>
			</tr>
			<tr valign="top">
				<th scope="row"><?php _e('Payment Response Password', 'em-pro') ?></th>
				<td>
					<input type="text" name="worldpay_callback_pw" value="<?php esc_attr_e( get_option('em_'. $this->gateway . "_callback_pw" )); ?>" />
					<br />
					<em><?php _e('To secure the Events Manager response handler, add a password here and in your WorldPay Installation Setup.', 'em-pro'); ?></em><br />
				</td>
			</tr>
			<tr valign="top">
				<th scope="row"><?php _e('Bypassing the Payment Selection Page', 'em-pro') ?></th>
				<td>
					<input type="checkbox" name="worldpay_payment_method_selection" value="1" <?php echo (get_option('em_'. $this->gateway . "_payment_method_selection" )) ? 'checked="checked"':''; ?> />
					<em><?php _e("Enable this option to offer the user a choice of payment methods on your WordPress site.", 'em-pro'); ?></em><br />
					<em><?php _e("This will allow users to bypass the Payment Selection Page on WorldPay.", 'em-pro'); ?></em><br />
					<em><?php _e("Note: Hide Language Choice will need to be enabled for the bypass to work.", 'em-pro'); ?></em><br />
				</td>
			</tr>
			<tr valign="top">
				<th scope="row"><?php _e('Hide currency choice', 'em-pro') ?></th>
				<td>
					<input type="checkbox" name="worldpay_hide_currency" value="1" <?php echo (get_option('em_'. $this->gateway . "_hide_currency" )) ? 'checked="checked"':''; ?> />
					<em><?php _e("By default the WorldPay payment page offers a choice of currency. Check if you don't want to give users that choice.", 'em-pro'); ?></em><br />
				</td>
			</tr>
			<tr valign="top">
				<th scope="row"><?php _e('Hide language choice', 'em-pro') ?></th>
				<td>
					<input type="checkbox" name="worldpay_hide_language" value="1" <?php echo (get_option('em_'. $this->gateway . "_hide_language" )) ? 'checked="checked"':''; ?> />
					<em><?php _e("By default the WorldPay payment page offers a choice of language. Check if you don't want to give users that choice.", 'em-pro'); ?></em><br />
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
			$this->gateway . "_md5_key" => $_REQUEST[ $this->gateway.'_md5_key' ],
			$this->gateway . "_callback_pw" => $_REQUEST[ $this->gateway.'_callback_pw' ],
			$this->gateway . "_manual_approval" => $_REQUEST[ $this->gateway.'_manual_approval' ],
			$this->gateway . "_booking_feedback" => wp_kses_data($_REQUEST[ $this->gateway.'_booking_feedback' ]),
			$this->gateway . "_booking_feedback_free" => wp_kses_data($_REQUEST[ $this->gateway.'_booking_feedback_free' ]),
			$this->gateway . "_booking_feedback_thanks" => wp_kses_data($_REQUEST[ $this->gateway.'_booking_feedback_thanks' ]),
			$this->gateway . "_booking_timeout" => $_REQUEST[ $this->gateway.'_booking_timeout' ],
			$this->gateway . "_hide_currency" => $_REQUEST[ $this->gateway.'_hide_currency' ],
			$this->gateway . "_payment_method_selection" => $_REQUEST[ $this->gateway.'_payment_method_selection' ],
			$this->gateway . "_hide_language" => $_REQUEST[ $this->gateway.'_hide_language' ],
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