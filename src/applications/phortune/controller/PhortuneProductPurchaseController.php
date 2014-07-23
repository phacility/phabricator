<?php

final class PhortuneProductPurchaseController
  extends PhortuneController {

  private $accountID;
  private $productID;

  public function willProcessRequest(array $data) {
    $this->accountID = $data['accountID'];
    $this->productID = $data['productID'];
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
      ->withIDs(array($this->productID))
      ->executeOne();
    if (!$product) {
      return new Aphront404Response();
    }

    if ($request->isFormPost()) {
      // TODO: Use ApplicationTransations.

      $cart = new PhortuneCart();
      $cart->openTransaction();

        $cart->setStatus(PhortuneCart::STATUS_READY);
        $cart->setAccountPHID($account->getPHID());
        $cart->setAuthorPHID($user->getPHID());
        $cart->save();

        $purchase = new PhortunePurchase();
        $purchase->setProductPHID($product->getPHID());
        $purchase->setAccountPHID($account->getPHID());
        $purchase->setAuthorPHID($user->getPHID());
        $purchase->setCartPHID($cart->getPHID());
        $purchase->setBasePriceInCents($product->getPriceInCents());
        $purchase->setQuantity(1);
        $purchase->setTotalPriceInCents(
          $purchase->getBasePriceInCents() * $purchase->getQuantity());
        $purchase->setStatus(PhortunePurchase::STATUS_PENDING);
        $purchase->save();

      $cart->saveTransaction();

      $cart_id = $cart->getID();
      $cart_uri = $this->getApplicationURI('/cart/'.$cart_id.'/checkout/');
      return id(new AphrontRedirectResponse())->setURI($cart_uri);
    }

    return $this->newDialog()
      ->setTitle(pht('Purchase Product'))
      ->appendParagraph(pht('Really purchase this stuff?'))
      ->addSubmitButton(pht('Checkout'))
      ->addCancelButton($account_uri);
  }
}
