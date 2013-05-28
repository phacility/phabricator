<?php
require './_shared.php';
?>
<h1>WePay Demo App: Account List</h1>
<a href="index.php">Back</a>
<br />

<?php
try {
	$wepay = new WePay($_SESSION['wepay_access_token']);
	$accounts = $wepay->request('account/find');
	foreach ($accounts as $account) {
		echo "<a href=\"$account->account_uri\">$account->name</a>: $account->description <br />";
	}
}
catch (WePayException $e) {
	// Something went wrong - normally you would log
	// this and give your user a more informative message
	echo $e->getMessage();
}
