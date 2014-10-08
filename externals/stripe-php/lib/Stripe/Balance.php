<?php

class Stripe_Balance extends Stripe_SingletonApiResource
{
  /**
    * @param string|null $apiKey
    *
    * @return Stripe_Balance
    */
  public static function retrieve($apiKey=null)
  {
    $class = get_class();
    return self::_scopedSingletonRetrieve($class, $apiKey);
  }
}
