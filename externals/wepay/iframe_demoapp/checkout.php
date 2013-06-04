<?php
/**
 * This PHP script helps you do the iframe checkout
 *
 */


/**
 * Put your API credentials here:
 * Get these from your API app details screen
 * https://stage.wepay.com/app
 */
$client_id = "PUT YOUR CLIENT_ID HERE";
$client_secret = "PUT YOUR CLIENT_SECRET HERE";
$access_token = "PUT YOUR ACCESS TOKEN HERE";
$account_id = "PUT YOUR ACCOUNT_ID HERE"; // you can find your account ID via list_accounts.php which users the /account/find call

/** 
 * Initialize the WePay SDK object 
 */
require '../wepay.php';
Wepay::useStaging($client_id, $client_secret);
$wepay = new WePay($access_token);

/**
 * Make the API request to get the checkout_uri
 * 
 */
try {
	$checkout = $wepay->request('/checkout/create', array(
			'account_id' => $account_id, // ID of the account that you want the money to go to
			'amount' => 100, // dollar amount you want to charge the user
			'short_description' => "this is a test payment", // a short description of what the payment is for
			'type' => "GOODS", // the type of the payment - choose from GOODS SERVICE DONATION or PERSONAL
			'mode' => "iframe", // put iframe here if you want the checkout to be in an iframe, regular if you want the user to be sent to WePay
		)
	);
} catch (WePayException $e) { // if the API call returns an error, get the error message for display later
	$error = $e->getMessage();
}

?>

<html>
	<head>
	</head>
	
	<body>
		
		<h1>Checkout:</h1>
		
		<p>The user will checkout here:</p>
		
		<?php if (isset($error)): ?>
			<h2 style="color:red">ERROR: <?php echo $error ?></h2>
		<?php else: ?>
			<div id="checkout_div"></div>
		
			<script type="text/javascript" src="https://stage.wepay.com/js/iframe.wepay.js">
			</script>
			
			<script type="text/javascript">
			WePay.iframe_checkout("checkout_div", "<?php echo $checkout->checkout_uri ?>");
			</script>
		<?php endif; ?>
	
	</body>
	
</html>