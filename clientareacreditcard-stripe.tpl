{include file="$template/pageheader.tpl" title="Credit Card Payment Information"}

{if $success != true}
<script type="text/javascript" src="https://js.stripe.com/v1/"></script>

<script type="text/javascript">

	 Stripe.setPublishableKey('{$stripe_pubkey}');
	 
	 {literal}
     function stripeResponseHandler(status, response) {
      if (response.error) {
        $('.alert-error').show();
        $('.payment-errors').text('Error: ' + response.error.message + '.');
        $('.submit-button').prop('disabled', false);
      } else {
        var $form = $('#payment-form');
        var token = response.id;
        $form.append($('<input type="hidden" name="stripeToken" />').val(token));
        $form.get(0).submit();
      }
    }

    $(function() {
      $('#payment-form').submit(function(event) {   
        
        $('.submit-button').prop('disabled', true);
        
        {/literal}
        
        var name = $('.cardholder-name').val();
        var address_line1 = $('.cardholder-address-l1').val();
        var address_line2 = $('.cardholder-address-l2').val();
        var adddress_city = $('.cardholder-city').val();
        var address_state = $('.cardholder-state').val();
        var address_zip = $('.cardholder-zip').val();

        {literal}Stripe.createToken({ {/literal}
          number: $('.card-number').val(),
          cvc: $('.card-cvc').val(),
          exp_month: $('.card-expiry-month').val(),
          exp_year: $('.card-expiry-year').val(), 
          name: name,
          address_line1: address_line1,
          address_line2: address_line2,
          address_city: adddress_city,
          address_state: address_state,
          address_zip: address_zip,
          address_country: 'US' {literal}
        }, stripeResponseHandler);

        return false;
      });
    });
{/literal}
</script>

{if $processingerror}
<div class="alert alert-error">
    <p class="bold payment-errors">{$processingerror}</p>
</div>
{/if}

<div class="alert alert-error" style="display: none;">
    <p class="bold payment-errors"></p>
</div>

	<p>{$explanation} Please make sure the credit card billing information below is correct before continuing and then click <strong>Pay Now</strong>.</p>

<form class="form-horizontal" action="" method="POST" id="payment-form">
<br/>

  <fieldset class="onecol">

	<div class="styled_title"><h3>Cardholder Information</h3></div>
	
	<div class="control-group">
	    <label class="control-label" for="cardholder-name">Cardholder Name</label>
		<div class="controls">
		     <input type="text" size="20" autocomplete="off" class="cardholder-name" value="{$name}" />
		</div>
	</div>
	
	<div class="control-group">
	    <label class="control-label" for="cardholder-address-l1">Address</label>
		<div class="controls">
		     <input type="text" size="20" autocomplete="off" class="cardholder-address-l1" value="{$address1}" /><br/><br/>
		     <input type="text" size="20" autocomplete="off" class="cardholder-address-l2" value="{$address2}" />
		</div>
	</div>
	
	<div class="control-group">
	    <label class="control-label" for="cardholder-city">City</label>
		<div class="controls">
		     <input type="text" size="20" autocomplete="off" class="cardholder-city" value="{$city}" />
		</div>
	</div>
	
	<div class="control-group">
	    <label class="control-label" for="cardholder-state">State</label>
		<div class="controls">
		     <input type="text" size="20" autocomplete="off" class="cardholder-state" value="{$state}" />
		</div>
	</div>
	
	<div class="control-group">
	    <label class="control-label" for="cardholder-zip">Zip/Postal Code</label>
		<div class="controls">
		     <input type="text" size="20" autocomplete="off" class="cardholder-zip" value="{$zipcode}" />
		</div>
	</div>

	<div class="styled_title"><h3>Card Information</h3></div>
	
    <div class="control-group">
	    <label class="control-label" for="card-number">{$LANG.creditcardcardnumber}</label>
		<div class="controls">
		     <input type="text" size="20" autocomplete="off" class="card-number"/>
		</div>
	</div>
	
	<div class="control-group">
	    <label class="control-label" for="card-cvc">CVC / Security Code</label>
		<div class="controls">
		     <input type="text" size="4" autocomplete="off" class="card-cvc input-mini"/>
		</div>
	</div>

    <div class="control-group">
	    <label class="control-label" for="ccexpirymonth">{$LANG.creditcardcardexpires} (MM/YYYY)</label>
		<div class="controls">
		    <input type="text" size="2" class="card-expiry-month input-mini" /> / <input type="text" size="4" class="card-expiry-year input-small" />
		</div>
	</div>
	
	<input type="hidden" name="ccpay" value="true" />
	<input type="hidden" name="description" value="{$description}" />
	<input type="hidden" name="invoiceid" value="{$invoiceid}" />
	<input type="hidden" name="amount" value="{$amount}" />
	<input type="hidden" name="total_amount" value="{$total_amount}" />
	<input type="hidden" name="planid" value="{$planid}" />
	<input type="hidden" name="planname" value="{$planname}" />
	<input type="hidden" name="multiple" value="{$multiple}" />
	<input type="hidden" name="payfreq" value="{$payfreq}" />

  </fieldset>

  <div class="form-actions">
    <input class="btn btn-primary submit-button" type="submit" value="Pay Now" />
    <a href="viewinvoice.php?id={$invoiceid}" class="btn">Cancel Payment</a>
  </div>

</form>

{/if}
{if $success == true}

<center>
	<h1>Success</h1>
	<p>Your credit card payment was successful.</p>
	<p><a href="viewinvoice.php?id={$invoiceid}&paymentsuccess=true" title="Invoice #{$invoiceid}">Click here</a> to view your paid invoice.</p>
</center>
<br/>
<br/>
<br/>
<br/>
{/if}

<center>{$companyname} values the security of your personal information.<br>Credit card details are transmitted and stored according the highest level of security standards available.</center>

<hr>