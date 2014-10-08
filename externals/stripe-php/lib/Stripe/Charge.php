<?php

class Stripe_Charge extends Stripe_ApiResource
{
  /**
   * @param string $id The ID of the charge to retrieve.
   * @param string|null $apiKey
   *
   * @return Stripe_Charge
   */
  public static function retrieve($id, $apiKey=null)
  {
    $class = get_class();
    return self::_scopedRetrieve($class, $id, $apiKey);
  }

  /**
   * @param array|null $params
   * @param string|null $apiKey
   *
   * @return array An array of Stripe_Charges.
   */
  public static function all($params=null, $apiKey=null)
  {
    $class = get_class();
    return self::_scopedAll($class, $params, $apiKey);
  }

  /**
   * @param array|null $params
   * @param string|null $apiKey
   *
   * @return Stripe_Charge The created charge.
   */
  public static function create($params=null, $apiKey=null)
  {
    $class = get_class();
    return self::_scopedCreate($class, $params, $apiKey);
  }

  /**
   * @return Stripe_Charge The saved charge.
   */
  public function save()
  {
    $class = get_class();
    return self::_scopedSave($class);
  }

  /**
   * @param array|null $params
   *
   * @return Stripe_Charge The refunded charge.
   */
  public function refund($params=null)
  {
    $requestor = new Stripe_ApiRequestor($this->_apiKey);
    $url = $this->instanceUrl() . '/refund';
    list($response, $apiKey) = $requestor->request('post', $url, $params);
    $this->refreshFrom($response, $apiKey);
    return $this;
  }

  /**
   * @param array|null $params
   *
   * @return Stripe_Charge The captured charge.
   */
  public function capture($params=null)
  {
    $requestor = new Stripe_ApiRequestor($this->_apiKey);
    $url = $this->instanceUrl() . '/capture';
    list($response, $apiKey) = $requestor->request('post', $url, $params);
    $this->refreshFrom($response, $apiKey);
    return $this;
  }

  /**
   * @param array|null $params
   *
   * @return array The updated dispute.
   */
  public function updateDispute($params=null)
  {
    $requestor = new Stripe_ApiRequestor($this->_apiKey);
    $url = $this->instanceUrl() . '/dispute';
    list($response, $apiKey) = $requestor->request('post', $url, $params);
    $this->refreshFrom(array('dispute' => $response), $apiKey, true);
    return $this->dispute;
  }

  /**
   * @return Stripe_Charge The updated charge.
   */
  public function closeDispute()
  {
    $requestor = new Stripe_ApiRequestor($this->_apiKey);
    $url = $this->instanceUrl() . '/dispute/close';
    list($response, $apiKey) = $requestor->request('post', $url);
    $this->refreshFrom($response, $apiKey);
    return $this;
  }
}
