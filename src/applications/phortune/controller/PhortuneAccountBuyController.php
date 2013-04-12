<?php

final class PhortuneAccountBuyController
  extends PhortuneController {

  private $accountID;
  private $id;

  public function willProcessRequest(array $data) {
    $this->accountID = $data['accountID'];
    $this->id = $data['id'];
  }

  public function processRequest() {
    $request = $this->getRequest();
    $user = $request->getUser();

    $account = id(new PhortuneAccountQuery())
      ->setViewer($user)
      ->withIDs(array($this->accountID))
      ->executeOne();
    if (!$account) {
      return new Aphront404Response();
    }

    $account_uri = $this->getApplicationURI($account->getID().'/');

    $product = id(new PhortuneProductQuery())
      ->setViewer($user)
      ->withIDs(array($this->id))
      ->executeOne();
    if (!$product) {
      return new Aphront404Response();
    }

    $title = pht('Buy %s', $product->getProductName());

    $payment_method_uri = $this->getApplicationURI(
      $account->getID().'/paymentmethod/edit/');

    $new_method = phutil_tag(
      'a',
      array(
        'href'  => $payment_method_uri,
        'sigil' => 'workflow',
      ),
      pht('Add New Payment Method'));


    $form = id(new AphrontFormView())
      ->setUser($user)
      ->appendChild(
        id(new AphrontFormStaticControl())
          ->setLabel(pht('Stuff'))
          ->setValue($product->getProductName()))
      ->appendChild(
        id(new AphrontFormRadioButtonControl())
          ->setLabel(pht('Payment Method')))
      ->appendChild(
        id(new AphrontFormMarkupControl())
          ->setValue($new_method))
      ->appendChild(
        id(new AphrontFormSubmitControl())
          ->setValue(pht("Dolla Dolla Bill Y'all")));

    return $this->buildApplicationPage(
      $form,
      array(
        'title'   => $title,
        'device'  => true,
        'dust'    => true,
      ));

  }
}
