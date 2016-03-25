<?php

final class PhortuneCartCheckoutController
  extends PhortuneCartController {

  public function handleRequest(AphrontRequest $request) {
    $viewer = $request->getViewer();
    $id = $request->getURIData('id');

    $cart = id(new PhortuneCartQuery())
      ->setViewer($viewer)
      ->withIDs(array($id))
      ->needPurchases(true)
      ->executeOne();
    if (!$cart) {
      return new Aphront404Response();
    }

    $cancel_uri = $cart->getCancelURI();
    $merchant = $cart->getMerchant();

    switch ($cart->getStatus()) {
      case PhortuneCart::STATUS_BUILDING:
        return $this->newDialog()
          ->setTitle(pht('Incomplete Cart'))
          ->appendParagraph(
            pht(
              'The application that created this cart did not finish putting '.
              'products in it. You can not checkout with an incomplete '.
              'cart.'))
          ->addCancelButton($cancel_uri);
      case PhortuneCart::STATUS_READY:
        // This is the expected, normal state for a cart that's ready for
        // checkout.
        break;
      case PhortuneCart::STATUS_CHARGED:
      case PhortuneCart::STATUS_PURCHASING:
      case PhortuneCart::STATUS_HOLD:
      case PhortuneCart::STATUS_REVIEW:
      case PhortuneCart::STATUS_PURCHASED:
        // For these states, kick the user to the order page to give them
        // information and options.
        return id(new AphrontRedirectResponse())->setURI($cart->getDetailURI());
      default:
        throw new Exception(
          pht(
            'Unknown cart status "%s"!',
            $cart->getStatus()));
    }

    $account = $cart->getAccount();
    $account_uri = $this->getApplicationURI($account->getID().'/');

    $methods = id(new PhortunePaymentMethodQuery())
      ->setViewer($viewer)
      ->withAccountPHIDs(array($account->getPHID()))
      ->withMerchantPHIDs(array($merchant->getPHID()))
      ->withStatuses(array(PhortunePaymentMethod::STATUS_ACTIVE))
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

        $charge = $cart->willApplyCharge($viewer, $provider, $method);

        try {
          $provider->applyCharge($method, $charge);
        } catch (Exception $ex) {
          $cart->didFailCharge($charge);
          return $this->newDialog()
            ->setTitle(pht('Charge Failed'))
            ->appendParagraph(
              pht(
                'Unable to make payment: %s',
                $ex->getMessage()))
            ->addCancelButton($cart->getCheckoutURI(), pht('Continue'));
        }

        $cart->didApplyCharge($charge);

        $done_uri = $cart->getCheckoutURI();
        return id(new AphrontRedirectResponse())->setURI($done_uri);
      }
    }

    $cart_table = $this->buildCartContentTable($cart);

    $cart_box = id(new PHUIObjectBoxView())
      ->setFormErrors($errors)
      ->setHeaderText(pht('Cart Contents'))
      ->setBackground(PHUIObjectBoxView::BLUE_PROPERTY)
      ->setTable($cart_table);

    $title = $cart->getName();

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
          $method->getFullDisplayName(),
          $method->getDescription());
      }
    }

    $method_control->setError($e_method);

    $account_id = $account->getID();

    $payment_method_uri = $this->getApplicationURI("{$account_id}/card/new/");
    $payment_method_uri = new PhutilURI($payment_method_uri);
    $payment_method_uri->setQueryParams(
      array(
        'merchantID' => $merchant->getID(),
        'cartID' => $cart->getID(),
      ));

    $form = id(new AphrontFormView())
      ->setUser($viewer)
      ->appendChild($method_control);

    $add_providers = $this->loadCreatePaymentMethodProvidersForMerchant(
      $merchant);
    if ($add_providers) {
      $new_method = javelin_tag(
        'a',
        array(
          'class' => 'button grey',
          'href'  => $payment_method_uri,
        ),
        pht('Add New Payment Method'));
      $form->appendChild(
        id(new AphrontFormMarkupControl())
          ->setValue($new_method));
    }

    if ($methods || $add_providers) {
      $submit = id(new AphrontFormSubmitControl())
        ->setValue(pht('Submit Payment'))
        ->setDisabled(!$methods);

      if ($cart->getCancelURI() !== null) {
        $submit->addCancelButton($cart->getCancelURI());
      }

      $form->appendChild($submit);
    }

    $provider_form = null;

    $pay_providers = $this->loadOneTimePaymentProvidersForMerchant($merchant);
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
          ->setLabel(pht('Pay With'))
          ->setValue($one_time_options));
    }

    $payment_box = id(new PHUIObjectBoxView())
      ->setHeaderText(pht('Choose Payment Method'))
      ->setBackground(PHUIObjectBoxView::BLUE_PROPERTY)
      ->appendChild($form)
      ->appendChild($provider_form);

    $description_box = $this->renderCartDescription($cart);

    $crumbs = $this->buildApplicationCrumbs();
    $crumbs->addTextCrumb(pht('Checkout'));
    $crumbs->addTextCrumb($title);
    $crumbs->setBorder(true);

    $header = id(new PHUIHeaderView())
      ->setHeader($title)
      ->setHeaderIcon('fa-shopping-cart');

    $view = id(new PHUITwoColumnView())
      ->setHeader($header)
      ->setFooter(array(
        $cart_box,
        $description_box,
        $payment_box,
      ));

    return $this->newPage()
      ->setTitle($title)
      ->setCrumbs($crumbs)
      ->appendChild($view);

  }
}
