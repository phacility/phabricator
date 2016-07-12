<?php

final class PhortuneWePayPaymentProvider extends PhortunePaymentProvider {

  const WEPAY_CLIENT_ID       = 'wepay.client-id';
  const WEPAY_CLIENT_SECRET   = 'wepay.client-secret';
  const WEPAY_ACCESS_TOKEN    = 'wepay.access-token';
  const WEPAY_ACCOUNT_ID      = 'wepay.account-id';

  public function isAcceptingLivePayments() {
    return preg_match('/^PRODUCTION_/', $this->getWePayAccessToken());
  }

  public function getName() {
    return pht('WePay');
  }

  public function getConfigureName() {
    return pht('Add WePay Payments Account');
  }

  public function getConfigureDescription() {
    return pht(
      'Allows you to accept credit or debit card payments with a '.
      'wepay.com account.');
  }

  public function getConfigureProvidesDescription() {
    return pht('This merchant accepts credit and debit cards via WePay.');
  }

  public function getConfigureInstructions() {
    return pht(
      "To configure WePay, register or log in to an existing account on ".
      "[[https://wepay.com | wepay.com]] (for live payments) or ".
      "[[https://stage.wepay.com | stage.wepay.com]] (for testing). ".
      "Once logged in:\n\n".
      "  - Create an API application if you don't already have one.\n".
      "  - Click the API application name to go to the detail page.\n".
      "  - Copy **Client ID**, **Client Secret**, **Access Token** and ".
      "    **AccountID** from that page to the fields above.\n\n".
      "You can either use `stage.wepay.com` to retrieve test credentials, ".
      "or `wepay.com` to retrieve live credentials for accepting live ".
      "payments.");
  }

  public function canRunConfigurationTest() {
    return true;
  }

  public function runConfigurationTest() {
    $this->loadWePayAPILibraries();

    WePay::useStaging(
      $this->getWePayClientID(),
      $this->getWePayClientSecret());

    $wepay = new WePay($this->getWePayAccessToken());
    $params = array(
      'client_id' => $this->getWePayClientID(),
      'client_secret' => $this->getWePayClientSecret(),
    );

    $wepay->request('app', $params);
  }

  public function getAllConfigurableProperties() {
    return array(
      self::WEPAY_CLIENT_ID,
      self::WEPAY_CLIENT_SECRET,
      self::WEPAY_ACCESS_TOKEN,
      self::WEPAY_ACCOUNT_ID,
    );
  }

  public function getAllConfigurableSecretProperties() {
    return array(
      self::WEPAY_CLIENT_SECRET,
    );
  }

  public function processEditForm(
    AphrontRequest $request,
    array $values) {

    $errors = array();
    $issues = array();

    if (!strlen($values[self::WEPAY_CLIENT_ID])) {
      $errors[] = pht('WePay Client ID is required.');
      $issues[self::WEPAY_CLIENT_ID] = pht('Required');
    }

    if (!strlen($values[self::WEPAY_CLIENT_SECRET])) {
      $errors[] = pht('WePay Client Secret is required.');
      $issues[self::WEPAY_CLIENT_SECRET] = pht('Required');
    }

    if (!strlen($values[self::WEPAY_ACCESS_TOKEN])) {
      $errors[] = pht('WePay Access Token is required.');
      $issues[self::WEPAY_ACCESS_TOKEN] = pht('Required');
    }

    if (!strlen($values[self::WEPAY_ACCOUNT_ID])) {
      $errors[] = pht('WePay Account ID is required.');
      $issues[self::WEPAY_ACCOUNT_ID] = pht('Required');
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
          ->setName(self::WEPAY_CLIENT_ID)
          ->setValue($values[self::WEPAY_CLIENT_ID])
          ->setError(idx($issues, self::WEPAY_CLIENT_ID, true))
          ->setLabel(pht('WePay Client ID')))
      ->appendChild(
        id(new AphrontFormTextControl())
          ->setName(self::WEPAY_CLIENT_SECRET)
          ->setValue($values[self::WEPAY_CLIENT_SECRET])
          ->setError(idx($issues, self::WEPAY_CLIENT_SECRET, true))
          ->setLabel(pht('WePay Client Secret')))
      ->appendChild(
        id(new AphrontFormTextControl())
          ->setName(self::WEPAY_ACCESS_TOKEN)
          ->setValue($values[self::WEPAY_ACCESS_TOKEN])
          ->setError(idx($issues, self::WEPAY_ACCESS_TOKEN, true))
          ->setLabel(pht('WePay Access Token')))
      ->appendChild(
        id(new AphrontFormTextControl())
          ->setName(self::WEPAY_ACCOUNT_ID)
          ->setValue($values[self::WEPAY_ACCOUNT_ID])
          ->setError(idx($issues, self::WEPAY_ACCOUNT_ID, true))
          ->setLabel(pht('WePay Account ID')));

  }

  public function getPaymentMethodDescription() {
    return pht('Credit or Debit Card');
  }

  public function getPaymentMethodIcon() {
    return 'WePay';
  }

  public function getPaymentMethodProviderDescription() {
    return 'WePay';
  }

  protected function executeCharge(
    PhortunePaymentMethod $payment_method,
    PhortuneCharge $charge) {
    throw new Exception('!');
  }

  private function getWePayClientID() {
    return $this
      ->getProviderConfig()
      ->getMetadataValue(self::WEPAY_CLIENT_ID);
  }

  private function getWePayClientSecret() {
    return $this
      ->getProviderConfig()
      ->getMetadataValue(self::WEPAY_CLIENT_SECRET);
  }

  private function getWePayAccessToken() {
    return $this
      ->getProviderConfig()
      ->getMetadataValue(self::WEPAY_ACCESS_TOKEN);
  }

  private function getWePayAccountID() {
    return $this
      ->getProviderConfig()
      ->getMetadataValue(self::WEPAY_ACCOUNT_ID);
  }

  protected function executeRefund(
    PhortuneCharge $charge,
    PhortuneCharge $refund) {
    $wepay = $this->loadWePayAPILibraries();

    $checkout_id = $this->getWePayCheckoutID($charge);

    $params = array(
      'checkout_id' => $checkout_id,
      'refund_reason' => pht('Refund'),
      'amount' => $refund->getAmountAsCurrency()->negate()->formatBareValue(),
    );

    $wepay->request('checkout/refund', $params);
  }

  public function updateCharge(PhortuneCharge $charge) {
    $wepay = $this->loadWePayAPILibraries();

    $params = array(
      'checkout_id' => $this->getWePayCheckoutID($charge),
    );
    $wepay_checkout = $wepay->request('checkout', $params);

    // TODO: Deal with disputes / chargebacks / surprising refunds.
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

  /**
   * @phutil-external-symbol class WePay
   */
  public function processControllerRequest(
    PhortuneProviderActionController $controller,
    AphrontRequest $request) {
    $wepay = $this->loadWePayAPILibraries();

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

        $params = array(
          'account_id'        => $this->getWePayAccountID(),
          'short_description' => $cart->getName(),
          'type'              => 'SERVICE',
          'amount'            => $price->formatBareValue(),
          'long_description'  => $cart->getName(),
          'reference_id'      => $cart->getPHID(),
          'app_fee'           => 0,
          'fee_payer'         => 'Payee',
          'redirect_uri'      => $return_uri,
          'fallback_uri'      => $cancel_uri,

          // NOTE: If we don't `auto_capture`, we might get a result back in
          // either an "authorized" or a "reserved" state. We can't capture
          // an "authorized" result, so just autocapture.

          'auto_capture'      => true,
          'require_shipping'  => 0,
          'shipping_fee'      => 0,
          'charge_tax'        => 0,
          'mode'              => 'regular',

          // TODO: We could accept bank accounts but the hold/capture rules
          // are not quite clear. Just accept credit cards for now.
          'funding_sources'   => 'cc',
        );

        $charge = $cart->willApplyCharge($viewer, $this);
        $result = $wepay->request('checkout/create', $params);

        $cart->setMetadataValue('provider.checkoutURI', $result->checkout_uri);
        $cart->save();

        $charge->setMetadataValue('wepay.checkoutID', $result->checkout_id);
        $charge->save();

        $uri = new PhutilURI($result->checkout_uri);
        return id(new AphrontRedirectResponse())
          ->setIsExternal(true)
          ->setURI($uri);
      case 'charge':
        if ($cart->getStatus() !== PhortuneCart::STATUS_PURCHASING) {
          return id(new AphrontRedirectResponse())
            ->setURI($cart->getCheckoutURI());
        }

        $checkout_id = $request->getInt('checkout_id');
        $params = array(
          'checkout_id' => $checkout_id,
        );

        $checkout = $wepay->request('checkout', $params);
        if ($checkout->reference_id != $cart->getPHID()) {
          throw new Exception(
            pht('Checkout reference ID does not match cart PHID!'));
        }

        $unguarded = AphrontWriteGuard::beginScopedUnguardedWrites();
          switch ($checkout->state) {
            case 'authorized':
            case 'reserved':
            case 'captured':
              // TODO: Are these all really "done" states, and not "hold"
              // states? Cards and bank accounts both come back as "authorized"
              // on the staging environment. Figure out what happens in
              // production?

              $cart->didApplyCharge($charge);

              $response = id(new AphrontRedirectResponse())->setURI(
                 $cart->getCheckoutURI());
              break;
            default:
              // It's not clear if we can ever get here on the web workflow,
              // WePay doesn't seem to return back to us after a failure (the
              // workflow dead-ends instead).

              $cart->didFailCharge($charge);

              $response = $controller
                ->newDialog()
                ->setTitle(pht('Charge Failed'))
                ->appendParagraph(
                  pht(
                    'Unable to make payment (checkout state is "%s").',
                    $checkout->state))
                ->addCancelButton($cart->getCheckoutURI(), pht('Continue'));
              break;
          }
        unset($unguarded);

        return $response;
      case 'cancel':
        // TODO: I don't know how it's possible to cancel out of a WePay
        // charge workflow.
        throw new Exception(
          pht('How did you get here? WePay has no cancel flow in its UI...?'));
        break;
    }

    throw new Exception(
      pht('Unsupported action "%s".', $controller->getAction()));
  }

  private function loadWePayAPILibraries() {
    $root = dirname(phutil_get_library_root('phabricator'));
    require_once $root.'/externals/wepay/wepay.php';

    WePay::useStaging(
      $this->getWePayClientID(),
      $this->getWePayClientSecret());

    return new WePay($this->getWePayAccessToken());
  }

  private function getWePayCheckoutID(PhortuneCharge $charge) {
    $checkout_id = $charge->getMetadataValue('wepay.checkoutID');
    if ($checkout_id === null) {
      throw new Exception(pht('No WePay Checkout ID present on charge!'));
    }
    return $checkout_id;
  }

}
