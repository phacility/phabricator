<?php

final class PhortuneBalancedPaymentProvider extends PhortunePaymentProvider {

  public function isEnabled() {
    return $this->getMarketplaceURI() &&
           $this->getSecretKey();
  }

  public function getProviderType() {
    return 'balanced';
  }

  public function getProviderDomain() {
    return 'balancedpayments.com';
  }

  public function getPaymentMethodDescription() {
    return pht('Add Credit or Debit Card');
  }

  public function getPaymentMethodIcon() {
    return celerity_get_resource_uri('/rsrc/image/phortune/balanced.png');
  }

  public function getPaymentMethodProviderDescription() {
    return pht('Processed by Balanced');
  }


  public function canHandlePaymentMethod(PhortunePaymentMethod $method) {
    $type = $method->getMetadataValue('type');
    return ($type === 'balanced.account');
  }

  protected function executeCharge(
    PhortunePaymentMethod $method,
    PhortuneCharge $charge) {
    throw new PhortuneNotImplementedException($this);
  }

  private function getMarketplaceURI() {
    return PhabricatorEnv::getEnvConfig('phortune.balanced.marketplace-uri');
  }

  private function getSecretKey() {
    return PhabricatorEnv::getEnvConfig('phortune.balanced.secret-key');
  }


/* -(  Adding Payment Methods  )--------------------------------------------- */


  public function canCreatePaymentMethods() {
    return true;
  }

  public function validateCreatePaymentMethodToken(array $token) {
    return isset($token['balancedMarketplaceURI']);
  }


  /**
   * @phutil-external-symbol class Balanced\Card
   * @phutil-external-symbol class Balanced\Settings
   * @phutil-external-symbol class Balanced\Marketplace
   * @phutil-external-symbol class RESTful\Exceptions\HTTPError
   */
  public function createPaymentMethodFromRequest(
    AphrontRequest $request,
    PhortunePaymentMethod $method,
    array $token) {

    $errors = array();

    $root = dirname(phutil_get_library_root('phabricator'));
    require_once $root.'/externals/httpful/bootstrap.php';
    require_once $root.'/externals/restful/bootstrap.php';
    require_once $root.'/externals/balanced-php/bootstrap.php';

    $account_phid = $method->getAccountPHID();
    $author_phid = $method->getAuthorPHID();
    $description = $account_phid.':'.$author_phid;

    try {
      Balanced\Settings::$api_key = $this->getSecretKey();

      $card = Balanced\Card::get($token['balancedMarketplaceURI']);

      $buyer = Balanced\Marketplace::mine()->createBuyer(
        null,
        $card->uri,
        array(
          'description' => $description,
        ));

    } catch (RESTful\Exceptions\HTTPError $error) {
      // NOTE: This exception doesn't print anything meaningful if it escapes
      // to top level. Replace it with something slightly readable.
      throw new Exception($error->response->body->description);
    }

    $method
      ->setBrand($card->brand)
      ->setLastFourDigits($card->last_four)
      ->setExpires($card->expiration_year, $card->expiration_month)
      ->setMetadata(
        array(
          'type' => 'balanced.account',
          'balanced.accountURI' => $buyer->uri,
          'balanced.cardURI' => $card->uri,
        ));

    return $errors;
  }

  public function renderCreatePaymentMethodForm(
    AphrontRequest $request,
    array $errors) {

    $ccform = id(new PhortuneCreditCardForm())
      ->setUser($request->getUser())
      ->setErrors($errors)
      ->addScript('https://js.balancedpayments.com/v1/balanced.js');

    Javelin::initBehavior(
      'balanced-payment-form',
      array(
        'balancedMarketplaceURI' => $this->getMarketplaceURI(),
        'formID'                 => $ccform->getFormID(),
      ));

    return $ccform->buildForm();
  }

  private function getBalancedShortErrorCode($error_code) {
    $prefix = 'cc:balanced:';
    if (strncmp($error_code, $prefix, strlen($prefix))) {
      return null;
    }
    return substr($error_code, strlen($prefix));
  }

  public function translateCreatePaymentMethodErrorCode($error_code) {
    $short_code = $this->getBalancedShortErrorCode($error_code);

    if ($short_code) {
      static $map = array(
      );

      if (isset($map[$short_code])) {
        return $map[$short_code];
      }
    }

    return $error_code;
  }

  public function getCreatePaymentMethodErrorMessage($error_code) {
    $short_code = $this->getBalancedShortErrorCode($error_code);
    if (!$short_code) {
      return null;
    }

    switch ($short_code) {

      default:
        break;
    }


    return null;
  }

}
