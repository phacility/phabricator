<?php

final class PhortuneCartUpdateController
  extends PhortuneCartController {

  private $id;

  public function willProcessRequest(array $data) {
    $this->id = $data['id'];
  }

  public function processRequest() {
    $request = $this->getRequest();
    $viewer = $request->getUser();

    $authority = $this->loadMerchantAuthority();

    $cart_query = id(new PhortuneCartQuery())
      ->setViewer($viewer)
      ->withIDs(array($this->id))
      ->needPurchases(true);

    if ($authority) {
      $cart_query->withMerchantPHIDs(array($authority->getPHID()));
    }

    $cart = $cart_query->executeOne();
    if (!$cart) {
      return new Aphront404Response();
    }

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
      ->setURI($cart->getDetailURI($authority));
  }

}
