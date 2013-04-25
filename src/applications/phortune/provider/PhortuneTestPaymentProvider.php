<?php

final class PhortuneTestPaymentProvider extends PhortunePaymentProvider {

  public function isEnabled() {
    return true;
  }

  public function getProviderType() {
    return 'test';
  }

  public function getProviderDomain() {
    return 'example.com';
  }

  public function getPaymentMethodDescription() {
    return pht('Add Mountain of Virtual Wealth');
  }

  public function getPaymentMethodIcon() {
    return celerity_get_resource_uri('/rsrc/image/phortune/test.png');
  }

  public function getPaymentMethodProviderDescription() {
    return pht('Infinite Free Money');
  }

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
