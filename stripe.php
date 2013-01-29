<?php

function stripe_config() {
    $configarray = array(
     "FriendlyName" => array("Type" => "System", "Value"=>"Stripe"),
     "public_live_key" => array("FriendlyName" => "Live Publishable Key", "Type" => "text", "Size" => "20", "Description" => "Available from Stripe's website at <a href='https://manage.stripe.com/account/apikeys' title='Stripe API Keys'>this link</a>.", ),
     "private_live_key" => array("FriendlyName" => "Live Secret Key", "Type" => "text", "Size" => "20", "Description" => "Available from Stripe's website at <a href='https://manage.stripe.com/account/apikeys' title='Stripe API Keys'>this link</a>.", ),
     "public_test_key" => array("FriendlyName" => "Test Secret Key", "Type" => "text", "Size" => "20", "Description" => "Available from Stripe's website at <a href='https://manage.stripe.com/account/apikeys' title='Stripe API Keys'>this link</a>.", ),
	 "private_test_key" => array("FriendlyName" => "Test Publishable Key", "Type" => "text", "Size" => "20", "Description" => "Available from Stripe's website at <a href='https://manage.stripe.com/account/apikeys' title='Stripe API Keys'>this link</a>." , ),
	 "problememail" => array("FriendlyName" => "Problem Report Email", "Type" => "text", "Size" => "20", "Description" => "Enter an email that the gateway can send a message to should an alert or other serious processing problem arise.", ),
     "testmode" => array("FriendlyName" => "Test Mode", "Type" => "yesno", "Description" => "Tick this to make all transactions use your test keys above.", ),
    );
	return $configarray;
}

function stripe_link($params) {

	# Invoice Variables
	$invoiceid = $params['invoiceid'];
	$description = $params["description"];
    $amount = $params['amount']; # Format: ##.##

	// Perform lookup to see if the invoice is for a recurring service
	$result = mysql_query("SELECT relid, amount, description FROM tblinvoiceitems WHERE type='Hosting' AND invoiceid='" . $invoiceid . "'");
    $grab_relid = mysql_fetch_row($result);
    $relid = $grab_relid[0];
    $subscribe_price = $grab_relid[1];
    $plan_name = $grab_relid[2];
    
    if ($relid != 0 || $relid != "") {
	    $wording = "Pay Once";
    } else {
	    $wording = "Pay Now";
    }
    
    if ($subscribe_price != $amount) { // Tell the payment processing page that they user needs to come back and pay their one time fees, such as a domain name, that were not covered as part of the subscription
	    $warning = "true";
    } else {
	    $warning = "false";
    }

	# Enter your code submit to the gateway...
	$code = '<form method="post" action="ccpay.php">
			<input type="hidden" name="description" value="'.$description.'" />
			<input type="hidden" name="invoiceid" value="'.$invoiceid.'" />
			<input type="hidden" name="amount" value="'.$amount.'" />
			<input type="hidden" name="frominvoice" value="true" />
			<input type="hidden" name="payfreq" value="otp" />
			<input type="hidden" name="multiple" value="'.$warning.'" />
			<input type="submit" value="'.$wording.'" />
			</form>';
			
	if ($relid != 0 || $relid != "") {
	$code .= '<form method="post" action="ccpay.php">
			<input type="hidden" name="description" value="'.$description.'" />
			<input type="hidden" name="invoiceid" value="'.$invoiceid.'" />
			<input type="hidden" name="amount" value="'.$subscribe_price.'" />
			<input type="hidden" name="total_amount" value="'.$amount.'" />
			<input type="hidden" name="frominvoice" value="true" />
			<input type="hidden" name="payfreq" value="recur" />
			<input type="hidden" name="planname" value="'.$plan_name.'" />
			<input type="hidden" name="planid" value="'.$relid.'" />
			<input type="hidden" name="multiple" value="'.$warning.'" />
			<input type="submit" value="Set up Automatic Payment" />
			</form>';
	}
	
	return $code;
	
}

function stripe_refund($params) {

	require_once('stripe/Stripe.php');
	
	$gatewaytestmode = $params["testmode"];
	
	if ($gatewaytestmode == "on") {
		Stripe::setApiKey($params['private_test_key']);
	} else {
		Stripe::setApiKey($params['private_live_key']);
	}

    # Invoice Variables
	$transid = $params['transid'];

	# Perform Refund
	try {
		$ch = Stripe_Charge::retrieve($transid);
		$ch->refund();
		return array("status"=>"success","transid"=>$ch["id"],"rawdata"=>$ch);
	} catch (Exception $e) {
		$response['error'] = $e->getMessage();
		return array("status"=>"error","rawdata"=>$response['error']);
	}

}

?>