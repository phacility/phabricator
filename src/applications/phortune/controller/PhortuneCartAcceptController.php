<?php

final class PhortuneCartAcceptController
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

    // You must control the merchant to accept orders.
    PhabricatorPolicyFilter::requireCapability(
      $viewer,
      $cart->getMerchant(),
      PhabricatorPolicyCapability::CAN_EDIT);

    $cancel_uri = $cart->getDetailURI();

    if ($cart->getStatus() !== PhortuneCart::STATUS_REVIEW) {
      return $this->newDialog()
        ->setTitle(pht('Order Not in Review'))
        ->appendParagraph(
          pht(
            'This order does not need manual review, so you can not '.
            'accept it.'))
        ->addCancelButton($cancel_uri);
    }

    if ($request->isFormPost()) {
      $cart->didReviewCart();
      return id(new AphrontRedirectResponse())->setURI($cancel_uri);
    }

    return $this->newDialog()
      ->setTitle(pht('Accept Order?'))
      ->appendParagraph(
        pht(
          'This order has been flagged for manual review. You should review '.
          'it carefully before accepting it.'))
      ->addCancelButton($cancel_uri)
      ->addSubmitButton(pht('Accept Order'));
  }
}
