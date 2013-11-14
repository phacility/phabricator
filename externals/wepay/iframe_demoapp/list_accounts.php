<?php
/**
 * This PHP script helps you find your account_id
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

/** 
 * Initialize the WePay SDK object 
 */
require '../wepay.php';
Wepay::useStaging($client_id, $client_secret);
$wepay = new WePay($access_token);

/**
 * Make the API request to get a list of all accounts this user owns
 * 
 */
try {
	$accounts = $wepay->request('/account/find');
} catch (WePayException $e) { // if the API call returns an error, get the error message for display later
	$error = $e->getMessage();
}

?>

<html>
	<head>
	</head>
	
	<body>
		
		<h1>List all accounts:</h1>
		
		<p>The following is a list of all accounts that this user owns</p>
		
		<?php if (isset($error)): ?>
			<h2 style="color:red">ERROR: <?php echo $error ?></h2>
		<?php elseif (empty($accounts)) : ?>
			<h2>You do not have any accounts. Go to <a href="https://stage.wepay.com.com">https://stage.wepay.com</a> to open an account.<h2>
		<?php else: ?>
			<table border="1">
				<thead>
					<tr>
						<td>Account ID</td>
						<td>Account Name</td>
						<td>Account Description</td>
					</tr>
				</thead>
				<tbody>
				<?php foreach ($accounts as $a): ?>
					<tr>
						<td><?php echo $a->account_id ?></td>
						<td><?php echo $a->name ?></td>
						<td><?php echo $a->description ?></td>
					</tr>
				<?php endforeach;?>
				</tbody>
			</table>
		<?php endif; ?>
	
	</body>
	
</html>