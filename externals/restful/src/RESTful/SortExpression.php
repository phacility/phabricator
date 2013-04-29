<?php

namespace RESTful;

class SortExpression
{
    public $name,
        $ascending;

    public function __construct($field, $ascending = true)
    {
        $this->field = $field;
        $this->ascending = $ascending;
    }
}
