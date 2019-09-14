<?php

final class PhortuneCartUpdateController
  extends PhortuneCartController {

  protected function shouldRequireAccountAuthority() {
    return false;
  }

  protected function shouldRequireMerchantAuthority() {
    return false;
  }

  protected function handleCartRequest(AphrontRequest $request) {
    $viewer = $request->getViewer();
    $id = $request->getURIData('id');

    $cart = $this->getCart();
    $authority = $this->getMerchantAuthority();

    $charges = id(new PhortuneChargeQuery())
      ->setViewer($viewer)
      ->withCartPHIDs(array($cart->getPHID()))
      ->needCarts(true)
      ->withStatuses(
        array(
          PhortuneCharge::STATUS_HOLD,
          PhortuneCharge::STATUS_CHARGED,
        ))
      ->execute();

    if ($charges) {
      $providers = id(new PhortunePaymentProviderConfigQuery())
        ->setViewer($viewer)
        ->withPHIDs(mpull($charges, 'getProviderPHID'))
        ->execute();
      $providers = mpull($providers, null, 'getPHID');
    } else {
      $providers = array();
    }

    foreach ($charges as $charge) {
      if ($charge->isRefund()) {
        // Don't update refunds.
        continue;
      }

      $provider_config = idx($providers, $charge->getProviderPHID());
      if (!$provider_config) {
        throw new Exception(pht('Unable to load provider for charge!'));
      }

      $provider = $provider_config->buildProvider();
      $provider->updateCharge($charge);
    }

    return id(new AphrontRedirectResponse())
      ->setURI($cart->getDetailURI());
  }

}
