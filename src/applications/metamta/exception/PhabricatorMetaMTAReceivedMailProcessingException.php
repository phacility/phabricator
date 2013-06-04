<?php

final class PhabricatorMetaMTAReceivedMailProcessingException
  extends Exception {

  private $statusCode;

  public function getStatusCode() {
    return $this->statusCode;
  }

  public function __construct($status_code /* ... */) {
    $args = func_get_args();
    $this->statusCode = $args[0];

    $args = array_slice($args, 1);
    call_user_func_array(array('parent', '__construct'), $args);
  }

}
