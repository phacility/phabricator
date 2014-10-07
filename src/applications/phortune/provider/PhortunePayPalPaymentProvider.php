<?php

final class PhortunePayPalPaymentProvider extends PhortunePaymentProvider {

  const PAYPAL_API_USERNAME   = 'paypal.api-username';
  const PAYPAL_API_PASSWORD   = 'paypal.api-password';
  const PAYPAL_API_SIGNATURE  = 'paypal.api-signature';
  const PAYPAL_MODE           = 'paypal.mode';

  public function isEnabled() {
    // TODO: See note in processControllerRequest().
    return false;

    return $this->getPaypalAPIUsername() &&
           $this->getPaypalAPIPassword() &&
           $this->getPaypalAPISignature();
  }

  public function getName() {
    return pht('PayPal');
  }

  public function getConfigureName() {
    return pht('Add PayPal Payments Account');
  }

  public function getConfigureDescription() {
    return pht(
      'Allows you to accept various payment instruments with a paypal.com '.
      'account.');
  }

  public function getConfigureInstructions() {
    return pht(
      "To configure PayPal, register or log into an existing account on ".
      "[[https://paypal.com | paypal.com]] (for live payments) or ".
      "[[https://sandbox.paypal.com | sandbox.paypal.com]] (for test ".
      "payments). Once logged in:\n\n".
      "  - Navigate to {nav Tools > API Access}.\n".
      "  - Choose **View API Signature**.\n".
      "  - Copy the **API Username**, **API Password** and **Signature** ".
      "    into the fields above.\n\n".
      "You can select whether the provider operates in test mode or ".
      "accepts live payments using the **Mode** dropdown above.\n\n".
      "You can either use `sandbox.paypal.com` to retrieve live credentials, ".
      "or `paypal.com` to retrieve live credentials.");
  }

  public function getAllConfigurableProperties() {
    return array(
      self::PAYPAL_API_USERNAME,
      self::PAYPAL_API_PASSWORD,
      self::PAYPAL_API_SIGNATURE,
      self::PAYPAL_MODE,
    );
  }

  public function getAllConfigurableSecretProperties() {
    return array(
      self::PAYPAL_API_PASSWORD,
      self::PAYPAL_API_SIGNATURE,
    );
  }

  public function processEditForm(
    AphrontRequest $request,
    array $values) {

    $errors = array();
    $issues = array();

    if (!strlen($values[self::PAYPAL_API_USERNAME])) {
      $errors[] = pht('PayPal API Username is required.');
      $issues[self::PAYPAL_API_USERNAME] = pht('Required');
    }

    if (!strlen($values[self::PAYPAL_API_PASSWORD])) {
      $errors[] = pht('PayPal API Password is required.');
      $issues[self::PAYPAL_API_PASSWORD] = pht('Required');
    }

    if (!strlen($values[self::PAYPAL_API_SIGNATURE])) {
      $errors[] = pht('PayPal API Signature is required.');
      $issues[self::PAYPAL_API_SIGNATURE] = pht('Required');
    }

    if (!strlen($values[self::PAYPAL_MODE])) {
      $errors[] = pht('Mode is required.');
      $issues[self::PAYPAL_MODE] = pht('Required');
    }

    return array($errors, $issues, $values);
  }

  public function extendEditForm(
    AphrontRequest $request,
    AphrontFormView $form,
    array $values,
    array $issues) {

    $form
      ->appendChild(
        id(new AphrontFormTextControl())
          ->setName(self::PAYPAL_API_USERNAME)
          ->setValue($values[self::PAYPAL_API_USERNAME])
          ->setError(idx($issues, self::PAYPAL_API_USERNAME, true))
          ->setLabel(pht('Paypal API Username')))
      ->appendChild(
        id(new AphrontFormTextControl())
          ->setName(self::PAYPAL_API_PASSWORD)
          ->setValue($values[self::PAYPAL_API_PASSWORD])
          ->setError(idx($issues, self::PAYPAL_API_PASSWORD, true))
          ->setLabel(pht('Paypal API Password')))
      ->appendChild(
        id(new AphrontFormTextControl())
          ->setName(self::PAYPAL_API_SIGNATURE)
          ->setValue($values[self::PAYPAL_API_SIGNATURE])
          ->setError(idx($issues, self::PAYPAL_API_SIGNATURE, true))
          ->setLabel(pht('Paypal API Signature')))
      ->appendChild(
        id(new AphrontFormSelectControl())
          ->setName(self::PAYPAL_MODE)
          ->setValue($values[self::PAYPAL_MODE])
          ->setError(idx($issues, self::PAYPAL_MODE))
          ->setLabel(pht('Mode'))
          ->setOptions(
            array(
              'test' => pht('Test Mode'),
              'live' => pht('Live Mode'),
            )));

    return;
  }

  public function canRunConfigurationTest() {
    return true;
  }

  public function runConfigurationTest() {
    $result = $this
      ->newPaypalAPICall()
      ->setRawPayPalQuery('GetBalance', array())
      ->resolve();
  }

  public function getPaymentMethodDescription() {
    return pht('Credit Card or PayPal Account');
  }

  public function getPaymentMethodIcon() {
    return 'PayPal';
  }

  public function getPaymentMethodProviderDescription() {
    return 'PayPal';
  }

  protected function executeCharge(
    PhortunePaymentMethod $payment_method,
    PhortuneCharge $charge) {
    throw new Exception('!');
  }

  private function getPaypalAPIUsername() {
    return $this
      ->getProviderConfig()
      ->getMetadataValue(self::PAYPAL_API_USERNAME);
  }

  private function getPaypalAPIPassword() {
    return $this
      ->getProviderConfig()
      ->getMetadataValue(self::PAYPAL_API_PASSWORD);
  }

  private function getPaypalAPISignature() {
    return $this
      ->getProviderConfig()
      ->getMetadataValue(self::PAYPAL_API_SIGNATURE);
  }

/* -(  One-Time Payments  )-------------------------------------------------- */

  public function canProcessOneTimePayments() {
    return true;
  }

/* -(  Controllers  )-------------------------------------------------------- */


  public function canRespondToControllerAction($action) {
    switch ($action) {
      case 'checkout':
      case 'charge':
      case 'cancel':
        return true;
    }
    return parent::canRespondToControllerAction();
  }

  public function processControllerRequest(
    PhortuneProviderActionController $controller,
    AphrontRequest $request) {

    $viewer = $request->getUser();

    $cart = $controller->loadCart($request->getInt('cartID'));
    if (!$cart) {
      return new Aphront404Response();
    }

    $charge = $controller->loadActiveCharge($cart);
    switch ($controller->getAction()) {
      case 'checkout':
        if ($charge) {
          throw new Exception(pht('Cart is already charging!'));
        }
        break;
      case 'charge':
      case 'cancel':
        if (!$charge) {
          throw new Exception(pht('Cart is not charging yet!'));
        }
        break;
    }

    switch ($controller->getAction()) {
      case 'checkout':
        $return_uri = $this->getControllerURI(
          'charge',
          array(
            'cartID' => $cart->getID(),
          ));

        $cancel_uri = $this->getControllerURI(
          'cancel',
          array(
            'cartID' => $cart->getID(),
          ));

        $price = $cart->getTotalPriceAsCurrency();

        $charge = $cart->willApplyCharge($viewer, $this);

        $params = array(
          'PAYMENTREQUEST_0_AMT'            => $price->formatBareValue(),
          'PAYMENTREQUEST_0_CURRENCYCODE'   => $price->getCurrency(),
          'PAYMENTREQUEST_0_PAYMENTACTION'  => 'Sale',
          'PAYMENTREQUEST_0_CUSTOM'         => $charge->getPHID(),

          'RETURNURL'                       => $return_uri,
          'CANCELURL'                       => $cancel_uri,

          // TODO: This should be cart-dependent if we eventually support
          // physical goods.
          'NOSHIPPING'                      => '1',
        );

        $result = $this
          ->newPaypalAPICall()
          ->setRawPayPalQuery('SetExpressCheckout', $params)
          ->resolve();

        $uri = new PhutilURI('https://www.sandbox.paypal.com/cgi-bin/webscr');
        $uri->setQueryParams(
          array(
            'cmd'   => '_express-checkout',
            'token' => $result['TOKEN'],
          ));

        $cart->setMetadataValue('provider.checkoutURI', $uri);
        $cart->save();

        $charge->setMetadataValue('paypal.token', $result['TOKEN']);
        $charge->save();

        return id(new AphrontRedirectResponse())
          ->setIsExternal(true)
          ->setURI($uri);
      case 'charge':
        $token = $request->getStr('token');

        $params = array(
          'TOKEN' => $token,
        );

        $result = $this
          ->newPaypalAPICall()
          ->setRawPayPalQuery('GetExpressCheckoutDetails', $params)
          ->resolve();

        var_dump($result);

        if ($result['CUSTOM'] !== $charge->getPHID()) {
          throw new Exception(
            pht('Paypal checkout does not match Phortune charge!'));
        }

        if ($result['CHECKOUTSTATUS'] !== 'PaymentActionNotInitiated') {
          throw new Exception(
            pht(
              'Expected status "%s", got "%s".',
              'PaymentActionNotInitiated',
              $result['CHECKOUTSTATUS']));
        }

        $price = $cart->getTotalPriceAsCurrency();

        $params = array(
          'TOKEN' => $token,
          'PAYERID' => $result['PAYERID'],

          'PAYMENTREQUEST_0_AMT'            => $price->formatBareValue(),
          'PAYMENTREQUEST_0_CURRENCYCODE'   => $price->getCurrency(),
          'PAYMENTREQUEST_0_PAYMENTACTION'  => 'Sale',
        );

        $result = $this
          ->newPaypalAPICall()
          ->setRawPayPalQuery('DoExpressCheckoutPayment', $params)
          ->resolve();

        // TODO: Paypal can send requests back in "PaymentReview" status,
        // and does this for test transactions. We're supposed to hold
        // the transaction and poll the API every 6 hours. This is unreasonably
        // difficult for now and we can't reasonably just fail these charges.

        var_dump($result);

        die();
        break;
      case 'cancel':
        var_dump($_REQUEST);
        break;
    }

    throw new Exception(
      pht('Unsupported action "%s".', $controller->getAction()));
  }

  private function newPaypalAPICall() {
    $mode = $this->getProviderConfig()->getMetadataValue(self::PAYPAL_MODE);
    if ($mode == 'live') {
      $host = 'https://api-3t.paypal.com/nvp';
    } else {
      $host = 'https://api-3t.sandbox.paypal.com/nvp';
    }

    return id(new PhutilPayPalAPIFuture())
      ->setHost($host)
      ->setAPIUsername($this->getPaypalAPIUsername())
      ->setAPIPassword($this->getPaypalAPIPassword())
      ->setAPISignature($this->getPaypalAPISignature());
  }


}
