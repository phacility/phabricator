<?php

class Stripe_RateLimitError extends Stripe_InvalidRequestError
{
  public function __construct($message, $param, $httpStatus=null,
      $httpBody=null, $jsonBody=null
  )
  {
    parent::__construct($message, $httpStatus, $httpBody, $jsonBody);
  }
}
