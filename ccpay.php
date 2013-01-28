<?php

define("CLIENTAREA",true);
define("FORCESSL",true);

require("dbconnect.php");
require("includes/functions.php");
require("includes/clientareafunctions.php");
include("includes/gatewayfunctions.php");
require("modules/gateways/stripe/Stripe.php");

$gateway = getGatewayVariables("stripe");

if ($gatewaytestmode == "on") {
	Stripe::setApiKey($gateway['private_test_key']);
	$pubkey = $gateway['public_test_key'];
} else {
	Stripe::setApiKey($gateway['private_live_key']);
	$pubkey = $gateway['public_live_key'];
}

function send_error($error_type, $error_contents) {
	mail($gateway['problememail'],"Stripe " . $error_type . " Error","Stripe payment processor failed processing a charge due to the following " . $error_type . " error: " . $error_contents);
}

$pagetitle = $_LANG['clientareatitle'] . " - Credit Card Payment Entry";

initialiseClientArea($pagetitle,'',$breadcrumbnav);

$smartyvalues["description"] = $_POST["description"];
$smartyvalues["invoiceid"] = $_POST["invoiceid"];
$smartyvalues["amount"] = $_POST["amount"];
$smartyvalues["total_amount"] = $_POST["total_amount"];
$smartyvalues["planname"] = $_POST["planname"];
$smartyvalues["planid"] = $_POST["planid"];
$smartyvalues["multiple"] = $_POST["multiple"];
$smartyvalues["payfreq"] = $_POST["payfreq"];
$smartyvalues["stripe_pubkey"] = $pubkey;

# Check login status
if ($_SESSION['uid']) {

	if ($_POST['frominvoice'] == "true" || $_POST['ccpay'] == "true") { 

			$result = mysql_query("SELECT firstname,lastname,email,address1,address2,state,postcode,city FROM tblclients WHERE id=".(int)$_SESSION['uid']);
			$data = mysql_fetch_array($result);
			
			$firstname = $data[0];
			$smartyvalues["firstname"] = $firstname;
			
			$lastname = $data[1];
			$smartyvalues["lastname"] = $lastname;
			
			$prepared_name = $firstname . " " . $lastname;
			$smartyvalues["name"] = $prepared_name;
			
	  		$email = $data[2];
	  		$smartyvalues["email"] = $email;
	  		
	  		$address1 = $data[3];
	  		$smartyvalues["address1"] = $address1;
	  		
	  		$address2 = $data[4];
	  		$smartyvalues["address2"] = $address2;
	  		
	  		$city = $data[7];
	  		$smartyvalues["city"] = $city;
	  		
	  		$state = $data[5];
	  		$smartyvalues["state"] = $state;
	  		
	  		$zipcode = $data[6];
	  		$smartyvalues["zipcode"] = $zipcode;
	
		// Is this a one time payment or is a subscription being set up?
		if ($_POST['payfreq'] == "otp") {
	
			$smartyvalues['explanation'] = "You are about to make a one time credit card payment of <strong>$" . $amount . "</strong>.";
	
			if ($_POST['stripeToken'] != "") {
				
				$token = $_POST['stripeToken'];
				$amount_cents = str_replace(".","",$amount);
				$description = "Invoice #" . $smartyvalues["invoiceid"] . " - " . $email;
		
				try {
				
					$charge = Stripe_Charge::create(array(
					  "amount" => $amount_cents,
					  "currency" => "usd",
					  "card" => $token,
					  "description" => $description)
					);
		
					if ($charge->card->address_zip_check == "fail") {
						throw new Exception("zip_check_invalid");
					} else if ($charge->card->address_line1_check == "fail") {
						throw new Exception("address_check_invalid");
					} else if ($charge->card->cvc_check == "fail") {
						throw new Exception("cvc_check_invalid");
					}
		
					// Payment has succeeded, no exceptions were thrown or otherwise caught
					$smartyvalues["success"] = true;
		
		
				} catch(Stripe_CardError $e) {
				
				$error = $e->getMessage();
				$smartyvalues["processingerror"] = 'Error: ' . $error . '.';
				  
				} catch (Stripe_InvalidRequestError $e) {
				  
				} catch (Stripe_AuthenticationError $e) {
					send_error("authentication",$e);
				} catch (Stripe_ApiConnectionError $e) {
					send_error("network", $e);
				} catch (Stripe_Error $e) {
					send_error("generic", $e);
				} catch (Exception $e) {
				
					if ($e->getMessage() == "zip_check_invalid") {
						$smartyvalues["processingerror"] = 'Error: The address information on your account does not match that of the credit card you are trying to use. Please try again or contact us if the problem persists.';
					} else if ($e->getMessage() == "address_check_invalid") {
						$smartyvalues["processingerror"] = 'The address information on your account does not match that of the credit card you are trying to use. Please try again or contact us if the problem persists.';
					} else if ($e->getMessage() == "cvc_check_invalid") {
						$smartyvalues["processingerror"] = 'The credit card information you specified is not valid. Please try again or contact us if the problem persists.';
					} else {
						send_error("unknown", $e);
					}
				  
				}
					
			} // end of if to check if this is a token acceptance for otps
			
		} else { // end if to check if this is a one time payment. else = this IS a otp

			$amount_total = $_POST['total_amount'];
			$amount_subscribe = $_POST['amount'];
			$amount_diff = abs($amount_total - $amount_subscribe);

			if ($multiple == "true") {
				$smartyvalues['explanation'] = "You are about to set up a <strong>$" . $amount_subscribe . "</strong> charge that will automatically bill to your credit card every <strong>month</strong>. You are also going to pay a <strong>one time</strong> charge of <strong>$" . $amount_diff . "</strong>.";
			} else {
				$smartyvalues['explanation'] = "You are about to set up a <strong>$" . $amount_subscribe . "</strong> charge that will automatically bill to your credit card every <strong>month</strong>.";
			}

			if ($_POST['stripeToken'] != "") {
				
				$token = $_POST['stripeToken'];
				$multiple = $_POST['multiple'];
				
				$amount_total_cents = $amount_total * 100;
				$amount_subscribe_cents = $amount_subscribe * 100;
				$amount_diff_cents = $amount_diff * 100;
				
				$message = "Amount Total: " . $amount_total . "<br/>";
				$message .= "Amount Subscribe: " . $amount_subscribe . "<br/>";
				$message .= "Amount Difference (OTP): " . $amount_diff . "<br/>";
				$message .= "Amount Difference (OTP) in Cents: " . $amount_diff_cents . "<br/>";
				
				$ng_plan_name = $_POST['planname'];
				$ng_plan_id = $_POST['planid'];
				$description_otp = "Invoice #" . $smartyvalues["invoiceid"] . " - " . $email .  " - One Time Services";
				$stripe_plan_name = "Invoice #" . $smartyvalues['invoiceid'] . ' - ' . $ng_plan_name . ' - ' . $email;
				
				// Create "custom" plan for this user
				try {
					Stripe_Plan::create(array(
						"amount" => $amount_subscribe_cents,
						"interval" => "month",
						"name" => $stripe_plan_name,
						"currency" => "usd",
						"id" => $ng_plan_id
					));
				
				
					// Find out if this customer already has a paying item with stripe and if they have a subscription with it
					$current_uid = $_SESSION['uid'];
					$q = mysql_query("SELECT subscriptionid FROM tblhosting WHERE userid='" . $current_uid . "' AND paymentmethod='stripe' AND subscriptionid !=''");
					if (mysql_num_rows($q) > 0) {
						$data = mysql_fetch_array($q);
						$stripe_customer_id = $data[0];
					} else {
						$stripe_customer_id = "";
					}
					
					if ($stripe_customer_id == "") {
						$customer = Stripe_Customer::create(array( // Sign them up for the requested plan and add the customer id into the subscription id
							"card" => $token,
							"plan" => $ng_plan_id,
							"email" => $email
						));
						$cust_id = $customer->id;
						$q = mysql_query("UPDATE tblhosting SET subscriptionid='" . $cust_id . "' WHERE id='" . $ng_plan_id . "'");
					} else { // Create the customer from scratch
						$c = Stripe_Customer::retrieve($stripe_customer_id);
						$c->updateSubscription(array("plan" => "basic", "prorate" => false));
					}
					
					if ($customer->card->address_zip_check == "fail") {
						throw new Exception("zip_check_invalid");
					} else if ($charge->card->address_line1_check == "fail") {
						throw new Exception("address_check_invalid");
					} else if ($charge->card->cvc_check == "fail") {
						throw new Exception("cvc_check_invalid");
					}
						
					if ($multiple == "true") { // Bill the customer once for other items they have too
						$charge = Stripe_Charge::create(array(
							  "amount" => $amount_diff_cents,
							  "currency" => "usd",
							  "customer" => $cust_id,
							  "description" => $description_otp
						));
						
						if ($charge->card->address_zip_check == "fail") {
							throw new Exception("zip_check_invalid");
						} else if ($charge->card->address_line1_check == "fail") {
							throw new Exception("address_check_invalid");
						} else if ($charge->card->cvc_check == "fail") {
							throw new Exception("cvc_check_invalid");
						}
						
					}
					
					// Payment has succeeded, no exceptions were thrown or otherwise caught
					$smartyvalues["success"] = true;
				
				} catch(Stripe_CardError $e) {
				
				$error = $e->getMessage();
				$smartyvalues["processingerror"] = 'Error: ' . $error . '.';
				  
				} catch (Stripe_InvalidRequestError $e) {
				  
				} catch (Stripe_AuthenticationError $e) {
					send_error("authentication",$e);
				} catch (Stripe_ApiConnectionError $e) {
					send_error("network", $e);
				} catch (Stripe_Error $e) {
					send_error("generic", $e);
				} catch (Exception $e) {
				
					if ($e->getMessage() == "zip_check_invalid") {
						$smartyvalues["processingerror"] = 'Error: The address information on your account does not match that of the credit card you are trying to use. Please try again or contact us if the problem persists.';
					} else if ($e->getMessage() == "address_check_invalid") {
						$smartyvalues["processingerror"] = 'The address information on your account does not match that of the credit card you are trying to use. Please try again or contact us if the problem persists.';
					} else if ($e->getMessage() == "cvc_check_invalid") {
						$smartyvalues["processingerror"] = 'The credit card information you specified is not valid. Please try again or contact us if the problem persists.';
					} else {
						send_error("unkown", $e);
					}
				  
				}
				
			} // end of if to check if this is a token acceptance for recurs

		}

	} else { // User is logged in but they shouldn't be here (i.e. they weren't here from an invoice)
		
		header("Location: clientarea.php?action=details");
		
	}

} else {

  header("Location: index.php");

}

# Define the template filename to be used without the .tpl extension

$templatefile = "clientareacreditcard-stripe"; 

outputClientArea($templatefile);

?>