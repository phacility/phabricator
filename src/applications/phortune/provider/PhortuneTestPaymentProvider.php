<?php

final class PhortuneTestPaymentProvider extends PhortunePaymentProvider {

  public function isEnabled() {
    return PhabricatorEnv::getEnvConfig('phortune.test.enabled');
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


/* -(  Adding Payment Methods  )--------------------------------------------- */


  public function canCreatePaymentMethods() {
    return true;
  }


  public function translateCreatePaymentMethodErrorCode($error_code) {
    return $error_code;
  }


  public function getCreatePaymentMethodErrorMessage($error_code) {
    return null;
  }


  public function validateCreatePaymentMethodToken(array $token) {
    return true;
  }


  public function createPaymentMethodFromRequest(
    AphrontRequest $request,
    PhortunePaymentMethod $method,
    array $token) {

    $method
      ->setExpires('2050', '01')
      ->setBrand('FreeMoney')
      ->setLastFourDigits('9999');

  }


  /**
   * @task addmethod
   */
  public function renderCreatePaymentMethodForm(
    AphrontRequest $request,
    array $errors) {

    $ccform = id(new PhortuneCreditCardForm())
      ->setUser($request->getUser())
      ->setErrors($errors);

    Javelin::initBehavior(
      'test-payment-form',
      array(
        'formID' => $ccform->getFormID(),
      ));

    return $ccform->buildForm();
  }
}
