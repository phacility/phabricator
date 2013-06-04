<?php

namespace RESTful;

class Fields
{
    public function __get($name)
    {
        return new Field($name);
    }
}
