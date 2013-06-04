<?php

namespace Httpful;

class Httpful {
    const VERSION = '0.1.7';

    private static $mimeRegistrar = array();
    private static $default = null;

    /**
     * @param string $mime_type
     * @param MimeHandlerAdapter $handler
     */
    public static function register($mimeType, \Httpful\Handlers\MimeHandlerAdapter $handler)
    {
        self::$mimeRegistrar[$mimeType] = $handler;
    }

    /**
     * @param string $mime_type defaults to MimeHandlerAdapter
     * @return MimeHandlerAdapter
     */
    public static function get($mimeType = null)
    {
        if (isset(self::$mimeRegistrar[$mimeType])) {
            return self::$mimeRegistrar[$mimeType];
        }

        if (empty(self::$default)) {
            self::$default = new \Httpful\Handlers\MimeHandlerAdapter();
        }

        return self::$default;
    }

    /**
     * Does this particular Mime Type have a parser registered
     * for it?
     * @return bool
     */
    public static function hasParserRegistered($mimeType)
    {
        return isset(self::$mimeRegistrar[$mimeType]);
    }
}