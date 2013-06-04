<?php
require './_shared.php';
?>
<h1>WePay Demo App: Open Account</h1>
<a href="index.php">Back</a>
<br />

<?php
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
	if (isset($_POST['account_name']) && isset($_POST['account_description'])) {
		// WePay sanitizes its own data, but displaying raw POST data on your own site is a XSS security hole.
		$name = htmlentities($_POST['account_name']);
		$desc = htmlentities($_POST['account_description']);
		try {
			$wepay = new WePay($_SESSION['wepay_access_token']);
			$account = $wepay->request('account/create', array(
				'name' => $name,
				'description' => $desc,
			));
			echo "Created account $name for '$desc'! View on WePay at <a href=\"$account->account_uri\">$account->account_uri</a>. See all of your accounts <a href=\"accountlist.php\">here</a>.";
		}
		catch (WePayException $e) {
			// Something went wrong - normally you would log
			// this and give your user a more informative message
			echo $e->getMessage();
		}
	}
	else {
		echo 'Account name and description are both required.';
	}
}
?>

<form method="post">
	<fieldset>
		<legend>Account Info</legend>

		<label for="account_name">Account Name:</label><br />
		<input type="text" id="account_name" name="account_name" placeholder="Ski Trip Savings"/>

		<br /><br />

		<label for="account_description">Account Description: </label><br />
		<textarea name="account_description" rows="10" cols="40" placeholder="Saving up some dough for our ski trip!"></textarea>

		<br /><br />

		<input type="submit" value="Open account" />
	</fieldset>
</form>
