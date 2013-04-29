<?php

final class PhortuneTestExtraPaymentProvider extends PhortunePaymentProvider {

  public function isEnabled() {
    return false;
  }

  public function getProviderType() {
    return 'test2';
  }

  public function getProviderDomain() {
    return 'example.com';
  }

  public function getPaymentMethodDescription() {
    return pht('You Should Not Be Able to See This');
  }

  public function getPaymentMethodIcon() {
    return celerity_get_resource_uri('/rsrc/image/phortune/test.png');
  }

  public function getPaymentMethodProviderDescription() {
    return pht('Just for Unit Tests');
  }

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
