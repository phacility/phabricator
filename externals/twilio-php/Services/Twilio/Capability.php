<?php

/**
 * Twilio Capability Token generator
 *
 * @category Services
 * @package  Services_Twilio
 * @author Jeff Lindsay <jeff.lindsay@twilio.com>
 * @license  http://creativecommons.org/licenses/MIT/ MIT
 */
class Services_Twilio_Capability
{
    public $accountSid;
    public $authToken;
    public $scopes;

    /**
     * Create a new TwilioCapability with zero permissions. Next steps are to
     * grant access to resources by configuring this token through the
     * functions allowXXXX.
     *
     * @param $accountSid the account sid to which this token is granted access
     * @param $authToken the secret key used to sign the token. Note, this auth
     *        token is not visible to the user of the token.
     */
    public function __construct($accountSid, $authToken)
    {
        $this->accountSid = $accountSid;
        $this->authToken = $authToken;
        $this->scopes = array();
		$this->clientName = false;
    }

    /**
     * If the user of this token should be allowed to accept incoming
     * connections then configure the TwilioCapability through this method and
     * specify the client name.
     *
     * @param $clientName
     */
    public function allowClientIncoming($clientName)
    {

        // clientName must be a non-zero length alphanumeric string
        if (preg_match('/\W/', $clientName)) {
            throw new InvalidArgumentException(
                'Only alphanumeric characters allowed in client name.');
        }

        if (strlen($clientName) == 0) {
            throw new InvalidArgumentException(
                'Client name must not be a zero length string.');
        }

		$this->clientName = $clientName;
        $this->allow('client', 'incoming',
            array('clientName' => $clientName));
    }

    /**
     * Allow the user of this token to make outgoing connections.
     *
     * @param $appSid the application to which this token grants access
     * @param $appParams signed parameters that the user of this token cannot
     *        overwrite.
     */
    public function allowClientOutgoing($appSid, array $appParams=array())
    {
        $this->allow('client', 'outgoing', array(
            'appSid' => $appSid,
            'appParams' => http_build_query($appParams, '', '&')));
    }

    /**
     * Allow the user of this token to access their event stream.
     *
     * @param $filters key/value filters to apply to the event stream
     */
    public function allowEventStream(array $filters=array())
    {
        $this->allow('stream', 'subscribe', array(
            'path' => '/2010-04-01/Events',
            'params' => http_build_query($filters, '', '&'),
        ));
    }

    /**
     * Generates a new token based on the credentials and permissions that
     * previously has been granted to this token.
     *
     * @param $ttl the expiration time of the token (in seconds). Default
     *        value is 3600 (1hr)
     * @return the newly generated token that is valid for $ttl seconds
     */
    public function generateToken($ttl = 3600)
    {
        $payload = array(
            'scope' => array(),
            'iss' => $this->accountSid,
            'exp' => time() + $ttl,
        );
        $scopeStrings = array();

        foreach ($this->scopes as $scope) {
			if ($scope->privilege == "outgoing" && $this->clientName)
				$scope->params["clientName"] = $this->clientName;
            $scopeStrings[] = $scope->toString();
        }

        $payload['scope'] = implode(' ', $scopeStrings);
        return JWT::encode($payload, $this->authToken, 'HS256');
    }

    protected function allow($service, $privilege, $params) {
        $this->scopes[] = new ScopeURI($service, $privilege, $params);
    }
}

/**
 * Scope URI implementation
 *
 * Simple way to represent configurable privileges in an OAuth
 * friendly way. For our case, they look like this:
 *
 * scope:<service>:<privilege>?<params>
 *
 * For example:
 * scope:client:incoming?name=jonas
 *
 * @author Jeff Lindsay <jeff.lindsay@twilio.com>
 */
class ScopeURI
{
    public $service;
    public $privilege;
    public $params;

    public function __construct($service, $privilege, $params = array())
    {
        $this->service = $service;
        $this->privilege = $privilege;
        $this->params = $params;
    }

    public function toString()
    {
        $uri = "scope:{$this->service}:{$this->privilege}";
        if (count($this->params)) {
            $uri .= "?".http_build_query($this->params, '', '&');
        }
        return $uri;
    }

    /**
     * Parse a scope URI into a ScopeURI object
     *
     * @param string    $uri  The scope URI
     * @return ScopeURI The parsed scope uri
     */
    public static function parse($uri)
    {
        if (strpos($uri, 'scope:') !== 0) {
            throw new UnexpectedValueException(
                'Not a scope URI according to scheme');
        }

        $parts = explode('?', $uri, 1);
        $params = null;

        if (count($parts) > 1) {
            parse_str($parts[1], $params);
        }

        $parts = explode(':', $parts[0], 2);

        if (count($parts) != 3) {
            throw new UnexpectedValueException(
                'Not enough parts for scope URI');
        }

        list($scheme, $service, $privilege) = $parts;
        return new ScopeURI($service, $privilege, $params);
    }

}

/**
 * JSON Web Token implementation
 *
 * Minimum implementation used by Realtime auth, based on this spec:
 * http://self-issued.info/docs/draft-jones-json-web-token-01.html.
 *
 * @author Neuman Vong <neuman@twilio.com>
 */
class JWT
{
    /**
     * @param string      $jwt    The JWT
     * @param string|null $key    The secret key
     * @param bool        $verify Don't skip verification process
     *
     * @return object The JWT's payload as a PHP object
     */
    public static function decode($jwt, $key = null, $verify = true)
    {
        $tks = explode('.', $jwt);
        if (count($tks) != 3) {
            throw new UnexpectedValueException('Wrong number of segments');
        }
        list($headb64, $payloadb64, $cryptob64) = $tks;
        if (null === ($header = JWT::jsonDecode(JWT::urlsafeB64Decode($headb64)))
        ) {
            throw new UnexpectedValueException('Invalid segment encoding');
        }
        if (null === $payload = JWT::jsonDecode(JWT::urlsafeB64Decode($payloadb64))
        ) {
            throw new UnexpectedValueException('Invalid segment encoding');
        }
        $sig = JWT::urlsafeB64Decode($cryptob64);
        if ($verify) {
            if (empty($header->alg)) {
                throw new DomainException('Empty algorithm');
            }
            if ($sig != JWT::sign("$headb64.$payloadb64", $key, $header->alg)) {
                throw new UnexpectedValueException('Signature verification failed');
            }
        }
        return $payload;
    }

    /**
      * @param object|array $payload PHP object or array
      * @param string       $key     The secret key
      * @param string       $algo    The signing algorithm
      *
      * @return string A JWT
      */
    public static function encode($payload, $key, $algo = 'HS256')
    {
        $header = array('typ' => 'JWT', 'alg' => $algo);

        $segments = array();
        $segments[] = JWT::urlsafeB64Encode(JWT::jsonEncode($header));
        $segments[] = JWT::urlsafeB64Encode(JWT::jsonEncode($payload));
        $signing_input = implode('.', $segments);

        $signature = JWT::sign($signing_input, $key, $algo);
        $segments[] = JWT::urlsafeB64Encode($signature);

        return implode('.', $segments);
    }

    /**
     * @param string $msg    The message to sign
     * @param string $key    The secret key
     * @param string $method The signing algorithm
     *
     * @return string An encrypted message
     */
    public static function sign($msg, $key, $method = 'HS256')
    {
        $methods = array(
            'HS256' => 'sha256',
            'HS384' => 'sha384',
            'HS512' => 'sha512',
        );
        if (empty($methods[$method])) {
            throw new DomainException('Algorithm not supported');
        }
        return hash_hmac($methods[$method], $msg, $key, true);
    }

    /**
     * @param string $input JSON string
     *
     * @return object Object representation of JSON string
     */
    public static function jsonDecode($input)
    {
        $obj = json_decode($input);
        if (function_exists('json_last_error') && $errno = json_last_error()) {
            JWT::handleJsonError($errno);
        }
        else if ($obj === null && $input !== 'null') {
            throw new DomainException('Null result with non-null input');
        }
        return $obj;
    }

    /**
     * @param object|array $input A PHP object or array
     *
     * @return string JSON representation of the PHP object or array
     */
    public static function jsonEncode($input)
    {
        $json = json_encode($input);
        if (function_exists('json_last_error') && $errno = json_last_error()) {
            JWT::handleJsonError($errno);
        }
        else if ($json === 'null' && $input !== null) {
            throw new DomainException('Null result with non-null input');
        }
        return $json;
    }

    /**
     * @param string $input A base64 encoded string
     *
     * @return string A decoded string
     */
    public static function urlsafeB64Decode($input)
    {
        $padlen = 4 - strlen($input) % 4;
        $input .= str_repeat('=', $padlen);
        return base64_decode(strtr($input, '-_', '+/'));
    }

    /**
     * @param string $input Anything really
     *
     * @return string The base64 encode of what you passed in
     */
    public static function urlsafeB64Encode($input)
    {
        return str_replace('=', '', strtr(base64_encode($input), '+/', '-_'));
    }

    /**
     * @param int $errno An error number from json_last_error()
     *
     * @return void
     */
    private static function handleJsonError($errno)
    {
        $messages = array(
            JSON_ERROR_DEPTH => 'Maximum stack depth exceeded',
            JSON_ERROR_CTRL_CHAR => 'Unexpected control character found',
            JSON_ERROR_SYNTAX => 'Syntax error, malformed JSON'
        );
        throw new DomainException(isset($messages[$errno])
            ? $messages[$errno]
            : 'Unknown JSON error: ' . $errno
        );
    }
}
