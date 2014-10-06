<?php

final class PhortunePaypalPaymentProvider extends PhortunePaymentProvider {

  public function isEnabled() {
    // TODO: See note in processControllerRequest().
    return false;

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
    return pht('Credit Card or Paypal Account');
  }

  public function getPaymentMethodIcon() {
    return celerity_get_resource_uri('rsrc/image/phortune/paypal.png');
  }

  public function getPaymentMethodProviderDescription() {
    return 'Paypal';
  }

  public function canHandlePaymentMethod(PhortunePaymentMethod $method) {
    $type = $method->getMetadataValue('type');
    return ($type == 'paypal');
  }

  protected function executeCharge(
    PhortunePaymentMethod $payment_method,
    PhortuneCharge $charge) {
    throw new Exception('!');
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
    return id(new PhutilPayPalAPIFuture())
      ->setHost('https://api-3t.sandbox.paypal.com/nvp')
      ->setAPIUsername($this->getPaypalAPIUsername())
      ->setAPIPassword($this->getPaypalAPIPassword())
      ->setAPISignature($this->getPaypalAPISignature());
  }


}
