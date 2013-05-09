<?php

final class PhortunePaypalPaymentProvider extends PhortunePaymentProvider {

  public function isEnabled() {
    return $this->getPaypalAPIUsername() &&
           $this->getPaypalAPIPassword() &&
           $this->getPaypalAPISignature();
  }

  public function getProviderType() {
    return 'paypal';
  }

  public function getProviderDomain() {
    return 'paypal.com';
  }

  public function getPaymentMethodDescription() {
    return 'Paypal Account';
  }

  public function getPaymentMethodIcon() {
    return 'rsrc/phortune/paypal.png';
  }

  public function getPaymentMethodProviderDescription() {
    return "Paypal";
  }


  public function canHandlePaymentMethod(PhortunePaymentMethod $method) {
    $type = $method->getMetadataValue('type');
    return ($type == 'paypal');
  }

  protected function executeCharge(
    PhortunePaymentMethod $payment_method,
    PhortuneCharge $charge) {
    throw new Exception("!");
  }

  private function getPaypalAPIUsername() {
    return PhabricatorEnv::getEnvConfig('phortune.paypal.api-username');
  }

  private function getPaypalAPIPassword() {
    return PhabricatorEnv::getEnvConfig('phortune.paypal.api-password');
  }

  private function getPaypalAPISignature() {
    return PhabricatorEnv::getEnvConfig('phortune.paypal.api-signature');
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
        pht('Pay with Paypal')));
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
    PhortuneProviderController $controller,
    AphrontRequest $request) {

    $cart = $controller->loadCart($request->getInt('cartID'));
    if (!$cart) {
      return new Aphront404Response();
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

        $total_in_cents = $cart->getTotalInCents();
        $price = PhortuneCurrency::newFromUSDCents($total_in_cents);

        $result = $this
          ->newPaypalAPICall()
          ->setRawPayPalQuery(
            'SetExpressCheckout',
            array(
              'PAYMENTREQUEST_0_AMT'            => $price->formatBareValue(),
              'PAYMENTREQUEST_0_CURRENCYCODE'   => $price->getCurrency(),
              'RETURNURL'                       => $return_uri,
              'CANCELURL'                       => $cancel_uri,
              'PAYMENTREQUEST_0_PAYMENTACTION'  => 'Sale',
              ))
          ->resolve();

        $uri = new PhutilURI('https://www.sandbox.paypal.com/cgi-bin/webscr');
        $uri->setQueryParams(
          array(
            'cmd'   => '_express-checkout',
            'token' => $result['TOKEN'],
          ));

        return id(new AphrontRedirectResponse())->setURI($uri);
      case 'charge':
        var_dump($_REQUEST);
        break;
      case 'cancel':
        var_dump($_REQUEST);
        break;
    }

    throw new Exception("The rest of this isn't implemented yet.");
  }

  private function newPaypalAPICall() {
    return id(new PhutilPayPalAPIFuture())
      ->setHost('https://api-3t.sandbox.paypal.com/nvp')
      ->setAPIUsername($this->getPaypalAPIUsername())
      ->setAPIPassword($this->getPaypalAPIPassword())
      ->setAPISignature($this->getPaypalAPISignature());
  }


}
