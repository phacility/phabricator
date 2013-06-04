<?php

namespace Balanced;

use Balanced\Resource;
use \RESTful\URISpec;

/*
 * An Event is a snapshot of another resource at a point in time when
 * something significant occurred. Events are created when resources are
 * created, updated, deleted or otherwise change state such as a Credit
 * being marked as failed.
 */
class Event extends Resource
{
    protected static $_uri_spec = null;

    public static function init()
    {
        self::$_uri_spec = new URISpec('events', 'id', '/v1');
        self::$_registry->add(get_called_class());
    }
}
