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
    return 'rsrc/phortune/wepay.png';
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

  public function renderOneTimePaymentButton(
    PhortuneAccount $account,
    PhortuneCart $cart,
    PhabricatorUser $user) {

    $uri = $this->getControllerURI(
      'checkout',
      array(
        'cartID' => $cart->getID(),
      ));

    return phabricator_form(
      $user,
      array(
        'action' => $uri,
        'method' => 'POST',
      ),
      phutil_tag(
        'button',
        array(
          'class' => 'green',
          'type'  => 'submit',
        ),
        pht('Pay with WePay')));
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

        $total_in_cents = $cart->getTotalInCents();
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
          'auto_capture'      => false,
          'require_shipping'  => 0,
          'shipping_fee'      => 0,
          'charge_tax'        => 0,
          'mode'              => 'regular',
          'funding_sources'   => 'bank,cc'
        );

        $result = $wepay->request('checkout/create', $params);

        // NOTE: We might want to store "$result->checkout_id" on the Cart.

        $uri = new PhutilURI($result->checkout_uri);
        return id(new AphrontRedirectResponse())->setURI($uri);
      case 'charge':

        // NOTE: We get $_REQUEST['checkout_id'] here, but our parameters are
        // dropped so we should stop depending on them or shove them into the
        // URI.

        var_dump($_REQUEST);
        break;
      case 'cancel':
        var_dump($_REQUEST);
        break;
    }

    throw new Exception("The rest of this isn't implemented yet.");
  }


}
