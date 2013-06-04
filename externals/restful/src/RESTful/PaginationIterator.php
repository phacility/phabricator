<?php

namespace RESTful;

class PaginationIterator implements \Iterator
{
    public function __construct($resource, $uri, $data = null)
    {
        $this->_page = new Page($resource, $uri, $data);
    }

    // Iterator
    public function current()
    {
        return $this->_page;
    }

    public function key()
    {
        return $this->_page->index;
    }

    public function next()
    {
        $this->_page = $this->_page->next();
    }

    public function rewind()
    {
        $this->_page = $this->_page->first();
    }

    public function valid()
    {
        return $this->_page != null;
    }
}
