<?php

namespace RESTful\Exceptions;

/**
 * Indicates an HTTP level error has occurred. The underlying HTTP response is
 * stored as response member. The response payload fields if any are stored as
 * members of the same name.
 *
 * @see \Httpful\Response
 */
class HTTPError extends Base
{
    public $response;

    public function __construct($response)
    {
        $this->response = $response;
        $this->_objectify($this->response->body);
    }

    protected function _objectify($fields)
    {
        foreach ($fields as $key => $val) {
            $this->$key = $val;
        }
    }
}
