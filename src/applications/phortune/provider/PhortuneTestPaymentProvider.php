<?php

final class PhortuneTestPaymentProvider extends PhortunePaymentProvider {

  public function canHandlePaymentMethod(PhortunePaymentMethod $method) {
    $type = $method->getMetadataValue('type');
    return ($type === 'test.cash' || $type === 'test.multiple');
  }

  protected function executeCharge(
    PhortunePaymentMethod $payment_method,
    PhortuneCharge $charge) {
    return;
  }

}
