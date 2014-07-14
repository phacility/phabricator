<?php

class Stripe_Coupon extends Stripe_ApiResource
{
  /**
   * @param string $id The ID of the coupon to retrieve.
   * @param string|null $apiKey
   *
   * @return Stripe_Coupon
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
   * @return Stripe_Coupon The created coupon.
   */
  public static function create($params=null, $apiKey=null)
  {
    $class = get_class();
    return self::_scopedCreate($class, $params, $apiKey);
  }

  /**
   * @param array|null $params
   *
   * @return Stripe_Coupon The deleted coupon.
   */
  public function delete($params=null)
  {
    $class = get_class();
    return self::_scopedDelete($class, $params);
  }

  /**
   * @param array|null $params
   * @param string|null $apiKey
   *
   * @return array An array of Stripe_Coupons.
   */
  public static function all($params=null, $apiKey=null)
  {
    $class = get_class();
    return self::_scopedAll($class, $params, $apiKey);
  }
}
