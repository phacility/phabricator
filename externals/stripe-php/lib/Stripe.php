<?php

// Tested on PHP 5.2, 5.3

// This snippet (and some of the curl code) due to the Facebook SDK.
if (!function_exists('curl_init')) {
  throw new Exception('Stripe needs the CURL PHP extension.');
}
if (!function_exists('json_decode')) {
  throw new Exception('Stripe needs the JSON PHP extension.');
}


abstract class Stripe
{
  public static $apiKey;
  public static $apiBase = 'https://api.stripe.com/v1';
  public static $verifySslCerts = true;
  const VERSION = '1.6.3';

  public static function getApiKey()
  {
    return self::$apiKey;
  }

  public static function setApiKey($apiKey)
  {
    self::$apiKey = $apiKey;
  }

  public static function getVerifySslCerts() {
    return self::$verifySslCerts;
  }

  public static function setVerifySslCerts($verify) {
    self::$verifySslCerts = $verify;
  }
}


// Utilities
require(dirname(__FILE__) . '/Stripe/Util.php');
require(dirname(__FILE__) . '/Stripe/Util/Set.php');

// Errors
require(dirname(__FILE__) . '/Stripe/Error.php');
require(dirname(__FILE__) . '/Stripe/ApiError.php');
require(dirname(__FILE__) . '/Stripe/ApiConnectionError.php');
require(dirname(__FILE__) . '/Stripe/AuthenticationError.php');
require(dirname(__FILE__) . '/Stripe/CardError.php');
require(dirname(__FILE__) . '/Stripe/InvalidRequestError.php');

// Plumbing
require(dirname(__FILE__) . '/Stripe/Object.php');
require(dirname(__FILE__) . '/Stripe/ApiRequestor.php');
require(dirname(__FILE__) . '/Stripe/ApiResource.php');

// Stripe API Resources
require(dirname(__FILE__) . '/Stripe/Charge.php');
require(dirname(__FILE__) . '/Stripe/Customer.php');
require(dirname(__FILE__) . '/Stripe/Invoice.php');
require(dirname(__FILE__) . '/Stripe/InvoiceItem.php');
require(dirname(__FILE__) . '/Stripe/Plan.php');
require(dirname(__FILE__) . '/Stripe/Token.php');
require(dirname(__FILE__) . '/Stripe/Coupon.php');
require(dirname(__FILE__) . '/Stripe/Event.php');