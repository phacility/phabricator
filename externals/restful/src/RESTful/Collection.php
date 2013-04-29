<?php

namespace RESTful;

class Collection extends Itemization
{
    public function __construct($resource, $uri, $data = null)
    {
        parent::__construct($resource, $uri, $data);
        $this->_parseUri();
    }

    private function _parseUri()
    {
        $parsed = parse_url($this->uri);
        $this->_uri = $parsed['path'];
        if (array_key_exists('query', $parsed)) {
            foreach (explode('&', $parsed['query']) as $param) {
                $param = explode('=', $param);
                $key = urldecode($param[0]);
                $val = (count($param) == 1) ? null : urldecode($param[1]);

                // size
                if ($key == 'limit') {
                    $this->_size = $val;
                }
            }
        }
    }

    public function create($payload)
    {
        $class = $this->resource;
        $client = $class::getClient();
        $response = $client->post($this->uri, $payload);

        return new $this->resource($response->body);
    }

    public function query()
    {
        return new Query($this->resource, $this->uri);
    }

    public function paginate()
    {
        return new Pagination($this->resource, $this->uri);
    }
}
