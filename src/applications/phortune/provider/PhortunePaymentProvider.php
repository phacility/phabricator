<?php

/**
 * @task addmethod  Adding Payment Methods
 */
abstract class PhortunePaymentProvider {


/* -(  Selecting Providers  )------------------------------------------------ */


  public static function getAllProviders() {
    $objects = id(new PhutilSymbolLoader())
      ->setAncestorClass('PhortunePaymentProvider')
      ->loadObjects();

    return mpull($objects, null, 'getProviderKey');
  }

  public static function getEnabledProviders() {
    $providers = self::getAllProviders();
    foreach ($providers as $key => $provider) {
      if (!$provider->isEnabled()) {
        unset($providers[$key]);
      }
    }
    return $providers;
  }

  public static function getProvidersForAddPaymentMethod() {
    $providers = self::getEnabledProviders();
    foreach ($providers as $key => $provider) {
      if (!$provider->canCreatePaymentMethods()) {
        unset($providers[$key]);
      }
    }
    return $providers;
  }

  abstract public function isEnabled();

  final public function getProviderKey() {
    return $this->getProviderType().'@'.$this->getProviderDomain();
  }


  /**
   * Return a short string which uniquely identifies this provider's protocol
   * type, like "stripe", "paypal", or "balanced".
   */
  abstract public function getProviderType();


  /**
   * Return a short string which uniquely identifies the domain for this
   * provider, like "stripe.com" or "google.com".
   *
   * This is distinct from the provider type so that protocols are not bound
   * to a single domain. This is probably not relevant for payments, but this
   * assumption burned us pretty hard with authentication and it's easy enough
   * to avoid.
   */
  abstract public function getProviderDomain();

  abstract public function getPaymentMethodDescription();
  abstract public function getPaymentMethodIcon();
  abstract public function getPaymentMethodProviderDescription();


  /**
   * Determine of a provider can handle a payment method.
   *
   * @return bool True if this provider can apply charges to the payment
   *              method.
   */
  abstract public function canHandlePaymentMethod(
    PhortunePaymentMethod $method);

  abstract protected function executeCharge(
    PhortunePaymentMethod $payment_method,
    PhortuneCharge $charge);


/* -(  Adding Payment Methods  )--------------------------------------------- */


  /**
   * @task addmethod
   */
  public function canCreatePaymentMethods() {
    return false;
  }


  /**
   * @task addmethod
   */
  public function translateCreatePaymentMethodErrorCode($error_code) {
    throw new PhortuneNotImplementedException($this);
  }


  /**
   * @task addmethod
   */
  public function getCreatePaymentMethodErrorMessage($error_code) {
    throw new PhortuneNotImplementedException($this);
  }


  /**
   * @task addmethod
   */
  public function validateCreatePaymentMethodToken(array $token) {
    throw new PhortuneNotImplementedException($this);
  }


  /**
   * @task addmethod
   */
  public function createPaymentMethodFromRequest(
    AphrontRequest $request,
    PhortunePaymentMethod $method,
    array $token) {
    throw new PhortuneNotImplementedException($this);
  }


  /**
   * @task addmethod
   */
  public function renderCreatePaymentMethodForm(
    AphrontRequest $request,
    array $errors) {
    throw new PhortuneNotImplementedException($this);
  }


}
