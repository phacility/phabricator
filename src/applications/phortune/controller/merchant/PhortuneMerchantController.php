<?php

abstract class PhortuneMerchantController
  extends PhortuneController {

  private $merchant;

  final protected function setMerchant(PhortuneMerchant $merchant) {
    $this->merchant = $merchant;
    return $this;
  }

  final protected function getMerchant() {
    return $this->merchant;
  }

  final protected function hasMerchant() {
    return (bool)$this->merchant;
  }

  final public function handleRequest(AphrontRequest $request) {
    if ($this->shouldRequireMerchantEditCapability()) {
      $response = $this->loadMerchantForEdit();
    } else {
      $response = $this->loadMerchantForView();
    }

    if ($response) {
      return $response;
    }

    return $this->handleMerchantRequest($request);
  }

  abstract protected function shouldRequireMerchantEditCapability();
  abstract protected function handleMerchantRequest(AphrontRequest $request);

  private function loadMerchantForEdit() {
    return $this->loadMerchantWithCapabilities(
      array(
        PhabricatorPolicyCapability::CAN_VIEW,
        PhabricatorPolicyCapability::CAN_EDIT,
      ));
  }

  private function loadMerchantForView() {
    return $this->loadMerchantWithCapabilities(
      array(
        PhabricatorPolicyCapability::CAN_VIEW,
      ));
  }

  private function loadMerchantWithCapabilities(array $capabilities) {
    $viewer = $this->getViewer();
    $request = $this->getRequest();

    $merchant_id = $request->getURIData('merchantID');
    if (!$merchant_id) {
      throw new Exception(
        pht(
          'Controller ("%s") extends controller "%s", but is reachable '.
          'with no "merchantID" in URI.',
          get_class($this),
          __CLASS__));
    }

    $merchant = id(new PhortuneMerchantQuery())
      ->setViewer($viewer)
      ->withIDs(array($merchant_id))
      ->needProfileImage(true)
      ->requireCapabilities($capabilities)
      ->executeOne();
    if (!$merchant) {
      return new Aphront404Response();
    }

    $this->setMerchant($merchant);

    return null;
  }

}
