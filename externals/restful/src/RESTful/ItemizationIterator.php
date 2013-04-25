<?php

namespace RESTful;

class ItemizationIterator implements \Iterator
{
    protected $_page,
        $_offset = 0;

    public function __construct($resource, $uri, $data = null)
    {
        $this->_page = new Page($resource, $uri, $data);
    }

    // Iterator
    public function current()
    {
        return $this->_page->items[$this->_offset];
    }

    public function key()
    {
        return $this->_page->offset + $this->_offset;
    }

    public function next()
    {
        $this->_offset += 1;
        if ($this->_offset >= count($this->_page->items)) {
            $this->_offset = 0;
            $this->_page = $this->_page->next();
        }
    }

    public function rewind()
    {
        $this->_page = $this->_page->first();
        $this->_offset = 0;
    }

    public function valid()
    {
        return ($this->_page != null && $this->_offset < count($this->_page->items));
    }
}
