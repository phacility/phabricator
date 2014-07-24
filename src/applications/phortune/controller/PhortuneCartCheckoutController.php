<?php

final class PhortuneCartCheckoutController
  extends PhortuneCartController {

  private $id;

  public function willProcessRequest(array $data) {
    $this->id = $data['id'];
  }

  public function processRequest() {
    $request = $this->getRequest();
    $viewer = $request->getUser();

    $cart = id(new PhortuneCartQuery())
      ->setViewer($viewer)
      ->withIDs(array($this->id))
      ->needPurchases(true)
      ->executeOne();
    if (!$cart) {
      return new Aphront404Response();
    }

    $account = $cart->getAccount();
    $account_uri = $this->getApplicationURI($account->getID().'/');

    $methods = id(new PhortunePaymentMethodQuery())
      ->setViewer($viewer)
      ->withAccountPHIDs(array($account->getPHID()))
      ->withStatus(PhortunePaymentMethodQuery::STATUS_OPEN)
      ->execute();

    $e_method = null;
    $errors = array();

    if ($request->isFormPost()) {

      // Require CAN_EDIT on the cart to actually make purchases.

      PhabricatorPolicyFilter::requireCapability(
        $viewer,
        $cart,
        PhabricatorPolicyCapability::CAN_EDIT);

      $method_id = $request->getInt('paymentMethodID');
      $method = idx($methods, $method_id);
      if (!$method) {
        $e_method = pht('Required');
        $errors[] = pht('You must choose a payment method.');
      }

      if (!$errors) {
        $provider = $method->buildPaymentProvider();

        $charge = id(new PhortuneCharge())
          ->setAccountPHID($account->getPHID())
          ->setCartPHID($cart->getPHID())
          ->setAuthorPHID($viewer->getPHID())
          ->setPaymentMethodPHID($method->getPHID())
          ->setAmountInCents($cart->getTotalPriceInCents())
          ->setStatus(PhortuneCharge::STATUS_PENDING);

        $charge->openTransaction();
          $charge->save();

          $cart->setStatus(PhortuneCart::STATUS_PURCHASING);
          $cart->save();
        $charge->saveTransaction();

        $provider->applyCharge($method, $charge);

        $cart->setStatus(PhortuneCart::STATUS_PURCHASED);
        $cart->save();

        $view_uri = $this->getApplicationURI('cart/'.$cart->getID().'/');

        return id(new AphrontRedirectResponse())->setURI($view_uri);
      }
    }

    $cart_box = $this->buildCartContents($cart);
    $cart_box->setFormErrors($errors);

    $title = pht('Buy Stuff');

    if (!$methods) {
      $method_control = id(new AphrontFormStaticControl())
        ->setLabel(pht('Payment Method'))
        ->setValue(
          phutil_tag('em', array(), pht('No payment methods configured.')));
    } else {
      $method_control = id(new AphrontFormRadioButtonControl())
        ->setLabel(pht('Payment Method'))
        ->setName('paymentMethodID')
        ->setValue($request->getInt('paymentMethodID'));
      foreach ($methods as $method) {
        $method_control->addButton(
          $method->getID(),
          $method->getBrand().' / '.$method->getLastFourDigits(),
          $method->getDescription());
      }
    }

    $method_control->setError($e_method);

    $payment_method_uri = $this->getApplicationURI(
      $account->getID().'/paymentmethod/edit/');

    $form = id(new AphrontFormView())
      ->setUser($viewer)
      ->appendChild($method_control);

    $add_providers = PhortunePaymentProvider::getProvidersForAddPaymentMethod();
    if ($add_providers) {
      $new_method = phutil_tag(
        'a',
        array(
          'class' => 'button grey',
          'href'  => $payment_method_uri,
          'sigil' => 'workflow',
        ),
        pht('Add New Payment Method'));
      $form->appendChild(
        id(new AphrontFormMarkupControl())
          ->setValue($new_method));
    }

    if ($methods || $add_providers) {
      $form
        ->appendChild(
          id(new AphrontFormSubmitControl())
            ->setValue(pht('Submit Payment'))
            ->setDisabled(!$methods));
    }

    $provider_form = null;

    $pay_providers = PhortunePaymentProvider::getProvidersForOneTimePayment();
    if ($pay_providers) {
      $one_time_options = array();
      foreach ($pay_providers as $provider) {
        $one_time_options[] = $provider->renderOneTimePaymentButton(
          $account,
          $cart,
          $viewer);
      }

      $one_time_options = phutil_tag(
        'div',
        array(
          'class' => 'phortune-payment-onetime-list',
        ),
        $one_time_options);

      $provider_form = new PHUIFormLayoutView();
      $provider_form->appendChild(
        id(new AphrontFormMarkupControl())
          ->setLabel('Pay With')
          ->setValue($one_time_options));
    }

    $payment_box = id(new PHUIObjectBoxView())
      ->setHeaderText(pht('Choose Payment Method'))
      ->appendChild($form)
      ->appendChild($provider_form);

    $crumbs = $this->buildApplicationCrumbs();
    $crumbs->addTextCrumb($title);

    return $this->buildApplicationPage(
      array(
        $crumbs,
        $cart_box,
        $payment_box,
      ),
      array(
        'title'   => $title,
      ));

  }
}
