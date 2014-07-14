<?php

abstract class Stripe
{
  /**
   * @var string The Stripe API key to be used for requests.
   */
  public static $apiKey;
  /**
   * @var string The base URL for the Stripe API.
   */
  public static $apiBase = 'https://api.stripe.com';
  /**
   * @var string|null The version of the Stripe API to use for requests.
   */
  public static $apiVersion = null;
  /**
   * @var boolean Defaults to true.
   */
  public static $verifySslCerts = true;
  const VERSION = '1.16.0';

  /**
   * @return string The API key used for requests.
   */
  public static function getApiKey()
  {
    return self::$apiKey;
  }

  /**
   * Sets the API key to be used for requests.
   *
   * @param string $apiKey
   */
  public static function setApiKey($apiKey)
  {
    self::$apiKey = $apiKey;
  }

  /**
   * @return string The API version used for requests. null if we're using the
   *    latest version.
   */
  public static function getApiVersion()
  {
    return self::$apiVersion;
  }

  /**
   * @param string $apiVersion The API version to use for requests.
   */
  public static function setApiVersion($apiVersion)
  {
    self::$apiVersion = $apiVersion;
  }

  /**
   * @return boolean
   */
  public static function getVerifySslCerts()
  {
    return self::$verifySslCerts;
  }

  /**
   * @param boolean $verify
   */
  public static function setVerifySslCerts($verify)
  {
    self::$verifySslCerts = $verify;
  }
}
