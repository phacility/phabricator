<?php

namespace Httpful;

use Httpful\Exception\ConnectionErrorException;

/**
 * Clean, simple class for sending HTTP requests
 * in PHP.
 *
 * There is an emphasis of readability without loosing concise
 * syntax.  As such, you will notice that the library lends
 * itself very nicely to "chaining".  You will see several "alias"
 * methods: more readable method definitions that wrap
 * their more concise counterparts.  You will also notice
 * no public constructor.  This two adds to the readability
 * and "chainabilty" of the library.
 *
 * @author Nate Good <me@nategood.com>
 */
class Request
{

    // Option constants
    const SERIALIZE_PAYLOAD_NEVER   = 0;
    const SERIALIZE_PAYLOAD_ALWAYS  = 1;
    const SERIALIZE_PAYLOAD_SMART   = 2;

    const MAX_REDIRECTS_DEFAULT     = 25;

    public $uri,
           $method                  = Http::GET,
           $headers                 = array(),
           $raw_headers             = '',
           $strict_ssl              = false,
           $content_type,
           $expected_type,
           $additional_curl_opts    = array(),
           $auto_parse              = true,
           $serialize_payload_method = self::SERIALIZE_PAYLOAD_SMART,
           $username,
           $password,
           $serialized_payload,
           $payload,
           $parse_callback,
           $error_callback,
           $follow_redirects        = false,
           $max_redirects           = self::MAX_REDIRECTS_DEFAULT,
           $payload_serializers     = array();

    // Options
    // private $_options = array(
    //     'serialize_payload_method' => self::SERIALIZE_PAYLOAD_SMART
    //     'auto_parse' => true
    // );

    // Curl Handle
    public $_ch,
           $_debug;

    // Template Request object
    private static $_template;

    /**
     * We made the constructor private to force the factory style.  This was
     * done to keep the syntax cleaner and better the support the idea of
     * "default templates".  Very basic and flexible as it is only intended
     * for internal use.
     * @param array $attrs hash of initial attribute values
     */
    private function __construct($attrs = null)
    {
        if (!is_array($attrs)) return;
        foreach ($attrs as $attr => $value) {
            $this->$attr = $value;
        }
    }

    // Defaults Management

    /**
     * Let's you configure default settings for this
     * class from a template Request object.  Simply construct a
     * Request object as much as you want to and then pass it to
     * this method.  It will then lock in those settings from
     * that template object.
     * The most common of which may be default mime
     * settings or strict ssl settings.
     * Again some slight memory overhead incurred here but in the grand
     * scheme of things as it typically only occurs once
     * @param Request $template
     */
    public static function ini(Request $template)
    {
        self::$_template = clone $template;
    }

    /**
     * Reset the default template back to the
     * library defaults.
     */
    public static function resetIni()
    {
        self::_initializeDefaults();
    }

    /**
     * Get default for a value based on the template object
     * @return mixed default value
     * @param string|null $attr Name of attribute (e.g. mime, headers)
     *    if null just return the whole template object;
     */
    public static function d($attr)
    {
        return isset($attr) ? self::$_template->$attr : self::$_template;
    }

    // Accessors

    /**
     * @return bool does the request have a timeout?
     */
    public function hasTimeout()
    {
        return isset($this->timeout);
    }

    /**
     * @return bool has the internal curl request been initialized?
     */
    public function hasBeenInitialized()
    {
        return isset($this->_ch);
    }

    /**
     * @return bool Is this request setup for basic auth?
     */
    public function hasBasicAuth()
    {
        return isset($this->password) && isset($this->username);
    }

    /**
     * @return bool Is this request setup for digest auth?
     */
    public function hasDigestAuth()
    {
        return isset($this->password) && isset($this->username) && $this->additional_curl_opts['CURLOPT_HTTPAUTH'] = CURLAUTH_DIGEST;
    }    

    /**
     * Specify a HTTP timeout
     * @return Request $this
     * @param |int $timeout seconds to timeout the HTTP call
     */
    public function timeout($timeout)
    {
        $this->timeout = $timeout;
        return $this;
    }

    /**
     * If the response is a 301 or 302 redirect, automatically
     * send off another request to that location
     * @return Request $this
     * @param bool|int $follow follow or not to follow or maximal number of redirects
     */
    public function followRedirects($follow = true)
    {
        $this->max_redirects = $follow === true ? self::MAX_REDIRECTS_DEFAULT : max(0, $follow);
        $this->follow_redirects = (bool) $follow;
        return $this;
    }

    /**
     * @return Request $this
     * @see Request::followRedirects()
     */
    public function doNotFollowRedirects()
    {
        return $this->followRedirects(false);
    }

    /**
     * Actually send off the request, and parse the response
     * @return string|associative array of parsed results
     * @throws ConnectionErrorException when unable to parse or communicate w server
     */
    public function send()
    {
        if (!$this->hasBeenInitialized())
            $this->_curlPrep();

        $result = curl_exec($this->_ch);

        if ($result === false) {
            $this->_error(curl_error($this->_ch));
            throw new ConnectionErrorException('Unable to connect.');
        }

        $info = curl_getinfo($this->_ch);
        $response = explode("\r\n\r\n", $result, 2 + $info['redirect_count']);

        $body = array_pop($response);
        $headers = array_pop($response);

        return new Response($body, $headers, $this);
    }
    public function sendIt()
    {
        return $this->send();
    }

    // Setters

    /**
     * @return Request this
     * @param string $uri
     */
    public function uri($uri)
    {
        $this->uri = $uri;
        return $this;
    }

    /**
     * User Basic Auth.
     * Only use when over SSL/TSL/HTTPS.
     * @return Request this
     * @param string $username
     * @param string $password
     */
    public function basicAuth($username, $password)
    {
        $this->username = $username;
        $this->password = $password;
        return $this;
    }
    // @alias of basicAuth
    public function authenticateWith($username, $password)
    {
        return $this->basicAuth($username, $password);
    }
    // @alias of basicAuth
    public function authenticateWithBasic($username, $password)
    {
        return $this->basicAuth($username, $password);
    }

    /**
     * User Digest Auth.
     * @return Request this
     * @param string $username
     * @param string $password
     */
    public function digestAuth($username, $password)
    {
        $this->addOnCurlOption(CURLOPT_HTTPAUTH, CURLAUTH_DIGEST);
        return $this->basicAuth($username, $password);
    }

    // @alias of digestAuth
    public function authenticateWithDigest($username, $password)
    {
        return $this->digestAuth($username, $password);
    } 

    /**
     * @return is this request setup for client side cert?
     */
    public function hasClientSideCert() {
        return isset($this->client_cert) && isset($this->client_key);
    }

    /**
     * Use Client Side Cert Authentication
     * @return Response $this
     * @param string $key file path to client key
     * @param string $cert file path to client cert
     * @param string $passphrase for client key
     * @param string $encoding default PEM
     */
    public function clientSideCert($cert, $key, $passphrase = null, $encoding = 'PEM')
    {
        $this->client_cert          = $cert;
        $this->client_key           = $key;
        $this->client_passphrase    = $passphrase;
        $this->client_encoding      = $encoding;

        return $this;
    }
    // @alias of basicAuth
    public function authenticateWithCert($cert, $key, $passphrase = null, $encoding = 'PEM')
    {
        return $this->clientSideCert($cert, $key, $passphrase, $encoding);
    }

    /**
     * Set the body of the request
     * @return Request this
     * @param mixed $payload
     * @param string $mimeType
     */
    public function body($payload, $mimeType = null)
    {
        $this->mime($mimeType);
        $this->payload = $payload;
        // Iserntentially don't call _serializePayload yet.  Wait until
        // we actually send off the request to convert payload to string.
        // At that time, the `serialized_payload` is set accordingly.
        return $this;
    }

    /**
     * Helper function to set the Content type and Expected as same in
     * one swoop
     * @return Request this
     * @param string $mime mime type to use for content type and expected return type
     */
    public function mime($mime)
    {
        if (empty($mime)) return $this;
        $this->content_type = $this->expected_type = Mime::getFullMime($mime);
        return $this;
    }
    // @alias of mime
    public function sendsAndExpectsType($mime)
    {
        return $this->mime($mime);
    }
    // @alias of mime
    public function sendsAndExpects($mime)
    {
        return $this->mime($mime);
    }

    /**
     * Set the method.  Shouldn't be called often as the preferred syntax
     * for instantiation is the method specific factory methods.
     * @return Request this
     * @param string $method
     */
    public function method($method)
    {
        if (empty($method)) return $this;
        $this->method = $method;
        return $this;
    }

    /**
     * @return Request this
     * @param string $mime
     */
    public function expects($mime)
    {
        if (empty($mime)) return $this;
        $this->expected_type = Mime::getFullMime($mime);
        return $this;
    }
    // @alias of expects
    public function expectsType($mime)
    {
        return $this->expects($mime);
    }

    /**
     * @return Request this
     * @param string $mime
     */
    public function contentType($mime)
    {
        if (empty($mime)) return $this;
        $this->content_type  = Mime::getFullMime($mime);
        return $this;
    }
    // @alias of contentType
    public function sends($mime)
    {
        return $this->contentType($mime);
    }
    // @alias of contentType
    public function sendsType($mime)
    {
        return $this->contentType($mime);
    }

    /**
     * Do we strictly enforce SSL verification?
     * @return Request this
     * @param bool $strict
     */
    public function strictSSL($strict)
    {
        $this->strict_ssl = $strict;
        return $this;
    }
    public function withoutStrictSSL()
    {
        return $this->strictSSL(false);
    }
    public function withStrictSSL()
    {
        return $this->strictSSL(true);
    }

    /**
     * Determine how/if we use the built in serialization by
     * setting the serialize_payload_method
     * The default (SERIALIZE_PAYLOAD_SMART) is...
     *  - if payload is not a scalar (object/array)
     *    use the appropriate serialize method according to
     *    the Content-Type of this request.
     *  - if the payload IS a scalar (int, float, string, bool)
     *    than just return it as is.
     * When this option is set SERIALIZE_PAYLOAD_ALWAYS,
     * it will always use the appropriate
     * serialize option regardless of whether payload is scalar or not
     * When this option is set SERIALIZE_PAYLOAD_NEVER,
     * it will never use any of the serialization methods.
     * Really the only use for this is if you want the serialize methods
     * to handle strings or not (e.g. Blah is not valid JSON, but "Blah"
     * is).  Forcing the serialization helps prevent that kind of error from
     * happening.
     * @return Request $this
     * @param int $mode
     */
    public function serializePayload($mode)
    {
        $this->serialize_payload_method = $mode;
        return $this;
    }

    /**
     * @see Request::serializePayload()
     * @return Request
     */
    public function neverSerializePayload()
    {
        return $this->serializePayload(self::SERIALIZE_PAYLOAD_NEVER);
    }

    /**
     * This method is the default behavior
     * @see Request::serializePayload()
     * @return Request
     */
    public function smartSerializePayload()
    {
        return $this->serializePayload(self::SERIALIZE_PAYLOAD_SMART);
    }

    /**
     * @see Request::serializePayload()
     * @return Request
     */
    public function alwaysSerializePayload()
    {
        return $this->serializePayload(self::SERIALIZE_PAYLOAD_ALWAYS);
    }

    /**
     * Add an additional header to the request
     * Can also use the cleaner syntax of
     * $Request->withMyHeaderName($my_value);
     * @see Request::__call()
     *
     * @return Request this
     * @param string $header_name
     * @param string $value
     */
    public function addHeader($header_name, $value)
    {
        $this->headers[$header_name] = $value;
        return $this;
    }

    /**
     * Add group of headers all at once.  Note: This is
     * here just as a convenience in very specific cases.
     * The preferred "readable" way would be to leverage
     * the support for custom header methods.
     * @return Response $this
     * @param array $headers
     */
    public function addHeaders(array $headers)
    {
        foreach ($headers as $header => $value) {
            $this->addHeader($header, $value);
        }
        return $this;
    }

    /**
     * @return Request
     * @param bool $auto_parse perform automatic "smart"
     *    parsing based on Content-Type or "expectedType"
     *    If not auto parsing, Response->body returns the body
     *    as a string.
     */
    public function autoParse($auto_parse = true)
    {
        $this->auto_parse = $auto_parse;
        return $this;
    }

    /**
     * @see Request::autoParse()
     * @return Request
     */
    public function withoutAutoParsing()
    {
        return $this->autoParse(false);
    }

    /**
     * @see Request::autoParse()
     * @return Request
     */
    public function withAutoParsing()
    {
        return $this->autoParse(true);
    }

    /**
     * Use a custom function to parse the response.
     * @return Request this
     * @param \Closure $callback Takes the raw body of
     *    the http response and returns a mixed
     */
    public function parseWith(\Closure $callback)
    {
        $this->parse_callback = $callback;
        return $this;
    }

    /**
     * @see Request::parseResponsesWith()
     * @return Request $this
     * @param \Closure $callback
     */
    public function parseResponsesWith(\Closure $callback)
    {
        return $this->parseWith($callback);
    }

    /**
     * Register a callback that will be used to serialize the payload
     * for a particular mime type.  When using "*" for the mime
     * type, it will use that parser for all responses regardless of the mime
     * type.  If a custom '*' and 'application/json' exist, the custom
     * 'application/json' would take precedence over the '*' callback.
     *
     * @return Request $this
     * @param string $mime mime type we're registering
     * @param Closure $callback takes one argument, $payload,
     *    which is the payload that we'll be
     */
    public function registerPayloadSerializer($mime, \Closure $callback)
    {
        $this->payload_serializers[Mime::getFullMime($mime)] = $callback;
        return $this;
    }

    /**
     * @see Request::registerPayloadSerializer()
     * @return Request $this
     * @param Closure $callback
     */
    public function serializePayloadWith(\Closure $callback)
    {
        return $this->regregisterPayloadSerializer('*', $callback);
    }

    /**
     * Magic method allows for neatly setting other headers in a
     * similar syntax as the other setters.  This method also allows
     * for the sends* syntax.
     * @return Request this
     * @param string $method "missing" method name called
     *    the method name called should be the name of the header that you
     *    are trying to set in camel case without dashes e.g. to set a
     *    header for Content-Type you would use contentType() or more commonly
     *    to add a custom header like X-My-Header, you would use xMyHeader().
     *    To promote readability, you can optionally prefix these methods with
     *    "with"  (e.g. withXMyHeader("blah") instead of xMyHeader("blah")).
     * @param array $args in this case, there should only ever be 1 argument provided
     *    and that argument should be a string value of the header we're setting
     */
    public function __call($method, $args)
    {
        // This method supports the sends* methods
        // like sendsJSON, sendsForm
        //!method_exists($this, $method) &&
        if (substr($method, 0, 5) === 'sends') {
            $mime = strtolower(substr($method, 5));
            if (Mime::supportsMimeType($mime)) {
                $this->sends(Mime::getFullMime($mime));
                return $this;
            }
            // else {
            //     throw new \Exception("Unsupported Content-Type $mime");
            // }
        }
        if (substr($method, 0, 7) === 'expects') {
            $mime = strtolower(substr($method, 7));
            if (Mime::supportsMimeType($mime)) {
                $this->expects(Mime::getFullMime($mime));
                return $this;
            }
            // else {
            //     throw new \Exception("Unsupported Content-Type $mime");
            // }
        }

        // This method also adds the custom header support as described in the
        // method comments
        if (count($args) === 0)
            return;

        // Strip the sugar.  If it leads with "with", strip.
        // This is okay because: No defined HTTP headers begin with with,
        // and if you are defining a custom header, the standard is to prefix it
        // with an "X-", so that should take care of any collisions.
        if (substr($method, 0, 4) === 'with')
            $method = substr($method, 4);

        // Precede upper case letters with dashes, uppercase the first letter of method
        $header = ucwords(implode('-', preg_split('/([A-Z][^A-Z]*)/', $method, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY)));
        $this->addHeader($header, $args[0]);
        return $this;
    }

    // Internal Functions

    /**
     * This is the default template to use if no
     * template has been provided.  The template
     * tells the class which default values to use.
     * While there is a slight overhead for object
     * creation once per execution (not once per
     * Request instantiation), it promotes readability
     * and flexibility within the class.
     */
    private static function _initializeDefaults()
    {
        // This is the only place you will
        // see this constructor syntax.  It
        // is only done here to prevent infinite
        // recusion.  Do not use this syntax elsewhere.
        // It goes against the whole readability
        // and transparency idea.
        self::$_template = new Request(array('method' => Http::GET));

        // This is more like it...
        self::$_template
            ->withoutStrictSSL();
    }

    /**
     * Set the defaults on a newly instantiated object
     * Doesn't copy variables prefixed with _
     * @return Request this
     */
    private function _setDefaults()
    {
        if (!isset(self::$_template))
            self::_initializeDefaults();
        foreach (self::$_template as $k=>$v) {
            if ($k[0] != '_')
                $this->$k = $v;
        }
        return $this;
    }

    private function _error($error)
    {
        // Default actions write to error log
        // TODO add in support for various Loggers
        error_log($error);
    }

    /**
     * Factory style constructor works nicer for chaining.  This
     * should also really only be used internally.  The Request::get,
     * Request::post syntax is preferred as it is more readable.
     * @return Request
     * @param string $method Http Method
     * @param string $mime Mime Type to Use
     */
    public static function init($method = null, $mime = null)
    {
        // Setup our handlers, can call it here as it's idempotent
        Bootstrap::init();

        // Setup the default template if need be
        if (!isset(self::$_template))
            self::_initializeDefaults();

        $request = new Request();
        return $request
               ->_setDefaults()
               ->method($method)
               ->sendsType($mime)
               ->expectsType($mime);
    }

    /**
     * Does the heavy lifting.  Uses de facto HTTP
     * library cURL to set up the HTTP request.
     * Note: It does NOT actually send the request
     * @return Request $this;
     */
    public function _curlPrep()
    {
        // Check for required stuff
        if (!isset($this->uri))
            throw new \Exception('Attempting to send a request before defining a URI endpoint.');

        $ch = curl_init($this->uri);

        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $this->method);

        if ($this->hasBasicAuth()) {
            curl_setopt($ch, CURLOPT_USERPWD, $this->username . ':' . $this->password);
        }

        if ($this->hasClientSideCert()) {

            if (!file_exists($this->client_key))
                throw new \Exception('Could not read Client Key');

            if (!file_exists($this->client_cert))
                throw new \Exception('Could not read Client Certificate');

            curl_setopt($ch, CURLOPT_SSLCERTTYPE,   $this->client_encoding);
            curl_setopt($ch, CURLOPT_SSLKEYTYPE,    $this->client_encoding);
            curl_setopt($ch, CURLOPT_SSLCERT,       $this->client_cert);
            curl_setopt($ch, CURLOPT_SSLKEY,        $this->client_key);
            curl_setopt($ch, CURLOPT_SSLKEYPASSWD,  $this->client_passphrase);
            // curl_setopt($ch, CURLOPT_SSLCERTPASSWD,  $this->client_cert_passphrase);
        }

        if ($this->hasTimeout()) {
            curl_setopt($ch, CURLOPT_TIMEOUT, $this->timeout);
        }
        
        if ($this->follow_redirects) {
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($ch, CURLOPT_MAXREDIRS, $this->max_redirects);
        }

        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, $this->strict_ssl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        $headers = array();
        // https://github.com/nategood/httpful/issues/37
        // Except header removes any HTTP 1.1 Continue from response headers
        $headers[] = 'Expect:';

        if (!isset($this->headers['User-Agent'])) {
            $headers[] = $this->buildUserAgent();
        }

        $headers[] = "Content-Type: {$this->content_type}";

        // allow custom Accept header if set
        if (!isset($this->headers['Accept'])) {
            // http://pretty-rfc.herokuapp.com/RFC2616#header.accept
            $accept = 'Accept: */*; q=0.5, text/plain; q=0.8, text/html;level=3;';

            if (!empty($this->expected_type)) {
                $accept .= "q=0.9, {$this->expected_type}";
            }

            $headers[] = $accept;
        }

        foreach ($this->headers as $header => $value) {
            $headers[] = "$header: $value";
        }

        $url = \parse_url($this->uri);
        $path = (isset($url['path']) ? $url['path'] : '/').(isset($url['query']) ? '?'.$url['query'] : '');
        $this->raw_headers = "{$this->method} $path HTTP/1.1\r\n";
        $host = (isset($url['host']) ? $url['host'] : 'localhost').(isset($url['port']) ? ':'.$url['port'] : '');
        $this->raw_headers .= "Host: $host\r\n";
        $this->raw_headers .= \implode("\r\n", $headers);
        $this->raw_headers .= "\r\n";

        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        if (isset($this->payload)) {
            $this->serialized_payload = $this->_serializePayload($this->payload);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $this->serialized_payload);
        }

        if ($this->_debug) {
            curl_setopt($ch, CURLOPT_VERBOSE, true);
        }

        curl_setopt($ch, CURLOPT_HEADER, 1);

        // If there are some additional curl opts that the user wants
        // to set, we can tack them in here
        foreach ($this->additional_curl_opts as $curlopt => $curlval) {
            curl_setopt($ch, $curlopt, $curlval);
        }

        $this->_ch = $ch;

        return $this;
    }

    public function buildUserAgent() {
        $user_agent = 'User-Agent: Httpful/' . Httpful::VERSION . ' (cURL/';
        $curl = \curl_version();

        if (isset($curl['version'])) {
            $user_agent .= $curl['version'];
        } else {
            $user_agent .= '?.?.?';
        }

        $user_agent .= ' PHP/'. PHP_VERSION . ' (' . PHP_OS . ')';

        if (isset($_SERVER['SERVER_SOFTWARE'])) {
            $user_agent .= ' ' . \preg_replace('~PHP/[\d\.]+~U', '',
                $_SERVER['SERVER_SOFTWARE']);
        } else {
            if (isset($_SERVER['TERM_PROGRAM'])) {
                $user_agent .= " {$_SERVER['TERM_PROGRAM']}";
            }

            if (isset($_SERVER['TERM_PROGRAM_VERSION'])) {
                $user_agent .= "/{$_SERVER['TERM_PROGRAM_VERSION']}";
            }
        }

        if (isset($_SERVER['HTTP_USER_AGENT'])) {
            $user_agent .= " {$_SERVER['HTTP_USER_AGENT']}";
        }

        $user_agent .= ')';

        return $user_agent;
    }

    /**
     * Semi-reluctantly added this as a way to add in curl opts
     * that are not otherwise accessible from the rest of the API.
     * @return Request $this
     * @param string $curlopt
     * @param mixed $curloptval
     */
    public function addOnCurlOption($curlopt, $curloptval)
    {
        $this->additional_curl_opts[$curlopt] = $curloptval;
        return $this;
    }

    /**
     * Turn payload from structured data into
     * a string based on the current Mime type.
     * This uses the auto_serialize option to determine
     * it's course of action.  See serialize method for more.
     * Renamed from _detectPayload to _serializePayload as of
     * 2012-02-15.
     *
     * Added in support for custom payload serializers.
     * The serialize_payload_method stuff still holds true though.
     * @see Request::registerPayloadSerializer()
     *
     * @return string
     * @param mixed $payload
     */
    private function _serializePayload($payload)
    {
        if (empty($payload) || $this->serialize_payload_method === self::SERIALIZE_PAYLOAD_NEVER)
            return $payload;

        // When we are in "smart" mode, don't serialize strings/scalars, assume they are already serialized
        if ($this->serialize_payload_method === self::SERIALIZE_PAYLOAD_SMART && is_scalar($payload))
            return $payload;

        // Use a custom serializer if one is registered for this mime type
        if (isset($this->payload_serializers['*']) || isset($this->payload_serializers[$this->content_type])) {
            $key = isset($this->payload_serializers[$this->content_type]) ? $this->content_type : '*';
            return call_user_func($this->payload_serializers[$key], $payload);
        }

        return Httpful::get($this->content_type)->serialize($payload);
    }

    /**
     * HTTP Method Get
     * @return Request
     * @param string $uri optional uri to use
     * @param string $mime expected
     */
    public static function get($uri, $mime = null)
    {
        return self::init(Http::GET)->uri($uri)->mime($mime);
    }


    /**
     * Like Request:::get, except that it sends off the request as well
     * returning a response
     * @return Response
     * @param string $uri optional uri to use
     * @param string $mime expected
     */
    public static function getQuick($uri, $mime = null)
    {
        return self::get($uri, $mime)->send();
    }

    /**
     * HTTP Method Post
     * @return Request
     * @param string $uri optional uri to use
     * @param string $payload data to send in body of request
     * @param string $mime MIME to use for Content-Type
     */
    public static function post($uri, $payload = null, $mime = null)
    {
        return self::init(Http::POST)->uri($uri)->body($payload, $mime);
    }

    /**
     * HTTP Method Put
     * @return Request
     * @param string $uri optional uri to use
     * @param string $payload data to send in body of request
     * @param string $mime MIME to use for Content-Type
     */
    public static function put($uri, $payload = null, $mime = null)
    {
        return self::init(Http::PUT)->uri($uri)->body($payload, $mime);
    }

    /**
     * HTTP Method Patch
     * @return Request
     * @param string $uri optional uri to use
     * @param string $payload data to send in body of request
     * @param string $mime MIME to use for Content-Type
     */
    public static function patch($uri, $payload = null, $mime = null)
    {
        return self::init(Http::PATCH)->uri($uri)->body($payload, $mime);
    }

    /**
     * HTTP Method Delete
     * @return Request
     * @param string $uri optional uri to use
     */
    public static function delete($uri, $mime = null)
    {
        return self::init(Http::DELETE)->uri($uri)->mime($mime);
    }

    /**
     * HTTP Method Head
     * @return Request
     * @param string $uri optional uri to use
     */
    public static function head($uri)
    {
        return self::init(Http::HEAD)->uri($uri);
    }

    /**
     * HTTP Method Options
     * @return Request
     * @param string $uri optional uri to use
     */
    public static function options($uri)
    {
        return self::init(Http::OPTIONS)->uri($uri);
    }
}
