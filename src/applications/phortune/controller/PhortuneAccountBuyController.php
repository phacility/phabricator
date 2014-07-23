<?php

final class PhortuneAccountBuyController
  extends PhortuneController {

  private $id;

  public function willProcessRequest(array $data) {
    $this->id = $data['id'];
  }

  public function processRequest() {
    $request = $this->getRequest();
    $user = $request->getUser();

    $cart = id(new PhortuneCartQuery())
      ->setViewer($user)
      ->withIDs(array($this->id))
      ->needPurchases(true)
      ->executeOne();
    if (!$cart) {
      return new Aphront404Response();
    }

    $account = $cart->getAccount();
    $account_uri = $this->getApplicationURI($account->getID().'/');

    $rows = array();
    $total = 0;
    foreach ($cart->getPurchases() as $purchase) {
      $rows[] = array(
        pht('A Purchase'),
        PhortuneCurrency::newFromUSDCents($purchase->getBasePriceInCents())
          ->formatForDisplay(),
        $purchase->getQuantity(),
        PhortuneCurrency::newFromUSDCents($purchase->getTotalPriceInCents())
          ->formatForDisplay(),
      );

      $total += $purchase->getTotalPriceInCents();
    }

    $rows[] = array(
      phutil_tag('strong', array(), pht('Total')),
      '',
      '',
      phutil_tag('strong', array(),
        PhortuneCurrency::newFromUSDCents($total)->formatForDisplay()),
    );

    $table = new AphrontTableView($rows);
    $table->setHeaders(
      array(
        pht('Item'),
        pht('Price'),
        pht('Qty.'),
        pht('Total'),
      ));
    $table->setColumnClasses(
      array(
        'wide',
        'right',
        'right',
        'right',
      ));

    $cart_box = id(new PHUIObjectBoxView())
      ->setHeaderText(pht('Your Cart'))
      ->appendChild($table);

    $title = pht('Buy Stuff');


    $methods = id(new PhortunePaymentMethodQuery())
      ->setViewer($user)
      ->withAccountPHIDs(array($account->getPHID()))
      ->withStatus(PhortunePaymentMethodQuery::STATUS_OPEN)
      ->execute();

    $method_control = id(new AphrontFormRadioButtonControl())
      ->setLabel(pht('Payment Method'));

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

    $payment_method_uri = $this->getApplicationURI(
      $account->getID().'/paymentmethod/edit/');

    $form = id(new AphrontFormView())
      ->setUser($user)
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
          $user);
      }

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
