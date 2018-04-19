// Add WorldPay redirection
$(document).bind('em_booking_gateway_add_worldpay', function(event, response){
  // called by EM if return JSON contains gateway key, notifications messages are shown by now.
  if(response.result){
    var wpForm = $('<form action="'+response.worldpay_url+'" method="post" id="em-worldpay-redirect-form"></form>');
    $.each( response.worldpay_vars, function(index,value){
console.log( index+': '+value );
      wpForm.append('<input type="hidden" name="'+index+'" value="'+value+'" />');
    });
    wpForm.append('<input id="em-worldpay-submit" type="submit" style="display:none" />');
    wpForm.appendTo('body').trigger('submit');
  }
});