<?php

abstract class Stripe_ApiResource extends Stripe_Object
{
  protected static function _scopedRetrieve($class, $id, $apiKey=null)
  {
    $instance = new $class($id, $apiKey);
    $instance->refresh();
    return $instance;
  }

  public function refresh()
  {
    $requestor = new Stripe_ApiRequestor($this->_apiKey);
    $url = $this->instanceUrl();
    list($response, $apiKey) = $requestor->request('get', $url);
    $this->refreshFrom($response, $apiKey);
    return $this;
   }

  public static function classUrl($class)
  {
    // Useful for namespaces: Foo\Stripe_Charge
    if ($postfix = strrchr($class, '\\'))
      $class = substr($postfix, 1);
    if (substr($class, 0, strlen('Stripe')) == 'Stripe')
      $class = substr($class, strlen('Stripe'));
    $class = str_replace('_', '', $class);
    $name = urlencode($class);
    $name = strtolower($name);
    return "/${name}s";
  }

  public function instanceUrl()
  {
    $id = $this['id'];
    $class = get_class($this);
    if (!$id) {
      throw new Stripe_InvalidRequestError("Could not determine which URL to request: $class instance has invalid ID: $id");
    }
    $id = Stripe_ApiRequestor::utf8($id);
    $base = self::classUrl($class);
    $extn = urlencode($id);
    return "$base/$extn";
  }

  private static function _validateCall($method, $params=null, $apiKey=null)
  {
    if ($params && !is_array($params))
      throw new Stripe_Error("You must pass an array as the first argument to Stripe API method calls.  (HINT: an example call to create a charge would be: \"StripeCharge::create(array('amount' => 100, 'currency' => 'usd', 'card' => array('number' => 4242424242424242, 'exp_month' => 5, 'exp_year' => 2015)))\")");
    if ($apiKey && !is_string($apiKey))
      throw new Stripe_Error('The second argument to Stripe API method calls is an optional per-request apiKey, which must be a string.  (HINT: you can set a global apiKey by "Stripe::setApiKey(<apiKey>)")');
  }

  protected static function _scopedAll($class, $params=null, $apiKey=null)
  {
    self::_validateCall('all', $params, $apiKey);
    $requestor = new Stripe_ApiRequestor($apiKey);
    $url = self::classUrl($class);
    list($response, $apiKey) = $requestor->request('get', $url, $params);
    return Stripe_Util::convertToStripeObject($response, $apiKey);
  }

  protected static function _scopedCreate($class, $params=null, $apiKey=null)
  {
    self::_validateCall('create', $params, $apiKey);
    $requestor = new Stripe_ApiRequestor($apiKey);
    $url = self::classUrl($class);
    list($response, $apiKey) = $requestor->request('post', $url, $params);
    return Stripe_Util::convertToStripeObject($response, $apiKey);
  }

  protected function _scopedSave($class)
  {
    self::_validateCall('save');
    if ($this->_unsavedValues) {
      $requestor = new Stripe_ApiRequestor($this->_apiKey);
      $params = array();
      foreach ($this->_unsavedValues->toArray() as $k)
	$params[$k] = $this->$k;
      $url = $this->instanceUrl();
      list($response, $apiKey) = $requestor->request('post', $url, $params);
      $this->refreshFrom($response, $apiKey);
    }
    return $this;
  }

  protected function _scopedDelete($class, $params=null)
  {
    self::_validateCall('delete');
    $requestor = new Stripe_ApiRequestor($this->_apiKey);
    $url = $this->instanceUrl();
    list($response, $apiKey) = $requestor->request('delete', $url, $params);
    $this->refreshFrom($response, $apiKey);
    return $this;
  }
}
