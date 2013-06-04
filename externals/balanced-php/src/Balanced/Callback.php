<?php

namespace Balanced;

use Balanced\Resource;
use \RESTful\URISpec;

/*
 * A Callback is a publicly accessible location that can receive POSTed JSON
 * data whenever an Event is generated.
 *
 * You create these using Balanced\Marketplace->createCallback.
 *
 */
class Callback extends Resource
{
    protected static $_uri_spec = null;

    public static function init()
    {
        self::$_uri_spec = new URISpec('callbacks', 'id');
        self::$_registry->add(get_called_class());
    }
}
