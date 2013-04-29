<?php

namespace RESTful;

class URISpec
{
    public $collection_uri = null,
        $name,
        $idNames;

    public function __construct($name, $idNames, $root = null)
    {
        $this->name = $name;
        if (!is_array($idNames)) {
            $idNames = array($idNames);
        }
        $this->idNames = $idNames;
        if ($root != null) {
            if ($root == '' || substr($root, -1) == '/') {
                $this->collection_uri = $root . $name;
            } else {
                $this->collection_uri = $root . '/' . $name;
            }
        }
    }

    public function match($uri)
    {
        $parts = explode('/', rtrim($uri, "/"));

        // collection
        if ($parts[count($parts) - 1] == $this->name) {

            return array(
                'collection' => true,
            );
        }

        // non-member
        if (count($parts) < count($this->idNames) + 1 ||
            $parts[count($parts) - 1 - count($this->idNames)] != $this->name
        ) {
            return null;
        }

        // member
        $ids = array_combine(
            $this->idNames,
            array_slice($parts, -count($this->idNames))
        );
        $result = array(
            'collection' => false,
            'ids'        => $ids,
        );

        return $result;
    }
}
