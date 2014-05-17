<?php

/*
 * Author:   Neuman Vong neuman@twilio.com
 * License:  http://creativecommons.org/licenses/MIT/ MIT
 * Link:     https://twilio-php.readthedocs.org/en/latest/
 */

function Services_Twilio_autoload($className) {
    if (substr($className, 0, 15) != 'Services_Twilio') {
        return false;
    }
    $file = str_replace('_', '/', $className);
    $file = str_replace('Services/', '', $file);
    return include dirname(__FILE__) . "/$file.php";
}

spl_autoload_register('Services_Twilio_autoload');

/**
 * Create a client to talk to the Twilio API.
 *
 *
 * :param string               $sid:      Your Account SID
 * :param string               $token:    Your Auth Token from `your dashboard
 *      <https://www.twilio.com/user/account>`_
 * :param string               $version:  API version to use
 * :param $_http:    A HTTP client for making requests.
 * :type $_http: :php:class:`Services_Twilio_TinyHttp`
 * :param int                  $retryAttempts:
 *      Number of times to retry failed requests. Currently only idempotent
 *      requests (GET's and DELETE's) are retried.
 *
 * Here's an example:
 *
 * .. code-block:: php
 *
 *      require('Services/Twilio.php');
 *      $client = new Services_Twilio('AC123', '456bef', null, null, 3);
 *      // Take some action with the client, etc.
 */
class Services_Twilio extends Services_Twilio_Resource
{
    const USER_AGENT = 'twilio-php/3.12.4';

    protected $http;
    protected $retryAttempts;
    protected $last_response;
    protected $version;
    protected $versions = array('2008-08-01', '2010-04-01');

    public function __construct(
        $sid,
        $token,
        $version = null,
        Services_Twilio_TinyHttp $_http = null,
        $retryAttempts = 1
    ) {
        $this->version = in_array($version, $this->versions) ?
                $version : end($this->versions);

        if (null === $_http) {
            if (!in_array('openssl', get_loaded_extensions())) {
                throw new Services_Twilio_HttpException("The OpenSSL extension is required but not currently enabled. For more information, see http://php.net/manual/en/book.openssl.php");
            }
            if (in_array('curl', get_loaded_extensions())) {
                  $_http = new Services_Twilio_TinyHttp(
                      "https://api.twilio.com",
                      array(
                          "curlopts" => array(
                              CURLOPT_USERAGENT => self::qualifiedUserAgent(phpversion()),
                              CURLOPT_HTTPHEADER => array('Accept-Charset: utf-8'),
                              CURLOPT_CAINFO => dirname(__FILE__) . '/cacert.pem',
                          ),
                      )
                  );
            } else {
                $_http = new Services_Twilio_HttpStream(
                    "https://api.twilio.com",
                    array(
                        "http_options" => array(
                            "http" => array(
                                "user_agent" => self::qualifiedUserAgent(phpversion()),
                                "header" => "Accept-Charset: utf-8\r\n",
                            ),
                            "ssl" => array(
                                'verify_peer' => true,
                                'cafile' => dirname(__FILE__) . '/cacert.pem',
                                'verify_depth' => 5,
                            ),
                        ),
                    )
                );
            }
        }
        $_http->authenticate($sid, $token);
        $this->http = $_http;
        $this->accounts = new Services_Twilio_Rest_Accounts($this, "/{$this->version}/Accounts");
        $this->account = $this->accounts->get($sid);
        $this->retryAttempts = $retryAttempts;
    }

    /**
     * Fully qualified user agent with the current PHP Version.
     *
     * :return: the user agent
     * :rtype: string
     */
    public static function qualifiedUserAgent($php_version) {
        return self::USER_AGENT . " (php $php_version)";
    }

    /**
     * Get the api version used by the rest client
     *
     * :return: the API version in use
     * :returntype: string
     */
    public function getVersion() {
        return $this->version;
    }

    /**
     * Get the retry attempt limit used by the rest client
     *
     * :return: the number of retry attempts
     * :rtype: int
     */
    public function getRetryAttempts() {
        return $this->retryAttempts;
    }

    /**
     * Construct a URI based on initial path, query params, and paging
     * information
     *
     * We want to use the query params, unless we have a next_page_uri from the
     * API.
     *
     * :param string $path: The request path (may contain query params if it's
     *      a next_page_uri)
     * :param array $params: Query parameters to use with the request
     * :param boolean $full_uri: Whether the $path contains the full uri
     *
     * :return: the URI that should be requested by the library
     * :returntype: string
     */
    public static function getRequestUri($path, $params, $full_uri = false) {
        $json_path = $full_uri ? $path : "$path.json";
        if (!$full_uri && !empty($params)) {
            $query_path = $json_path . '?' . http_build_query($params, '', '&');
        } else {
            $query_path = $json_path;
        }
        return $query_path;
    }

    /**
     * Helper method for implementing request retry logic
     *
     * :param array  $callable:      The function that makes an HTTP request
     * :param string $uri:           The URI to request
     * :param int    $retriesLeft:   Number of times to retry
     *
     * :return: The object representation of the resource
     * :rtype: object
     */
    protected function _makeIdempotentRequest($callable, $uri, $retriesLeft) {
        $response = call_user_func_array($callable, array($uri));
        list($status, $headers, $body) = $response;
        if ($status >= 500 && $retriesLeft > 0) {
            return $this->_makeIdempotentRequest($callable, $uri, $retriesLeft - 1);
        } else {
            return $this->_processResponse($response);
        }
    }

    /**
     * GET the resource at the specified path.
     *
     * :param string $path:   Path to the resource
     * :param array  $params: Query string parameters
     * :param boolean  $full_uri: Whether the full URI has been passed as an
     *      argument
     *
     * :return: The object representation of the resource
     * :rtype: object
     */
    public function retrieveData($path, $params = array(),
        $full_uri = false
    ) {
        $uri = self::getRequestUri($path, $params, $full_uri);
        return $this->_makeIdempotentRequest(array($this->http, 'get'),
            $uri, $this->retryAttempts);
    }

    /**
     * DELETE the resource at the specified path.
     *
     * :param string $path:   Path to the resource
     * :param array  $params: Query string parameters
     *
     * :return: The object representation of the resource
     * :rtype: object
     */
    public function deleteData($path, $params = array())
    {
        $uri = self::getRequestUri($path, $params);
        return $this->_makeIdempotentRequest(array($this->http, 'delete'),
            $uri, $this->retryAttempts);
    }

    /**
     * POST to the resource at the specified path.
     *
     * :param string $path:   Path to the resource
     * :param array  $params: Query string parameters
     *
     * :return: The object representation of the resource
     * :rtype: object
     */
    public function createData($path, $params = array())
    {
        $path = "$path.json";
        $headers = array('Content-Type' => 'application/x-www-form-urlencoded');
        $response = $this->http->post(
            $path, $headers, self::buildQuery($params, '')
        );
        return $this->_processResponse($response);
    }

    /**
     * Build a query string from query data
     *
     * :param array $queryData: An associative array of keys and values. The
     *      values can be a simple type or a list, in which case the list is
     *      converted to multiple query parameters with the same key.
     * :param string $numericPrefix:
     * :param string $queryStringStyle: Determine how to build the url
     *      - strict: Build a standards compliant query string without braces (can be hacked by using braces in key)
     *      - php: Build a PHP compatible query string with nested array syntax
     * :return: The encoded query string
     * :rtype: string
     */
    public static function buildQuery($queryData, $numericPrefix = '') {
            $query = '';
            // Loop through all of the $query_data
            foreach ($queryData as $key => $value) {
                // If the key is an int, add the numeric_prefix to the beginning
                if (is_int($key)) {
                    $key = $numericPrefix . $key;
                }

                // If the value is an array, we will end up recursing
                if (is_array($value)) {
                    // Loop through the values
                    foreach ($value as $value2) {
                        // Add an arg_separator if needed
                        if ($query !== '') {
                            $query .= '&';
                        }
                        // Recurse
                        $query .= self::buildQuery(array($key => $value2), $numericPrefix);
                    }
                } else {
                    // Add an arg_separator if needed
                    if ($query !== '') {
                        $query .= '&';
                    }
                    // Add the key and the urlencoded value (as a string)
                    $query .= $key . '=' . urlencode((string)$value);
                }
            }
        return $query;
    }

    /**
     * Convert the JSON encoded resource into a PHP object.
     *
     * :param array $response: 3-tuple containing status, headers, and body
     *
     * :return: PHP object decoded from JSON
     * :rtype: object
     * :throws: A :php:class:`Services_Twilio_RestException` if the Response is
     *      in the 300-500 range of status codes.
     */
    private function _processResponse($response)
    {
        list($status, $headers, $body) = $response;
        if ($status === 204) {
            return true;
        }
        $decoded = json_decode($body);
        if ($decoded === null) {
            throw new Services_Twilio_RestException(
                $status,
                'Could not decode response body as JSON. ' .
                'This likely indicates a 500 server error'
            );
        }
        if (200 <= $status && $status < 300) {
            $this->last_response = $decoded;
            return $decoded;
        }
        throw new Services_Twilio_RestException(
            $status,
            isset($decoded->message) ? $decoded->message : '',
            isset($decoded->code) ? $decoded->code : null,
            isset($decoded->more_info) ? $decoded->more_info : null
        );
    }
}

