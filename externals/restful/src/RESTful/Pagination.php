<?php

namespace RESTful;

class Pagination implements \IteratorAggregate, \ArrayAccess
{
    public $resource,
        $uri;

    protected $_page,
        $_offset = 0,
        $_size = 25;

    public function __construct($resource, $uri, $data = null)
    {
        $this->resource = $resource;
        $this->uri = $uri;
        if ($data != null) {
            $this->_page = new Page($resource, $uri, $data);
        } else {
            $this->_page = null;
        }
    }

    protected function _getPage($offset = null)
    {
        if ($this->_page == null) {
            $this->_offset = ($offset == null) ? 0 : $offset * $this->_size;
            $uri = $this->_buildUri();
            $this->_page = new Page($this->resource, $uri);
        } elseif ($offset != null) {
            $offset = $offset * $this->_size;
            if ($offset != $this->_offset) {
                $this->_offset = $offset;
                $uri = $this->_buildUri();
                $this->_page = new Page($this->resource, $uri);
            }
        }

        return $this->_page;
    }

    public function total()
    {
        return floor($this->_getPage()->total / $this->_size);
    }

    protected function _buildUri($offset = null)
    {
        # TODO: hacky but works for now
        $offset = ($offset == null) ? $this->_offset : $offset;
        if (strpos($this->uri, '?') === false) {
            $uri = $this->uri . '?';
        } else {
            $uri = $this->uri . '&';
        }
        $uri = $uri . 'offset=' . strval($offset);

        return $uri;
    }

    // IteratorAggregate
    public function getIterator()
    {
        $uri = $this->_buildUri($offset = 0);

        return new PaginationIterator($this->resource, $uri);
    }

    // ArrayAccess
    public function offsetSet($offset, $value)
    {
        throw new \BadMethodCallException(get_class($this) . ' array access is read-only');
    }

    public function offsetExists($offset)
    {
        return (0 <= $offset && $offset < $this->total());
    }

    public function offsetUnset($offset)
    {
        throw new \BadMethodCallException(get_class($this) . ' array access is read-only');
    }

    public function offsetGet($offset)
    {
        return $this->_getPage($offset);
    }
}
