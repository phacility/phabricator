<?php

class SentryLoggerPlugin extends Phobject {

  public static function registerErrorHandler() {
    PhutilReadableSerializer::printableValue(null);
    PhutilErrorHandler::setErrorListener(
      array(__CLASS__, 'handleError'));
  }

  public static function parse_query_str($str) {
    # result array
    $arr = array();

    # split on outer delimiter
    $pairs = explode('&', $str);

    # loop through each pair
    foreach ($pairs as $i) {
      # split into name and value
      list($name,$value) = explode('=', $i, 2);

      # if name already exists
      if( isset($arr[$name]) ) {
        # stick multiple values into an array
        if( is_array($arr[$name]) ) {
          $arr[$name][] = $value;
        }
        else {
          $arr[$name] = array($arr[$name], $value);
        }
      }
      # otherwise, simply stick it in a scalar
      else {
        $arr[$name] = $value;
      }
    }

    # return result array
    return $arr;
  }

  public static function generate_query_str($array) {
    $params = array();
    foreach ($array as $k => $v) {
      if (is_array($v))
        $params[] = append_params($v, urlencode($k));
      else
        $params[] = urlencode($k) . '=' . urlencode($v);
    }

    return implode('&', $params);
  }

  public static function handleError($event, $value, $metadata) {
    $sentry_dsn = PhabricatorEnv::getEnvConfigIfExists('sentry.dsn');

    if (empty($sentry_dsn)) {
      return;
    }

    $root = phutil_get_library_root('phabricator');
    $root = dirname($root);
    require_once $root . '/externals/extensions/autoload.php';

    $before_send = function(Sentry\Event $event) {
      $integration = new Sentry\Integration\RequestIntegration(new Sentry\Options());
      Sentry\Integration\RequestIntegration::applyToEvent($integration, $event);
      $request = $event->getRequest();

      // Sanitize HTTP POST data
      $fields_re = '/^(__csrf__|token)$/i';
      $sanitize = function(&$item, $key, $fields_re) {
        if (empty($key)) {
          return;
        }
        if (preg_match($fields_re, $key)) {
          $item = '********';
        }
      };
      array_walk_recursive($request['data'], $sanitize, $fields_re);

      if (array_key_exists('params', $request['data'])) {
        try {
          $params = phutil_json_decode($request['data']['params']);
          array_walk_recursive($params, $sanitize, $fields_re);
          $request['data']['params'] = phutil_json_encode($params);
        } catch (PhutilJSONParserException $ex) {
          // data['params'] wasn't JSON, so we're finished sanitizing the request data.
        }
      }

      // Sanitize query string
      $query_data = self::parse_query_str($request['query_string']);
      array_walk_recursive($query_data, $sanitize, $fields_re);
      $request['query_string'] = self::generate_query_str($query_data);

      // Sanitize cookie data
      if (isset($request['cookies'])) {
        $request['cookies']['phsid'] = '********';
      }

      // Sanitize header data
      $headers_re = '/^(Cookie|X-Phabricator-Csfr)$/i';
      array_walk_recursive($request['headers'], $sanitize, $headers_re);

      $event->setRequest($request);
      return $event;
    };

    // Configure the client
    $clientBuilder = Sentry\ClientBuilder::create(['dsn' => $sentry_dsn]);
    $client = $clientBuilder->getClient();
    $options = $client->getOptions();
    $options->setBeforeSendCallback($before_send);

    switch ($event) {
      case PhutilErrorHandler::EXCEPTION:
        // $value is of type Exception
        $client->captureException($value);
        break;
      case PhutilErrorHandler::ERROR:
        // $value is a simple string
        $client->captureMessage($value);
        break;
      default:
        error_log(pht('Unknown event: %s', $event));
        break;
    }
  }
}

