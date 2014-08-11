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

  public static function getProvidersForOneTimePayment() {
    $providers = self::getEnabledProviders();
    foreach ($providers as $key => $provider) {
      if (!$provider->canProcessOneTimePayments()) {
        unset($providers[$key]);
      }
    }
    return $providers;
  }

  public static function getProviderByDigest($digest) {
    $providers = self::getEnabledProviders();
    foreach ($providers as $key => $provider) {
      $provider_digest = PhabricatorHash::digestForIndex($key);
      if ($provider_digest == $digest) {
        return $provider;
      }
    }
    return null;
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
   * @return bool True if this provider can apply charges to the payment method.
   */
  abstract public function canHandlePaymentMethod(
    PhortunePaymentMethod $method);

  final public function applyCharge(
    PhortunePaymentMethod $payment_method,
    PhortuneCharge $charge) {

    $charge->setStatus(PhortuneCharge::STATUS_CHARGING);
    $charge->save();

    $this->executeCharge($payment_method, $charge);

    $charge->setStatus(PhortuneCharge::STATUS_CHARGED);
    $charge->save();
  }

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

  public function getDefaultPaymentMethodDisplayName(
    PhortunePaymentMethod $method) {
    throw new PhortuneNotImplementedException($this);
  }


/* -(  One-Time Payments  )-------------------------------------------------- */


  public function canProcessOneTimePayments() {
    return false;
  }

  public function renderOneTimePaymentButton(
    PhortuneAccount $account,
    PhortuneCart $cart,
    PhabricatorUser $user) {

    require_celerity_resource('phortune-css');

    $icon_uri = $this->getPaymentMethodIcon();
    $description = $this->getPaymentMethodProviderDescription();
    $details = $this->getPaymentMethodDescription();

    $icon = id(new PHUIIconView())
      ->setImage($icon_uri)
      ->addClass('phortune-payment-icon');

    $button = id(new PHUIButtonView())
      ->setSize(PHUIButtonView::BIG)
      ->setColor(PHUIButtonView::GREY)
      ->setIcon($icon)
      ->setText($description)
      ->setSubtext($details);

    $uri = $this->getControllerURI(
      'checkout',
      array(
        'cartID' => $cart->getID(),
      ));

    return phabricator_form(
      $user,
      array(
        'action' => $uri,
        'method' => 'POST',
      ),
      $button);
  }


/* -(  Controllers  )-------------------------------------------------------- */


  final public function getControllerURI(
    $action,
    array $params = array()) {

    $digest = PhabricatorHash::digestForIndex($this->getProviderKey());

    $app = PhabricatorApplication::getByClass('PhabricatorPhortuneApplication');
    $path = $app->getBaseURI().'provider/'.$digest.'/'.$action.'/';

    $uri = new PhutilURI($path);
    $uri->setQueryParams($params);

    return PhabricatorEnv::getURI((string)$uri);
  }

  public function canRespondToControllerAction($action) {
    return false;
  }

  public function processControllerRequest(
    PhortuneProviderController $controller,
    AphrontRequest $request) {
    throw new PhortuneNotImplementedException($this);
  }

}
