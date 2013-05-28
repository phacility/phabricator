<?php
require './_shared.php';
?>
<h1>WePay Demo App: User Info</h1>
<a href="index.php">Back</a>
<br />

<?php
try {
	$wepay = new WePay($_SESSION['wepay_access_token']);
	$user = $wepay->request('user');
	echo '<dl>';
	foreach ($user as $key => $value) {
		echo "<dt>$key</dt><dd>$value</dd>";
	}
	echo '</dl>';
}
catch (WePayException $e) {
	// Something went wrong - normally you would log
	// this and give your user a more informative message
	echo $e->getMessage();
}
