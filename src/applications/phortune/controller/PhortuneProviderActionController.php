<?php

final class PhortuneProviderActionController
  extends PhortuneController {

  private $action;

  public function setAction($action) {
    $this->action = $action;
    return $this;
  }

  public function getAction() {
    return $this->action;
  }

  public function handleRequest(AphrontRequest $request) {
    $viewer = $request->getViewer();
    $id = $request->getURIData('id');
    $this->setAction($request->getURIData('action'));

    $provider_config = id(new PhortunePaymentProviderConfigQuery())
      ->setViewer($viewer)
      ->withIDs(array($id))
      ->executeOne();
    if (!$provider_config) {
      return new Aphront404Response();
    }

    $provider = $provider_config->buildProvider();

    if (!$provider->canRespondToControllerAction($this->getAction())) {
      return new Aphront404Response();
    }

    $response = $provider->processControllerRequest($this, $request);

    if ($response instanceof AphrontResponse) {
      return $response;
    }

    return $this->buildApplicationPage(
      $response,
      array(
        'title' => pht('Phortune'),
      ));
  }


  public function loadCart($id) {
    $request = $this->getRequest();
    $viewer = $request->getUser();

    return id(new PhortuneCartQuery())
      ->setViewer($viewer)
      ->needPurchases(true)
      ->withIDs(array($id))
      ->requireCapabilities(
        array(
          PhabricatorPolicyCapability::CAN_VIEW,
          PhabricatorPolicyCapability::CAN_EDIT,
        ))
      ->executeOne();
  }

  public function loadActiveCharge(PhortuneCart $cart) {
    $request = $this->getRequest();
    $viewer = $request->getUser();

    return id(new PhortuneChargeQuery())
      ->setViewer($viewer)
      ->withCartPHIDs(array($cart->getPHID()))
      ->withStatuses(
        array(
          PhortuneCharge::STATUS_CHARGING,
        ))
      ->executeOne();
  }

}
