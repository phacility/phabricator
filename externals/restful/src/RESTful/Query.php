<?php

namespace RESTful;

use RESTful\Exceptions\NoResultFound;
use RESTful\Exceptions\MultipleResultsFound;

class Query extends Itemization
{
    public $filters = array(),
        $sorts = array(),
        $size;

    public function __construct($resource, $uri)
    {
        parent::__construct($resource, $uri);
        $this->size = $this->_size;
        $this->_parseUri($uri);
    }

    private function _parseUri($uri)
    {
        $parsed = parse_url($uri);
        $this->uri = $parsed['path'];
        if (array_key_exists('query', $parsed)) {
            foreach (explode('&', $parsed['query']) as $param) {
                $param = explode('=', $param);
                $key = urldecode($param[0]);
                $val = (count($param) == 1) ? null : urldecode($param[1]);

                // limit
                if ($key == 'limit') {
                    $this->size = $this->_size = $val;
                } // sorts
                else if ($key == 'sort') {
                    array_push($this->sorts, $val);
                } // everything else
                else {
                    if (!array_key_exists($key, $this->filters)) {
                        $this->filters[$key] = array();
                    }
                    if (!is_array($val)) {
                        $val = array($val);
                    }
                    $this->filters[$key] = array_merge($this->filters[$key], $val);
                }
            }
        }
    }

    protected function _buildUri($offset = null)
    {
        // params
        $params = array_merge(
            $this->filters,
            array(
                'sort'   => $this->sorts,
                'limit'  => $this->_size,
                'offset' => ($offset == null) ? $this->_offset : $offset
            )
        );
        $getSingle = function ($v) {
            if (is_array($v) && count($v) == 1)
                return $v[0];
            return $v;
        };
        $params = array_map($getSingle, $params);

        // url encode params
        // NOTE: http://stackoverflow.com/a/8171667/1339571
        $qs = http_build_query($params);
        $qs = preg_replace('/%5B(?:[0-9]|[1-9][0-9]+)%5D=/', '=', $qs);

        return $this->uri . '?' . $qs;
    }

    private function _reset()
    {
        $this->_page = null;
    }

    public function filter($expression)
    {
        if ($expression->op == '=') {
            $field = $expression->field;
        } else {
            $field = $expression->field . '[' . $expression->op . ']';
        }
        if (is_array($expression->val)) {
            $val = implode(',', $expression->val);
        } else {
            $val = $expression->val;
        }
        if (!array_key_exists($field, $this->filters)) {
            $this->filters[$field] = array();
        }
        array_push($this->filters[$field], $val);
        $this->_reset();

        return $this;
    }

    public function sort($expression)
    {
        $dir = $expression->ascending ? 'asc' : 'desc';
        array_push($this->sorts, $expression->field . ',' . $dir);
        $this->_reset();

        return $this;
    }

    public function limit($limit)
    {
        $this->size = $this->_size = $limit;
        $this->_reset();

        return $this;
    }

    public function all()
    {
        $items = array();
        foreach ($this as $item) {
            array_push($items, $item);
        }

        return $items;
    }

    public function first()
    {
        $prev_size = $this->_size;
        $this->_size = 1;
        $page = new Page($this->resource, $this->_buildUri());
        $this->_size = $prev_size;
        $item = count($page->items) != 0 ? $page->items[0] : null;

        return $item;
    }

    public function one()
    {
        $prev_size = $this->_size;
        $this->_size = 2;
        $page = new Page($this->resource, $this->_buildUri());
        $this->_size = $prev_size;
        if (count($page->items) == 1) {
            return $page->items[0];
        }
        if (count($page->items) == 0) {
            throw new NoResultFound();
        }

        throw new MultipleResultsFound();
    }

    public function paginate()
    {
        return new Pagination($this->resource, $this->_buildUri());
    }
}
