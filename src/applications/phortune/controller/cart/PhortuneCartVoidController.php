<?php

final class PhortuneCartVoidController
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

    try {
      $title = pht('Unable to Void Invoice');
      $cart->assertCanVoidOrder();
    } catch (Exception $ex) {
      return $this->newDialog()
        ->setTitle($title)
        ->appendChild($ex->getMessage())
        ->addCancelButton($cancel_uri);
    }

    if ($request->isFormPost()) {
      return id(new AphrontRedirectResponse())->setURI($cancel_uri);
    }

    return $this->newDialog()
      ->setTitle(pht('Void Invoice?'))
      ->appendParagraph(
        pht(
          'Really void this invoice? The customer will no longer be asked '.
          'to submit payment for it.'))
      ->addCancelButton($cancel_uri)
      ->addSubmitButton(pht('Void Invoice'));
  }
}
