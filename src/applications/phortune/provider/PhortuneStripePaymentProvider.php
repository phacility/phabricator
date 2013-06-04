<?php

final class PhortuneStripePaymentProvider extends PhortunePaymentProvider {

  public function isEnabled() {
    return $this->getPublishableKey() &&
           $this->getSecretKey();
  }

  public function getProviderType() {
    return 'stripe';
  }

  public function getProviderDomain() {
    return 'stripe.com';
  }

  public function getPaymentMethodDescription() {
    return pht('Add Credit or Debit Card (US and Canada)');
  }

  public function getPaymentMethodIcon() {
    return celerity_get_resource_uri('/rsrc/image/phortune/stripe.png');
  }

  public function getPaymentMethodProviderDescription() {
    return pht('Processed by Stripe');
  }


  public function canHandlePaymentMethod(PhortunePaymentMethod $method) {
    $type = $method->getMetadataValue('type');
    return ($type === 'stripe.customer');
  }

  /**
   * @phutil-external-symbol class Stripe_Charge
   */
  protected function executeCharge(
    PhortunePaymentMethod $method,
    PhortuneCharge $charge) {

    $secret_key = $this->getSecretKey();
    $params = array(
      'amount'      => $charge->getAmountInCents(),
      'currency'    => 'usd',
      'customer'    => $method->getMetadataValue('stripe.customerID'),
      'description' => $charge->getPHID(),
      'capture'     => true,
    );

    $stripe_charge = Stripe_Charge::create($params, $secret_key);
    $id = $stripe_charge->id;
    if (!$id) {
      throw new Exception("Stripe charge call did not return an ID!");
    }

    $charge->setMetadataValue('stripe.chargeID', $id);
  }

  private function getPublishableKey() {
    return PhabricatorEnv::getEnvConfig('phortune.stripe.publishable-key');
  }

  private function getSecretKey() {
    return PhabricatorEnv::getEnvConfig('phortune.stripe.secret-key');
  }


/* -(  Adding Payment Methods  )--------------------------------------------- */


  public function canCreatePaymentMethods() {
    return true;
  }


  /**
   * @phutil-external-symbol class Stripe_Token
   * @phutil-external-symbol class Stripe_Customer
   */
  public function createPaymentMethodFromRequest(
    AphrontRequest $request,
    PhortunePaymentMethod $method,
    array $token) {

    $errors = array();

    $root = dirname(phutil_get_library_root('phabricator'));
    require_once $root.'/externals/stripe-php/lib/Stripe.php';

    $secret_key = $this->getSecretKey();
    $stripe_token = $token['stripeCardToken'];

    // First, make sure the token is valid.
    $info = id(new Stripe_Token())->retrieve($stripe_token, $secret_key);

    $account_phid = $method->getAccountPHID();
    $author_phid = $method->getAuthorPHID();

    $params = array(
      'card' => $stripe_token,
      'description' => $account_phid.':'.$author_phid,
    );

    // Then, we need to create a Customer in order to be able to charge
    // the card more than once. We create one Customer for each card;
    // they do not map to PhortuneAccounts because we allow an account to
    // have more than one active card.
    $customer = Stripe_Customer::create($params, $secret_key);

    $card = $info->card;
    $method
      ->setBrand($card->type)
      ->setLastFourDigits($card->last4)
      ->setExpires($card->exp_year, $card->exp_month)
      ->setMetadata(
        array(
          'type' => 'stripe.customer',
          'stripe.customerID' => $customer->id,
          'stripe.cardToken' => $stripe_token,
        ));

    return $errors;
  }

  public function renderCreatePaymentMethodForm(
    AphrontRequest $request,
    array $errors) {

    $ccform = id(new PhortuneCreditCardForm())
      ->setUser($request->getUser())
      ->setErrors($errors)
      ->addScript('https://js.stripe.com/v2/');

    Javelin::initBehavior(
      'stripe-payment-form',
      array(
        'stripePublishableKey' => $this->getPublishableKey(),
        'formID'               => $ccform->getFormID(),
      ));

    return $ccform->buildForm();
  }

  private function getStripeShortErrorCode($error_code) {
    $prefix = 'cc:stripe:';
    if (strncmp($error_code, $prefix, strlen($prefix))) {
      return null;
    }
    return substr($error_code, strlen($prefix));
  }

  public function validateCreatePaymentMethodToken(array $token) {
    return isset($token['stripeCardToken']);
  }

  public function translateCreatePaymentMethodErrorCode($error_code) {
    $short_code = $this->getStripeShortErrorCode($error_code);

    if ($short_code) {
      static $map = array(
        'error:invalid_number'        => PhortuneErrCode::ERR_CC_INVALID_NUMBER,
        'error:invalid_cvc'           => PhortuneErrCode::ERR_CC_INVALID_CVC,
        'error:invalid_expiry_month'  => PhortuneErrCode::ERR_CC_INVALID_EXPIRY,
        'error:invalid_expiry_year'   => PhortuneErrCode::ERR_CC_INVALID_EXPIRY,
      );

      if (isset($map[$short_code])) {
        return $map[$short_code];
      }
    }

    return $error_code;
  }

  /**
   * See https://stripe.com/docs/api#errors for more information on possible
   * errors.
   */
  public function getCreatePaymentMethodErrorMessage($error_code) {
    $short_code = $this->getStripeShortErrorCode($error_code);
    if (!$short_code) {
      return null;
    }

    switch ($short_code) {
      case 'error:incorrect_number':
        $error_key = 'number';
        $message = pht('Invalid or incorrect credit card number.');
        break;
      case 'error:incorrect_cvc':
        $error_key = 'cvc';
        $message = pht('Card CVC is invalid or incorrect.');
        break;
        $error_key = 'exp';
        $message = pht('Card expiration date is invalid or incorrect.');
        break;
      case 'error:invalid_expiry_month':
      case 'error:invalid_expiry_year':
      case 'error:invalid_cvc':
      case 'error:invalid_number':
        // NOTE: These should be translated into Phortune error codes earlier,
        // so we don't expect to receive them here. They are listed for clarity
        // and completeness. If we encounter one, we treat it as an unknown
        // error.
        break;
      case 'error:invalid_amount':
      case 'error:missing':
      case 'error:card_declined':
      case 'error:expired_card':
      case 'error:duplicate_transaction':
      case 'error:processing_error':
      default:
        // NOTE: These errors currently don't recevive a detailed message.
        // NOTE: We can also end up here with "http:nnn" messages.

        // TODO: At least some of these should have a better message, or be
        // translated into common errors above.
        break;
    }

    return null;
  }

}
