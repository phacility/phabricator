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

    $root = dirname(phutil_get_library_root('phabricator'));
    require_once $root.'/externals/wepay/wepay.php';

    WePay::useStaging(
      $this->getWePayClientID(),
      $this->getWePayClientSecret());

    $wepay = new WePay($this->getWePayAccessToken());

    $charge = id(new PhortuneChargeQuery())
      ->setViewer($viewer)
      ->withCartPHIDs(array($cart->getPHID()))
      ->withStatuses(
        array(
          PhortuneCharge::STATUS_CHARGING,
        ))
      ->executeOne();

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

        $cart->willApplyCharge($viewer, $this);

        $result = $wepay->request('checkout/create', $params);

        $cart->setMetadataValue('provider.checkoutURI', $result->checkout_uri);
        $cart->setMetadataValue('wepay.checkoutID', $result->checkout_id);
        $cart->save();

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
          $cart->didApplyCharge($charge);
        unset($unguarded);

        return id(new AphrontRedirectResponse())
          ->setURI($cart->getDoneURI());
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


}
