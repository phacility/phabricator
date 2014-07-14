<?php

class Stripe_InvoiceItem extends Stripe_ApiResource
{
  /**
   * @param string $id The ID of the invoice item to retrieve.
   * @param string|null $apiKey
   *
   * @return Stripe_InvoiceItem
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
   * @return array An array of Stripe_InvoiceItems.
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
   * @return Stripe_InvoiceItem The created invoice item.
   */
  public static function create($params=null, $apiKey=null)
  {
    $class = get_class();
    return self::_scopedCreate($class, $params, $apiKey);
  }

  /**
   * @return Stripe_InvoiceItem The saved invoice item.
   */
  public function save()
  {
    $class = get_class();
    return self::_scopedSave($class);
  }

  /**
   * @return Stripe_InvoiceItem The deleted invoice item.
   */
  public function delete($params=null)
  {
    $class = get_class();
    return self::_scopedDelete($class, $params);
  }
}
