<?php

final class PhortuneTestExtraPaymentProvider extends PhortunePaymentProvider {

  public function canHandlePaymentMethod(PhortunePaymentMethod $method) {
    $type = $method->getMetadataValue('type');
    return ($type === 'test.multiple');
  }

  protected function executeCharge(
    PhortunePaymentMethod $payment_method,
    PhortuneCharge $charge) {
    return;
  }

}
