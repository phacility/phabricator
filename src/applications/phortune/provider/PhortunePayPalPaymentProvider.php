<?php

final class PhortunePayPalPaymentProvider extends PhortunePaymentProvider {

  const PAYPAL_API_USERNAME   = 'paypal.api-username';
  const PAYPAL_API_PASSWORD   = 'paypal.api-password';
  const PAYPAL_API_SIGNATURE  = 'paypal.api-signature';
  const PAYPAL_MODE           = 'paypal.mode';

  public function isAcceptingLivePayments() {
    $mode = $this->getProviderConfig()->getMetadataValue(self::PAYPAL_MODE);
    return ($mode === 'live');
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

  public function getConfigureProvidesDescription() {
    return pht('This merchant accepts payments via PayPal.');
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

  protected function executeRefund(
    PhortuneCharge $charge,
    PhortuneCharge $refund) {

    $transaction_id = $charge->getMetadataValue('paypal.transactionID');
    if (!$transaction_id) {
      throw new Exception(pht('Charge has no transaction ID!'));
    }

    $refund_amount = $refund->getAmountAsCurrency()->negate();
    $refund_currency = $refund_amount->getCurrency();
    $refund_value = $refund_amount->formatBareValue();

    $params = array(
      'TRANSACTIONID' => $transaction_id,
      'REFUNDTYPE' => 'Partial',
      'AMT' => $refund_value,
      'CURRENCYCODE' => $refund_currency,
    );

    $result = $this
      ->newPaypalAPICall()
      ->setRawPayPalQuery('RefundTransaction', $params)
      ->resolve();

    $charge->setMetadataValue(
      'paypal.refundID',
      $result['REFUNDTRANSACTIONID']);
  }

  public function updateCharge(PhortuneCharge $charge) {
    $transaction_id = $charge->getMetadataValue('paypal.transactionID');
    if (!$transaction_id) {
      throw new Exception(pht('Charge has no transaction ID!'));
    }

    $params = array(
      'TRANSACTIONID' => $transaction_id,
    );

    $result = $this
      ->newPaypalAPICall()
      ->setRawPayPalQuery('GetTransactionDetails', $params)
      ->resolve();

    $is_charge = false;
    $is_fail = false;
    switch ($result['PAYMENTSTATUS']) {
      case 'Processed':
      case 'Completed':
      case 'Completed-Funds-Held':
        $is_charge = true;
        break;
      case 'Partially-Refunded':
      case 'Refunded':
      case 'Reversed':
      case 'Canceled-Reversal':
        // TODO: Handle these.
        return;
      case 'In-Progress':
      case 'Pending':
        // TODO: Also handle these better?
        return;
      case 'Denied':
      case 'Expired':
      case 'Failed':
      case 'None':
      case 'Voided':
      default:
        $is_fail = true;
        break;
    }

    if ($charge->getStatus() == PhortuneCharge::STATUS_HOLD) {
      $cart = $charge->getCart();

      $unguarded = AphrontWriteGuard::beginScopedUnguardedWrites();
        if ($is_charge) {
          $cart->didApplyCharge($charge);
        } else if ($is_fail) {
          $cart->didFailCharge($charge);
        }
      unset($unguarded);
    }
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
          'PAYMENTREQUEST_0_DESC'           => $cart->getName(),

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

        $cart->setMetadataValue('provider.checkoutURI', (string)$uri);
        $cart->save();

        $charge->setMetadataValue('paypal.token', $result['TOKEN']);
        $charge->save();

        return id(new AphrontRedirectResponse())
          ->setIsExternal(true)
          ->setURI($uri);
      case 'charge':
        if ($cart->getStatus() !== PhortuneCart::STATUS_PURCHASING) {
          return id(new AphrontRedirectResponse())
            ->setURI($cart->getCheckoutURI());
        }

        $token = $request->getStr('token');

        $params = array(
          'TOKEN' => $token,
        );

        $result = $this
          ->newPaypalAPICall()
          ->setRawPayPalQuery('GetExpressCheckoutDetails', $params)
          ->resolve();

        if ($result['CUSTOM'] !== $charge->getPHID()) {
          throw new Exception(
            pht('Paypal checkout does not match Phortune charge!'));
        }

        if ($result['CHECKOUTSTATUS'] !== 'PaymentActionNotInitiated') {
          return $controller->newDialog()
            ->setTitle(pht('Payment Already Processed'))
            ->appendParagraph(
              pht(
                'The payment response for this charge attempt has already '.
                'been processed.'))
            ->addCancelButton($cart->getCheckoutURI(), pht('Continue'));
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

        $transaction_id = $result['PAYMENTINFO_0_TRANSACTIONID'];

        $success = false;
        $hold = false;
        switch ($result['PAYMENTINFO_0_PAYMENTSTATUS']) {
          case 'Processed':
          case 'Completed':
          case 'Completed-Funds-Held':
            $success = true;
            break;
          case 'In-Progress':
          case 'Pending':
            // TODO: We can capture more information about this stuff.
            $hold = true;
            break;
          case 'Denied':
          case 'Expired':
          case 'Failed':
          case 'Partially-Refunded':
          case 'Canceled-Reversal':
          case 'None':
          case 'Refunded':
          case 'Reversed':
          case 'Voided':
          default:
            // These are all failure states.
            break;
        }

        $unguarded = AphrontWriteGuard::beginScopedUnguardedWrites();

          $charge->setMetadataValue('paypal.transactionID', $transaction_id);
          $charge->save();

          if ($success) {
            $cart->didApplyCharge($charge);
            $response = id(new AphrontRedirectResponse())->setURI(
              $cart->getCheckoutURI());
          } else if ($hold) {
            $cart->didHoldCharge($charge);

            $response = $controller
              ->newDialog()
              ->setTitle(pht('Charge On Hold'))
              ->appendParagraph(
                pht('Your charge is on hold, for reasons?'))
              ->addCancelButton($cart->getCheckoutURI(), pht('Continue'));
          } else {
            $cart->didFailCharge($charge);

            $response = $controller
              ->newDialog()
              ->setTitle(pht('Charge Failed'))
              ->addCancelButton($cart->getCheckoutURI(), pht('Continue'));
          }
        unset($unguarded);

        return $response;
      case 'cancel':
        if ($cart->getStatus() === PhortuneCart::STATUS_PURCHASING) {
          $unguarded = AphrontWriteGuard::beginScopedUnguardedWrites();
            // TODO: Since the user cancelled this, we could conceivably just
            // throw it away or make it more clear that it's a user cancel.
            $cart->didFailCharge($charge);
          unset($unguarded);
        }

        return id(new AphrontRedirectResponse())
          ->setURI($cart->getCheckoutURI());
    }

    throw new Exception(
      pht('Unsupported action "%s".', $controller->getAction()));
  }

  private function newPaypalAPICall() {
    if ($this->isAcceptingLivePayments()) {
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
