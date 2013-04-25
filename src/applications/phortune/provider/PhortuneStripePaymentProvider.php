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
    PhortunePaymentMethod $method) {

    $card_errors = $request->getStr('cardErrors');
    $stripe_token = $request->getStr('stripeToken');

    $errors = array();
    if ($card_errors) {
      $raw_errors = json_decode($card_errors);
      $errors = $this->parseRawCreatePaymentMethodErrors($raw_errors);
    }

    if (!$errors) {
      if (!$stripe_token) {
        $errors[] = pht('There was an unknown error processing your card.');
      }
    }

    if (!$errors) {
      $root = dirname(phutil_get_library_root('phabricator'));
      require_once $root.'/externals/stripe-php/lib/Stripe.php';

      try {
        // First, make sure the token is valid.
        $secret_key = $this->getSecretKey();

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
          ->setName($card->type.' / '.$card->last4)
          ->setExpiresEpoch(strtotime($card->exp_year.'-'.$card->exp_month))
          ->setMetadata(
            array(
              'type'              => 'stripe.customer',
              'stripe.customerID' => $customer->id,
              'stripe.tokenID'    => $stripe_token,
            ));
      } catch (Exception $ex) {
        phlog($ex);
        $errors[] = pht(
          'There was an error communicating with the payments backend.');
      }
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
      ->addScript('https://js.stripe.com/v2/');

    Javelin::initBehavior(
      'stripe-payment-form',
      array(
        'stripePublishableKey' => $this->getPublishableKey(),
        'formID'               => $ccform->getFormID(),
      ));

    return $ccform->buildForm();
  }


  /**
   * Stripe JS and calls to Stripe handle all errors with processing this
   * form. This function takes the raw errors - in the form of an array
   * where each elementt is $type => $message - and figures out what if
   * any fields were invalid and pulls the messages into a flat object.
   *
   * See https://stripe.com/docs/api#errors for more information on possible
   * errors.
   */
  private function parseRawCreatePaymentMethodErrors(array $raw_errors) {
    $errors = array();

    foreach ($raw_errors as $type) {
      $error_key = null;
      $message = pht('A card processing error has occurred.');
      switch ($type) {
        case 'number':
        case 'invalid_number':
        case 'incorrect_number':
          $error_key = 'number';
          $message = pht('Invalid or incorrect credit card number.');
          break;
        case 'cvc':
        case 'invalid_cvc':
        case 'incorrect_cvc':
          $error_key = 'cvc';
          $message = pht('Card CVC is invalid or incorrect.');
          break;
        case 'expiry':
        case 'invalid_expiry_month':
        case 'invalid_expiry_year':
          $error_key = 'exp';
          $message = pht('Card expiration date is invalid or incorrect.');
          break;
        case 'card_declined':
        case 'expired_card':
        case 'duplicate_transaction':
        case 'processing_error':
          // these errors don't map well to field(s) being bad
          break;
        case 'invalid_amount':
        case 'missing':
        default:
          // these errors only happen if we (not the user) messed up so log it
          $error = sprintf('[Stripe Error] %s', $type);
          phlog($error);
          break;
      }

      if ($error_key === null || isset($errors[$error_key])) {
        $errors[] = $message;
      } else {
        $errors[$error_key] = $message;
      }
    }

    return $errors;
  }

}
