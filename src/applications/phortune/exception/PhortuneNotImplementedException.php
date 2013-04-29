<?php

final class PhortuneNotImplementedException extends Exception {

  public function __construct(PhortunePaymentProvider $provider) {
    $class = get_class($provider);
    return parent::__construct(
      "Provider '{$class}' does not implement this method.");
  }

}
