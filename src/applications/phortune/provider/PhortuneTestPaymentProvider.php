<?php

final class PhortuneTestPaymentProvider extends PhortunePaymentProvider {

  public function isAcceptingLivePayments() {
    return false;
  }

  public function getName() {
    return pht('Test Payments');
  }

  public function getConfigureName() {
    return pht('Test Payments');
  }

  public function getConfigureDescription() {
    return pht(
      'Adds a test provider to allow you to test payments. This allows '.
      'users to make purchases by clicking a button without actually paying '.
      'any money.');
  }

  public function getConfigureProvidesDescription() {
    return pht('This merchant accepts test payments.');
  }

  public function getConfigureInstructions() {
    return pht('This providers does not require any special configuration.');
  }

  public function canRunConfigurationTest() {
    return false;
  }

  public function getPaymentMethodDescription() {
    return pht('Add Mountain of Virtual Wealth');
  }

  public function getPaymentMethodIcon() {
    return 'TestPayment';
  }

  public function getPaymentMethodProviderDescription() {
    return pht('Infinite Free Money');
  }

  public function getDefaultPaymentMethodDisplayName(
    PhortunePaymentMethod $method) {
    return pht('Vast Wealth');
  }

  protected function executeCharge(
    PhortunePaymentMethod $payment_method,
    PhortuneCharge $charge) {
    return;
  }

  protected function executeRefund(
    PhortuneCharge $charge,
    PhortuneCharge $refund) {
    return;
  }

  public function updateCharge(PhortuneCharge $charge) {
    return;
  }

  public function getAllConfigurableProperties() {
    return array();
  }

  public function getAllConfigurableSecretProperties() {
    return array();
  }

  public function processEditForm(
    AphrontRequest $request,
    array $values) {

    $errors = array();
    $issues = array();
    $values = array();

    return array($errors, $issues, $values);
  }

  public function extendEditForm(
    AphrontRequest $request,
    AphrontFormView $form,
    array $values,
    array $issues) {
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
      ->setLastFourDigits('9999')
      ->setMetadata(
        array(
          'type' => 'test.wealth',
        ));

    return array();
  }


  /**
   * @task addmethod
   */
  public function renderCreatePaymentMethodForm(
    AphrontRequest $request,
    array $errors) {

    $ccform = id(new PhortuneCreditCardForm())
      ->setSecurityAssurance(
        pht('This is a test payment provider.'))
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
