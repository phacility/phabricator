<?php

final class PhortuneProviderActionController
  extends PhortuneController {

  private $id;
  private $action;

  public function willProcessRequest(array $data) {
    $this->id = $data['id'];
    $this->setAction($data['action']);
  }

  public function setAction($action) {
    $this->action = $action;
    return $this;
  }

  public function getAction() {
    return $this->action;
  }

  public function processRequest() {
    $request = $this->getRequest();
    $viewer = $request->getUser();

    $provider_config = id(new PhortunePaymentProviderConfigQuery())
      ->setViewer($viewer)
      ->withIDs(array($this->id))
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
