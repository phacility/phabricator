<?php

namespace Balanced;

use Balanced\Resource;
use Balanced\Settings;
use \RESTful\URISpec;

/**
 * Represents an api key. These are used to authenticate you with the api.
 * 
 * Typically you create an initial api key:
 * 
 * <code>
 * print \Balanced\Settings::$api_key == null;
 * $api_key = new \Balanced\APIKey();
 * $api_key = api_key->save();
 * $secret = $api_key->secret;
 * print $secret;
 * </code>
 * 
 * Then save the returned secret (we don't store it) and configure the client
 * to use it:
 * 
 * <code>
 * \Balanced\Settings::$api_key = 'my-api-key-secret';
 * </code> 
 * 
 * You can later add another api key if you'd like to rotate or expire old
 * ones:
 *
 * <code>
 * $api_key = new \Balanced\APIKey();
 * $api_key = api_key->save();
 * $new_secret = $api_key->secret;
 * print $new_secret;
 * 
 * \Balanced\Settings::$api_key = $new_secret;
 *
 * \Balanced\APIKey::query()
 *     ->sort(\Balanced\APIKey::f->created_at->desc())
 *     ->first()
 *     ->delete();
 * </code> 
 */
class APIKey extends Resource
{
    protected static $_uri_spec = null;

    public static function init()
    {
        self::$_uri_spec = new URISpec('api_keys', 'id', '/v1');
        self::$_registry->add(get_called_class());
    }
}
