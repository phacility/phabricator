<?php

namespace RESTful;

class FilterExpression
{
    public $field,
        $op,
        $val,
        $not_op;

    public function __construct($field, $op, $val, $not_op = null)
    {
        $this->field = $field;
        $this->op = $op;
        $this->val = $val;
        $this->not_op = $not_op;
    }

    public function not()
    {
        if (null === $this->not_op) {
            throw new \LogicException(sprintf('Filter cannot be inverted'));
        }
        $temp = $this->op;
        $this->op = $this->not_op;
        $this->not_op = $temp;

        return $this;
    }
}
