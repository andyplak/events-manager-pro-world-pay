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
	}

	/************ Admin functions *************/

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