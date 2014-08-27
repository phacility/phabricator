<?php

final class PhortuneWePayPaymentProvider extends PhortunePaymentProvider {

  public function isEnabled() {
    return $this->getWePayClientID() &&
           $this->getWePayClientSecret() &&
           $this->getWePayAccessToken() &&
           $this->getWePayAccountID();
  }

  public function getProviderType() {
    return 'wepay';
  }

  public function getProviderDomain() {
    return 'wepay.com';
  }

  public function getPaymentMethodDescription() {
    return pht('Credit Card or Bank Account');
  }

  public function getPaymentMethodIcon() {
    return celerity_get_resource_uri('/rsrc/image/phortune/wepay.png');
  }

  public function getPaymentMethodProviderDescription() {
    return 'WePay';
  }

  public function canHandlePaymentMethod(PhortunePaymentMethod $method) {
    $type = $method->getMetadataValue('type');
    return ($type == 'wepay');
  }

  protected function executeCharge(
    PhortunePaymentMethod $payment_method,
    PhortuneCharge $charge) {
    throw new Exception('!');
  }

  private function getWePayClientID() {
    return PhabricatorEnv::getEnvConfig('phortune.wepay.client-id');
  }

  private function getWePayClientSecret() {
    return PhabricatorEnv::getEnvConfig('phortune.wepay.client-secret');
  }

  private function getWePayAccessToken() {
    return PhabricatorEnv::getEnvConfig('phortune.wepay.access-token');
  }

  private function getWePayAccountID() {
    return PhabricatorEnv::getEnvConfig('phortune.wepay.account-id');
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
    PhortuneProviderController $controller,
    AphrontRequest $request) {

    $viewer = $request->getUser();

    $cart = $controller->loadCart($request->getInt('cartID'));
    if (!$cart) {
      return new Aphront404Response();
    }

    $cart_uri = '/phortune/cart/'.$cart->getID().'/';

    $root = dirname(phutil_get_library_root('phabricator'));
    require_once $root.'/externals/wepay/wepay.php';

    WePay::useStaging(
      $this->getWePayClientID(),
      $this->getWePayClientSecret());

    $wepay = new WePay($this->getWePayAccessToken());

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

        $total_in_cents = $cart->getTotalPriceInCents();
        $price = PhortuneCurrency::newFromUSDCents($total_in_cents);

        $params = array(
          'account_id'        => $this->getWePayAccountID(),
          'short_description' => 'Services', // TODO
          'type'              => 'SERVICE',
          'amount'            => $price->formatBareValue(),
          'long_description'  => 'Services', // TODO
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
          'funding_sources'   => 'bank,cc'
        );

        $result = $wepay->request('checkout/create', $params);

        // TODO: We must store "$result->checkout_id" on the Cart since the
        // user might not end up back here. Really this needs a bunch of junk.

        $uri = new PhutilURI($result->checkout_uri);
        return id(new AphrontRedirectResponse())
          ->setIsExternal(true)
          ->setURI($uri);
      case 'charge':
        $checkout_id = $request->getInt('checkout_id');
        $params = array(
          'checkout_id' => $checkout_id,
        );

        $checkout = $wepay->request('checkout', $params);
        if ($checkout->reference_id != $cart->getPHID()) {
          throw new Exception(
            pht('Checkout reference ID does not match cart PHID!'));
        }

        switch ($checkout->state) {
          case 'authorized':
          case 'reserved':
          case 'captured':
            break;
          default:
            throw new Exception(
              pht(
                'Checkout is in bad state "%s"!',
                $result->state));
        }

        $unguarded = AphrontWriteGuard::beginScopedUnguardedWrites();

          $charge = id(new PhortuneCharge())
            ->setAmountInCents((int)$checkout->gross * 100)
            ->setAccountPHID($cart->getAccount()->getPHID())
            ->setAuthorPHID($viewer->getPHID())
            ->setPaymentProviderKey($this->getProviderKey())
            ->setCartPHID($cart->getPHID())
            ->setStatus(PhortuneCharge::STATUS_CHARGING)
            ->save();

          $cart->openTransaction();
            $charge->setStatus(PhortuneCharge::STATUS_CHARGED);
            $charge->save();

            $cart->setStatus(PhortuneCart::STATUS_PURCHASED);
            $cart->save();
          $cart->saveTransaction();

        unset($unguarded);

        return id(new AphrontRedirectResponse())
          ->setIsExternal(true)
          ->setURI($cart_uri);
      case 'cancel':
        var_dump($_REQUEST);
        break;
    }

    throw new Exception("The rest of this isn't implemented yet.");
  }


}
