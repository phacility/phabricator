<?php

namespace RESTful;

abstract class Resource
{
    protected $_collection_uris,
        $_member_uris;

    public static function getClient()
    {
        $class = get_called_class();

        return $class::$_client;
    }

    public static function getRegistry()
    {
        $class = get_called_class();

        return $class::$_registry;
    }

    public static function getURISpec()
    {
        $class = get_called_class();

        return $class::$_uri_spec;
    }

    public function __construct($fields = null)
    {
        if ($fields == null) {
            $fields = array();
        }
        $this->_objectify($fields);
    }

    public function __get($name)
    {
        // collection uri
        if (array_key_exists($name, $this->_collection_uris)) {
            $result = $this->_collection_uris[$name];
            $this->$name = new Collection($result['class'], $result['uri']);

            return $this->$name;
        } // member uri
        else if (array_key_exists($name, $this->_member_uris)) {
            $result = $this->$_collection_uris[$name];
            $response = self::getClient() . get($result['uri']);
            $class = $result['class'];
            $this->$name = new $class($response->body);

            return $this->$name;
        }

        // unknown
        $trace = debug_backtrace();
        trigger_error(
            sprintf('Undefined property via __get(): %s in %s on line %s', $name, $trace[0]['file'], $trace[0]['line']),
            E_USER_NOTICE
        );

        return null;
    }

    public function __isset($name)
    {
        if (array_key_exists($name, $this->_collection_uris) || array_key_exists($name, $this->_member_uris)) {
            return true;
        }

        return false;
    }

    protected function _objectify($fields)
    {
        // initialize uris
        $this->_collection_uris = array();
        $this->_member_uris = array();

        foreach ($fields as $key => $val) {
            // nested uri
            if ((strlen($key) - 3) == strrpos($key, 'uri', 0) && $key != 'uri') {
                $result = self::getRegistry()->match($val);
                if ($result != null) {
                    $name = substr($key, 0, -4);
                    $class = $result['class'];
                    if ($result['collection']) {
                        $this->_collection_uris[$name] = array(
                            'class' => $class,
                            'uri'   => $val,
                        );
                    } else {
                        $this->_member_uris[$name] = array(
                            'class' => $class,
                            'uri'   => $val,
                        );
                    }

                    continue;
                }
            } elseif (is_object($val) && property_exists($val, 'uri')) {
                // nested
                $result = self::getRegistry()->match($val->uri);
                if ($result != null) {
                    $class = $result['class'];
                    if ($result['collection']) {
                        $this->$key = new Collection($class, $val['uri'], $val);
                    } else {
                        $this->$key = new $class($val);
                    }

                    continue;
                }
            } elseif (is_array($val) && array_key_exists('uri', $val)) {
                $result = self::getRegistry()->match($val['uri']);
                if ($result != null) {
                    $class = $result['class'];
                    if ($result['collection']) {
                        $this->$key = new Collection($class, $val['uri'], $val);
                    } else {
                        $this->$key = new $class($val);
                    }

                    continue;
                }
            }

            // default
            $this->$key = $val;
        }
    }

    public static function query()
    {
        $uri_spec = self::getURISpec();
        if ($uri_spec == null || $uri_spec->collection_uri == null) {
            $msg = sprintf('Cannot directly query %s resources', get_called_class());
            throw new \LogicException($msg);
        }

        return new Query(get_called_class(), $uri_spec->collection_uri);
    }

    public static function get($uri)
    {
        # id
        if (strncmp($uri, '/', 1)) {
            $uri_spec = self::getURISpec();
            if ($uri_spec == null || $uri_spec->collection_uri == null) {
                $msg = sprintf('Cannot get %s resources by id %s', $class, $uri);
                throw new \LogicException($msg);
            }
            $uri = $uri_spec->collection_uri . '/' . $uri;
        }

        $response = self::getClient()->get($uri);
        $class = get_called_class();

        return new $class($response->body);
    }

    public function save()
    {
        // payload
        $payload = array();
        foreach ($this as $key => $val) {
            if ($key[0] == '_' || is_object($val)) {
                continue;
            }
            $payload[$key] = $val;
        }

        // update
        if (array_key_exists('uri', $payload)) {
            $uri = $payload['uri'];
            unset($payload['uri']);
            $response = self::getClient()->put($uri, $payload);
        } else {
            // create
            $class = get_class($this);
            if ($class::$_uri_spec == null || $class::$_uri_spec->collection_uri == null) {
                $msg = sprintf('Cannot directly create %s resources', $class);
                throw new \LogicException($msg);
            }
            $response = self::getClient()->post($class::$_uri_spec->collection_uri, $payload);
        }

        // re-objectify
        foreach ($this as $key => $val) {
            unset($this->$key);
        }
        $this->_objectify($response->body);

        return $this;
    }

    public function delete()
    {
        self::getClient()->delete($this->uri);

        return $this;
    }
}
