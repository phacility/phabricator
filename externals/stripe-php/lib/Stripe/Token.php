<?php

class Stripe_Token extends Stripe_ApiResource
{
  /**
   * @param string $id The ID of the token to retrieve.
   * @param string|null $apiKey
   *
   * @return Stripe_Token
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
   * @return Stripe_Coupon The created token.
   */
  public static function create($params=null, $apiKey=null)
  {
    $class = get_class();
    return self::_scopedCreate($class, $params, $apiKey);
  }
}
