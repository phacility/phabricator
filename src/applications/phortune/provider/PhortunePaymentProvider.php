<?php

/**
 * @task addmethod  Adding Payment Methods
 */
abstract class PhortunePaymentProvider extends Phobject {

  private $providerConfig;

  public function setProviderConfig(
    PhortunePaymentProviderConfig $provider_config) {
    $this->providerConfig = $provider_config;
    return $this;
  }

  public function getProviderConfig() {
    return $this->providerConfig;
  }

  /**
   * Return a short name which identifies this provider.
   */
  abstract public function getName();


/* -(  Configuring Providers  )---------------------------------------------- */


  /**
   * Return a human-readable provider name for use on the merchant workflow
   * where a merchant owner adds providers.
   */
  abstract public function getConfigureName();


  /**
   * Return a human-readable provider description for use on the merchant
   * workflow where a merchant owner adds providers.
   */
  abstract public function getConfigureDescription();

  abstract public function getConfigureInstructions();

  abstract public function getConfigureProvidesDescription();

  abstract public function getAllConfigurableProperties();

  abstract public function getAllConfigurableSecretProperties();
  /**
   * Read a dictionary of properties from the provider's configuration for
   * use when editing the provider.
   */
  public function readEditFormValuesFromProviderConfig() {
    $properties = $this->getAllConfigurableProperties();
    $config = $this->getProviderConfig();

    $secrets = $this->getAllConfigurableSecretProperties();
    $secrets = array_fuse($secrets);

    $map = array();
    foreach ($properties as $property) {
      $map[$property] = $config->getMetadataValue($property);
      if (isset($secrets[$property])) {
        $map[$property] = $this->renderConfigurationSecret($map[$property]);
      }
    }

    return $map;
  }


  /**
   * Read a dictionary of properties from a request for use when editing the
   * provider.
   */
  public function readEditFormValuesFromRequest(AphrontRequest $request) {
    $properties = $this->getAllConfigurableProperties();

    $map = array();
    foreach ($properties as $property) {
      $map[$property] = $request->getStr($property);
    }

    return $map;
  }


  abstract public function processEditForm(
    AphrontRequest $request,
    array $values);

  abstract public function extendEditForm(
    AphrontRequest $request,
    AphrontFormView $form,
    array $values,
    array $issues);

  protected function renderConfigurationSecret($value) {
    if (strlen($value)) {
      return str_repeat('*', strlen($value));
    }
    return '';
  }

  public function isConfigurationSecret($value) {
    return preg_match('/^\*+\z/', trim($value));
  }

  abstract public function canRunConfigurationTest();

  public function runConfigurationTest() {
    throw new PhutilMethodNotImplementedException();
  }


/* -(  Selecting Providers  )------------------------------------------------ */


  public static function getAllProviders() {
    return id(new PhutilClassMapQuery())
      ->setAncestorClass(__CLASS__)
      ->execute();
  }

  public function isEnabled() {
    return $this->getProviderConfig()->getIsEnabled();
  }

  abstract public function isAcceptingLivePayments();
  abstract public function getPaymentMethodDescription();
  abstract public function getPaymentMethodIcon();
  abstract public function getPaymentMethodProviderDescription();

  final public function applyCharge(
    PhortunePaymentMethod $payment_method,
    PhortuneCharge $charge) {
    $this->executeCharge($payment_method, $charge);
  }

  final public function refundCharge(
    PhortuneCharge $charge,
    PhortuneCharge $refund) {
    $this->executeRefund($charge, $refund);
  }

  abstract protected function executeCharge(
    PhortunePaymentMethod $payment_method,
    PhortuneCharge $charge);

  abstract protected function executeRefund(
    PhortuneCharge $charge,
    PhortuneCharge $refund);

  abstract public function updateCharge(PhortuneCharge $charge);


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
    throw new PhutilMethodNotImplementedException();
  }


  /**
   * @task addmethod
   */
  public function getCreatePaymentMethodErrorMessage($error_code) {
    throw new PhutilMethodNotImplementedException();
  }


  /**
   * @task addmethod
   */
  public function validateCreatePaymentMethodToken(array $token) {
    throw new PhutilMethodNotImplementedException();
  }


  /**
   * @task addmethod
   */
  public function createPaymentMethodFromRequest(
    AphrontRequest $request,
    PhortunePaymentMethod $method,
    array $token) {
    throw new PhutilMethodNotImplementedException();
  }


  /**
   * @task addmethod
   */
  public function renderCreatePaymentMethodForm(
    AphrontRequest $request,
    array $errors) {
    throw new PhutilMethodNotImplementedException();
  }

  public function getDefaultPaymentMethodDisplayName(
    PhortunePaymentMethod $method) {
    throw new PhutilMethodNotImplementedException();
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

    $description = $this->getPaymentMethodProviderDescription();
    $details = $this->getPaymentMethodDescription();

    $icon = id(new PHUIIconView())
      ->setSpriteSheet(PHUIIconView::SPRITE_LOGIN)
      ->setSpriteIcon($this->getPaymentMethodIcon());

    $button = id(new PHUIButtonView())
      ->setSize(PHUIButtonView::BIG)
      ->setColor(PHUIButtonView::GREY)
      ->setIcon($icon)
      ->setText($description)
      ->setSubtext($details);

    // NOTE: We generate a local URI to make sure the form picks up CSRF tokens.
    $uri = $this->getControllerURI(
      'checkout',
      array(
        'cartID' => $cart->getID(),
      ),
      $local = true);

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
    array $params = array(),
    $local = false) {

    $id = $this->getProviderConfig()->getID();
    $app = PhabricatorApplication::getByClass('PhabricatorPhortuneApplication');
    $path = $app->getBaseURI().'provider/'.$id.'/'.$action.'/';

    $uri = new PhutilURI($path, $params);

    if ($local) {
      return $uri;
    } else {
      return PhabricatorEnv::getURI((string)$uri);
    }
  }

  public function canRespondToControllerAction($action) {
    return false;
  }

  public function processControllerRequest(
    PhortuneProviderActionController $controller,
    AphrontRequest $request) {
    throw new PhutilMethodNotImplementedException();
  }

}
