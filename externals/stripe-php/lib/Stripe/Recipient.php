<?php

class Stripe_Recipient extends Stripe_ApiResource
{
  /**
   * @param string $id The ID of the recipient to retrieve.
   * @param string|null $apiKey
   *
   * @return Stripe_Recipient
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
   * @return array An array of Stripe_Recipients.
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
   * @return Stripe_Recipient The created recipient.
   */
  public static function create($params=null, $apiKey=null)
  {
    $class = get_class();
    return self::_scopedCreate($class, $params, $apiKey);
  }

  /**
   * @return Stripe_Recipient The saved recipient.
   */
  public function save()
  {
    $class = get_class();
    return self::_scopedSave($class);
  }

  /**
   * @param array|null $params
   *
   * @return Stripe_Recipient The deleted recipient.
   */
  public function delete($params=null)
  {
    $class = get_class();
    return self::_scopedDelete($class, $params);
  }

  
  /**
   * @param array|null $params
   *
   * @return array An array of the recipient's Stripe_Transfers.
   */
  public function transfers($params=null)
  {
    if (!$params)
      $params = array();
    $params['recipient'] = $this->id;
    $transfers = Stripe_Transfer::all($params, $this->_apiKey);
    return $transfers;
  }
}
