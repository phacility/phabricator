<?php

final class PhortuneNoPaymentProviderException extends Exception {

  public function __construct(PhortunePaymentMethod $method) {
    $type = $method->getMetadataValue('type');

    return parent::__construct(
      "No available payment provider can handle charging payments for this ".
      "payment method. You may be able to use a different payment method to ".
      "complete this transaction. The payment method type is '{$type}'.");
  }

}
