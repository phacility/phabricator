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


  /**
   * @phutil-external-symbol class Balanced\Settings
   * @phutil-external-symbol class Balanced\Marketplace
   * @phutil-external-symbol class RESTful\Exceptions\HTTPError
   */
  public function createPaymentMethodFromRequest(
    AphrontRequest $request,
    PhortunePaymentMethod $method) {

    $card_errors = $request->getStr('cardErrors');
    $balanced_data = $request->getStr('balancedCardData');

    $errors = array();
    if ($card_errors) {
      $raw_errors = json_decode($card_errors);
      $errors = $this->parseRawCreatePaymentMethodErrors($raw_errors);
    }

    if (!$errors) {
      $data = json_decode($balanced_data, true);
      if (!is_array($data)) {
        $errors[] = pht('An error occurred decoding card data.');
      }
    }

    if (!$errors) {
      $root = dirname(phutil_get_library_root('phabricator'));
      require_once $root.'/externals/httpful/bootstrap.php';
      require_once $root.'/externals/restful/bootstrap.php';
      require_once $root.'/externals/balanced-php/bootstrap.php';

      $account_phid = $method->getAccountPHID();
      $author_phid = $method->getAuthorPHID();
      $description = $account_phid.':'.$author_phid;

      try {

        Balanced\Settings::$api_key = $this->getSecretKey();
        $buyer = Balanced\Marketplace::mine()->createBuyer(
          null,
          $data['uri'],
          array(
            'description' => $description,
          ));

      } catch (RESTful\Exceptions\HTTPError $error) {
        // NOTE: This exception doesn't print anything meaningful if it escapes
        // to top level. Replace it with something slightly readable.
        throw new Exception($error->response->body->description);
      }

      $exp_string = $data['expiration_year'].'-'.$data['expiration_month'];
      $epoch = strtotime($exp_string);

      $method
        ->setName($data['brand'].' / '.$data['last_four'])
        ->setExpiresEpoch($epoch)
        ->setMetadata(
          array(
            'type' => 'balanced.account',
            'balanced.accountURI' => $buyer->uri,
            'balanced.cardURI' => $data['uri'],
          ));
    }

    return $errors;
  }

  public function renderCreatePaymentMethodForm(
    AphrontRequest $request,
    array $errors) {

    $ccform = id(new PhortuneCreditCardForm())
      ->setUser($request->getUser())
      ->setCardNumberError(isset($errors['number']) ? pht('Invalid') : true)
      ->setCardCVCError(isset($errors['cvc']) ? pht('Invalid') : true)
      ->setCardExpirationError(isset($errors['exp']) ? pht('Invalid') : null)
      ->addScript('https://js.balancedpayments.com/v1/balanced.js');

    Javelin::initBehavior(
      'balanced-payment-form',
      array(
        'balancedMarketplaceURI' => $this->getMarketplaceURI(),
        'formID'                 => $ccform->getFormID(),
      ));

    return $ccform->buildForm();
  }

  private function parseRawCreatePaymentMethodErrors(array $raw_errors) {
    $errors = array();

    foreach ($raw_errors as $error) {
      switch ($error) {
        case 'number':
          $errors[$error] = pht('Card number is incorrect or invalid.');
          break;
        case 'cvc':
          $errors[$error] = pht('CVC code is incorrect or invalid.');
          break;
        case 'exp':
          $errors[$error] = pht('Card expiration date is incorrect.');
          break;
        default:
          $errors[] = $error;
          break;
      }
    }

    return $errors;
  }

}
