<?php
require './_shared.php';

// ** YOU MUST CHANGE THIS FOR THE SAMPLE APP TO WORK **
$redirect_uri = 'http://YOUR SERVER NAME/login.php';
$scope = WePay::getAllScopes();

// If we are already logged in, send the user home
if (!empty($_SESSION['wepay_access_token'])) {
	header('Location: index.php');
	exit;
}

// If the authentication dance returned an error, catch it to avoid a
// redirect loop. This usually indicates some sort of application issue,
// like a domain mismatch on your redirect_uri
if (!empty($_GET['error'])) {
	echo 'Error during user authentication: ';
	echo htmlentities($_GET['error_description']);
	exit;
}

// If we don't have a code from being redirected back here,
// send the user to WePay to grant permissions.
if (empty($_GET['code'])) {
	$uri = WePay::getAuthorizationUri($scope, $redirect_uri);
	header("Location: $uri");
}
else {
	$info = WePay::getToken($_GET['code'], $redirect_uri);
	if ($info) {
		// Normally you'd integrate this into your existing auth system
		$_SESSION['wepay_access_token'] = $info->access_token;
		// If desired, you can also store $info->user_id somewhere
		header('Location: index.php');
	}
	else {
		// Unable to obtain access token
		echo 'Unable to obtain access token from WePay.';
	}
}
