<?php

final class PhortuneCartAcceptController
  extends PhortuneCartController {

  protected function shouldRequireAccountAuthority() {
    return false;
  }

  protected function shouldRequireMerchantAuthority() {
    return true;
  }

  protected function handleCartRequest(AphrontRequest $request) {
    $viewer = $request->getViewer();
    $cart = $this->getCart();

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
