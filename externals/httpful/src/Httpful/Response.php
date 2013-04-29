<?php

namespace Httpful;

/**
 * Models an HTTP response
 *
 * @author Nate Good <me@nategood.com>
 */
class Response
{

    public $body,
           $raw_body,
           $headers,
           $raw_headers,
           $request,
           $code = 0,
           $content_type,
           $parent_type,
           $charset,
           $is_mime_vendor_specific = false,
           $is_mime_personal = false;

    private $parsers;
    /**
     * @param string $body
     * @param string $headers
     * @param Request $request
     */
    public function __construct($body, $headers, Request $request)
    {
        $this->request      = $request;
        $this->raw_headers  = $headers;
        $this->raw_body     = $body;

        $this->code         = $this->_parseCode($headers);
        $this->headers      = Response\Headers::fromString($headers);

        $this->_interpretHeaders();

        $this->body         = $this->_parse($body);
    }

    /**
     * Status Code Definitions
     *
     * Informational 1xx
     * Successful    2xx
     * Redirection   3xx
     * Client Error  4xx
     * Server Error  5xx
     *
     * http://pretty-rfc.herokuapp.com/RFC2616#status.codes
     *
     * @return bool Did we receive a 4xx or 5xx?
     */
    public function hasErrors()
    {
        return $this->code >= 400;
    }

    /**
     * @return return bool
     */
    public function hasBody()
    {
        return !empty($this->body);
    }

    /**
     * Parse the response into a clean data structure
     * (most often an associative array) based on the expected
     * Mime type.
     * @return array|string|object the response parse accordingly
     * @param string Http response body
     */
    public function _parse($body)
    {
        // If the user decided to forgo the automatic
        // smart parsing, short circuit.
        if (!$this->request->auto_parse) {
            return $body;
        }

        // If provided, use custom parsing callback
        if (isset($this->request->parse_callback)) {
            return call_user_func($this->request->parse_callback, $body);
        }

        // Decide how to parse the body of the response in the following order
        //  1. If provided, use the mime type specifically set as part of the `Request`
        //  2. If a MimeHandler is registered for the content type, use it
        //  3. If provided, use the "parent type" of the mime type from the response
        //  4. Default to the content-type provided in the response
        $parse_with = $this->request->expected_type;
        if (empty($this->request->expected_type)) {
            $parse_with = Httpful::hasParserRegistered($this->content_type)
                ? $this->content_type
                : $this->parent_type;
        }

       return Httpful::get($parse_with)->parse($body);
    }

    /**
     * Parse text headers from response into
     * array of key value pairs
     * @return array parse headers
     * @param string $headers raw headers
     */
    public function _parseHeaders($headers)
    {
        $headers = preg_split("/(\r|\n)+/", $headers, -1, \PREG_SPLIT_NO_EMPTY);
        $parse_headers = array();
        for ($i = 1; $i < count($headers); $i++) {
            list($key, $raw_value) = explode(':', $headers[$i], 2);
            $key = trim($key);
            $value = trim($raw_value);
            if (array_key_exists($key, $parse_headers)) {
                // See HTTP RFC Sec 4.2 Paragraph 5
                // http://www.w3.org/Protocols/rfc2616/rfc2616-sec4.html#sec4.2
                // If a header appears more than once, it must also be able to
                // be represented as a single header with a comma-separated
                // list of values.  We transform accordingly.
                $parse_headers[$key] .= ',' . $value;
            } else {
                $parse_headers[$key] = $value;
            }
        }
        return $parse_headers;
    }

    public function _parseCode($headers)
    {
        $parts = explode(' ', substr($headers, 0, strpos($headers, "\r\n")));
        if (count($parts) < 2 || !is_numeric($parts[1])) {
            throw new \Exception("Unable to parse response code from HTTP response due to malformed response");
        }
        return intval($parts[1]);
    }

    /**
     * After we've parse the headers, let's clean things
     * up a bit and treat some headers specially
     */
    public function _interpretHeaders()
    {
        // Parse the Content-Type and charset
        $content_type = isset($this->headers['Content-Type']) ? $this->headers['Content-Type'] : '';
        $content_type = explode(';', $content_type);

        $this->content_type = $content_type[0];
        if (count($content_type) == 2 && strpos($content_type[1], '=') !== false) {
            list($nill, $this->charset) = explode('=', $content_type[1]);
        }

        // RFC 2616 states "text/*" Content-Types should have a default
        // charset of ISO-8859-1. "application/*" and other Content-Types
        // are assumed to have UTF-8 unless otherwise specified.
        // http://www.w3.org/Protocols/rfc2616/rfc2616-sec3.html#sec3.7.1
        // http://www.w3.org/International/O-HTTP-charset.en.php
        if (!isset($this->charset)) {
            $this->charset = substr($this->content_type, 5) === 'text/' ? 'iso-8859-1' : 'utf-8';
        }

        // Is vendor type? Is personal type?
        if (strpos($this->content_type, '/') !== false) {
            list($type, $sub_type) = explode('/', $this->content_type);
            $this->is_mime_vendor_specific = substr($sub_type, 0, 4) === 'vnd.';
            $this->is_mime_personal = substr($sub_type, 0, 4) === 'prs.';
        }

        // Parent type (e.g. xml for application/vnd.github.message+xml)
        $this->parent_type = $this->content_type;
        if (strpos($this->content_type, '+') !== false) {
            list($vendor, $this->parent_type) = explode('+', $this->content_type, 2);
            $this->parent_type = Mime::getFullMime($this->parent_type);
        }
    }

    /**
     * @return string
     */
    public function __toString()
    {
        return $this->raw_body;
    }
}
