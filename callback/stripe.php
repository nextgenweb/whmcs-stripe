<?php

# Required File Includes
include("../../../dbconnect.php");
include("../../../includes/functions.php");
include("../../../includes/gatewayfunctions.php");
include("../../../includes/invoicefunctions.php");
require_once("../stripe/Stripe.php");

$gatewaymodule = "stripe"; # Enter your gateway module name here replacing template

$gateway = getGatewayVariables($gatewaymodule);
if (!$gateway["type"]) die("Module Not Activated"); # Checks gateway module is active before accepting callback

$gatewaytestmode = $gateway['testmode'];

if ($gatewaytestmode == "on") {
	Stripe::setApiKey($gateway['private_test_key']);
} else {
	Stripe::setApiKey($gateway['private_live_key']);
}

$body = @file_get_contents('php://input');
$event_json = json_decode($body);
$event_id = $event_json->id;

try {

	$event = Stripe_Event::retrieve($event_id);

	if($event->type == 'charge.succeeded') {
	
		// Pull invoice ID from Stripe description
		if ($event->data->object->invoice != "") { // This is an invoice/subscription payment, get the WHMCS invoice ID
			$invoice_id = $event->data->object->invoice;
			$retrieved_invoice = Stripe_Invoice::retrieve($invoice_id)->lines->all(array('count'=>1, 'offset'=>0));
			$description_invoice = $retrieved_invoice["data"][0]["plan"]["name"];
			$description = $description_invoice;
		} else { // This is a one time payment
			$description = $event->data->object->description;
		}
		
		// Get the invoice ID from the transaction
		$start = strpos($description, "#") + strlen("#");
		$end = strpos($description, " ", $start);
		$invoiceid = substr($description, $start, $end - $start);
		
		$transid = $event->data->object->id;
		
		$amount_cents = $event->data->object->amount;
		$amount = $amount / 100;
		
		$fee_cents = $event->data->object->fee_details->amount;
		$fee = $fee_cents / 100;
		
		$paid = $event->data->object->paid;
		
	}

} catch (Exception $e) {
	mail($gateway["problememail"],"Stripe Failed Callback","A problem prevented Stripe from properly processing an incoming payment webhook:" . $e);
}

$invoiceid = checkCbInvoiceID($invoiceid,$GATEWAY["name"]); # Checks invoice ID is a valid invoice number or ends processing

checkCbTransID($transid); # Checks transaction number isn't already in the database and ends processing if it does

if ($paid == true) {
    # Successful
    addInvoicePayment($invoiceid,$transid,$amount,$fee,$gatewaymodule); # Apply Payment to Invoice: invoiceid, transactionid, amount paid, fees, modulename
	logTransaction($GATEWAY["name"],$event,"Successful"); # Save to Gateway Log: name, data array, status
} else {
	# Unsuccessful
    logTransaction($GATEWAY["name"],$event,"Unsuccessful"); # Save to Gateway Log: name, data array, status
}

?>