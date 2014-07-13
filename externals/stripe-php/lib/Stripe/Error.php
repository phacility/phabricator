<?php

class Stripe_Error extends Exception
{
  public function __construct($message, $httpStatus=null,
      $httpBody=null, $jsonBody=null
  )
  {
    parent::__construct($message);
    $this->httpStatus = $httpStatus;
    $this->httpBody = $httpBody;
    $this->jsonBody = $jsonBody;
  }

  public function getHttpStatus()
  {
    return $this->httpStatus;
  }

  public function getHttpBody()
  {
    return $this->httpBody;
  }

  public function getJsonBody()
  {
    return $this->jsonBody;
  }
}
