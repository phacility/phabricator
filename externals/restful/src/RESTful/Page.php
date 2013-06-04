<?php

namespace RESTful;

class Page
{
    public $resource,
        $total,
        $items,
        $offset,
        $limit;

    private $_first_uri,
        $_previous_uri,
        $_next_uri,
        $_last_uri;

    public function __construct($resource, $uri, $data = null)
    {
        $this->resource = $resource;
        if ($data == null) {
            $client = $resource::getClient();
            $data = $client->get($uri)->body;
        }
        $this->total = $data->total;
        $this->items = array_map(
            function ($x) use ($resource) {
                return new $resource($x);
            },
            $data->items);
        $this->offset = $data->offset;
        $this->limit = $data->limit;
        $this->_first_uri = property_exists($data, 'first_uri') ? $data->first_uri : null;
        $this->_previous_uri = property_exists($data, 'previous_uri') ? $data->previous_uri : null;
        $this->_next_uri = property_exists($data, 'next_uri') ? $data->next_uri : null;
        $this->_last_uri = property_exists($data, 'last_uri') ? $data->last_uri : null;
    }

    public function first()
    {
        return new Page($this->resource, $this->_first_uri);
    }

    public function next()
    {
        if (!$this->hasNext()) {
            return null;
        }

        return new Page($this->resource, $this->_next_uri);
    }

    public function hasNext()
    {
        return $this->_next_uri != null;
    }

    public function previous()
    {
        return new Page($this->resource, $this->_previous_uri);
    }

    public function hasPrevious()
    {
        return $this->_previous_uri != null;
    }

    public function last()
    {
        return new Page($this->resource, $this->_last_uri);
    }
}
