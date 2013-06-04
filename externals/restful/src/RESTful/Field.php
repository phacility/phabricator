<?php

namespace RESTful;

class Field
{
    public $name;

    public function __construct($name)
    {
        $this->name = $name;
    }

    public function __get($name)
    {
        return new Field($this->name . '.' . $name);
    }

    public function in($vals)
    {
        return new FilterExpression($this->name, 'in', $vals, '!in');
    }

    public function startswith($prefix)
    {
        if (!is_string($prefix)) {
            throw new \InvalidArgumentException('"startswith" prefix  must be a string');
        }

        return new FilterExpression($this->name, 'contains', $prefix);
    }

    public function endswith($suffix)
    {
        if (!is_string($suffix)) {
            throw new \InvalidArgumentException('"endswith" suffix  must be a string');
        }

        return new FilterExpression($this->name, 'contains', $suffix);
    }

    public function contains($fragment)
    {
        if (!is_string($fragment)) {
            throw new \InvalidArgumentException('"contains" fragment must be a string');
        }

        return new FilterExpression($this->name, 'contains', $fragment, '!contains');
    }

    public function eq($val)
    {
        return new FilterExpression($this->name, '=', $val, '!eq');
    }

    public function lt($val)
    {
        return new FilterExpression($this->name, '<', $val, '>=');
    }

    public function lte($val)
    {
        return new FilterExpression($this->name, '<=', $val, '>');
    }

    public function gt($val)
    {
        return new FilterExpression($this->name, '>', $val, '<=');
    }

    public function gte($val)
    {
        return new FilterExpression($this->name, '>=', $val, '<');
    }

    public function asc()
    {
        return new SortExpression($this->name, true);
    }

    public function desc()
    {
        return new SortExpression($this->name, false);
    }
}
