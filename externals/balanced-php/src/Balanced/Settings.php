<?php

namespace Balanced;

/**
 * Configurable settings.
 *
 *  You can either set these settings individually:
 *
 *  <code>
 *  \Balanced\Settngs::api_key = 'my-api-key-secret';
 *  </code>
 *
 *  or all at once:
 *
 *  <code>
 *  \Balanced\Settngs::configure(
 *      'https://api.balancedpayments.com',
 *      'my-api-key-secret'
 *      );
 *  </code>
 */
class Settings
{
    const VERSION = '0.7.1';

    public static $url_root = 'https://api.balancedpayments.com',
                  $api_key = null,
                  $agent = 'balanced-php',
                  $version = Settings::VERSION;

    /**
     * Configure all settings.
     *
     * @param string url_root The root (schema://hostname[:port]) to use when constructing api URLs.
     * @param string api_key The api key secret to use for authenticating when talking to the api. If null then api usage is limited to uauthenticated endpoints.
     */
    public static function configure($url_root, $api_key)
    {
        self::$url_root= $url_root;
        self::$api_key = $api_key;
    }
}
