WePay PHP SDK
=============

WePay's API allows you to easily add payments into your application.

For full documentation, see [WePay's developer documentation](https://www.wepay.com/developer)

Usage
-----

In addition to the samples below, we have included a very basic demo application in the `demoapp` directory. See its README file for additional information.

### Configuration ###

For all requests, you must initialize the SDK with your Client ID and Client Secret, into either Staging or Production mode. All API calls made against WePay's staging environment mirror production in functionality, but do not actually move money. This allows you to develop your application and test the checkout experience from the perspective of your users without spending any money on payments.  Our [full documentation](https://www.wepay.com/developer) contains additional information on test account numbers you can use in addition to "magic" amounts you can use to trigger payment failures and reversals (helpful for testing IPNs).

**Note:** Staging and Production are two completely independent environments and share NO data with each other. This means that in order to use staging, you must register at [stage.wepay.com](https://stage.wepay.com/developer) and get a set of API keys for your Staging application, and must do the same on Production when you are ready to go live. API keys and access tokens granted on stage *can not* be used on Production, and vice-versa.

    <?php
    require './wepay.php';
    WePay::useProduction('YOUR CLIENT ID', 'YOUR CLIENT SECRET'); // To initialize staging, use WePay::useStaging('ID','SECRET'); instead.

### Authentication ###

To obtain an access token for your user, you must redirect the user to WePay for authentication. WePay uses OAuth2 for authorization, which is detailed [in our documentation](https://www.wepay.com/developer/reference/oauth2). To generate the URI to which you must redirect your user, the SDK contains `WePay::getAuthorizationUri($scope, $redirect_uri)`. `$scope` should be an array of scope strings detailed in the documentation. To request full access (most useful for testing, since users may be weary of granting permission to your application if it wants to do too much), you pay pass in `WePay::getAllScopes()`. `$redirect_uri` must be a fully qualified URI where we will send the user after permission is granted (or not granted), and the domain must match your application settings.

If the user grants permission, he or she will be redirected to your `$redirect_uri` with `code=XXXX` appended to the query string. If permission is not granted, we will instead put `error=XXXX` in the query string. If `code` is present, the following will exchange it for an access token. Note that codes are only valid for several minutes, so you should do this immediately after the user is redirected back to your website or application.

    <?php
	if (!empty($_GET['error'])) {
		// user did not grant permissions
	}
	elseif (empty($_GET['code'])) {
		// set $scope and $redirect_uri before doing this
		// this will send the user to WePay to authenticate
		$uri = WePay::getAuthorizationUri($scope, $redirect_uri);
		header("Location: $uri");
		exit;
	}
	else {
		$info = WePay::getToken($_GET['code'], $redirect_uri);
		if ($info) {
			// YOUR ACCESS TOKEN IS HERE
			$access_token = $info->access_token;
		}
		else {
			// Unable to obtain access token
		}
	}

Full details on the access token response are [here](https://www.wepay.com/developer/reference/oauth2#token).

**Note:** If you only need access for yourself (e.g., for a personal storefront), the application settings page automatically creates an access token for you. Simply copy and paste it into your code rather than manually going through the authentication flow.

### Making API Calls ###

With the `$access_token` from above, get a new SDK object:

    <?php
    $wepay = new WePay($access_token);

Then you can make a simple API call. This will list the user's accounts available to your application:

	// (continued from above)
	try {
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

And that's it!  For more detail on what API calls are available, their parameters and responses, and what permissions they require, please see [our documentation](https://www.wepay.com/developer/reference). For some more detailed examples, look in the `demoapp` directory and check the README. Dropping the entire directory in a web-accessible location and adding your API keys should allow you to be up and running in just a few seconds.

### SSL Certificate ###

If making an API call causes the following problem:

	Uncaught exception 'Exception' with message 'cURL error while making API call to WePay: SSL certificate problem, verify that the CA cert is OK. Details: error:14090086:SSL routines:SSL3_GET_SERVER_CERTIFICATE:certificate verify failed'

You can read the solution here: https://support.wepay.com/entries/21095813-problem-with-ssl-certificate-verification
