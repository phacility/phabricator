<?php

final class PhortuneNotImplementedException extends Exception {

  public function __construct(PhortunePaymentProvider $provider) {
    return parent::__construct(
      pht(
        "Provider '%s' does not implement this method.",
        get_class($provider)));
  }

}
