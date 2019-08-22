<?php

abstract class PhortuneAccountController
  extends PhortuneController {

  private $account;
  private $merchants;

  final public function handleRequest(AphrontRequest $request) {
    if ($this->shouldRequireAccountEditCapability()) {
      $response = $this->loadAccountForEdit();
    } else {
      $response = $this->loadAccountForView();
    }

    if ($response) {
      return $response;
    }

    return $this->handleAccountRequest($request);
  }

  abstract protected function shouldRequireAccountEditCapability();
  abstract protected function handleAccountRequest(AphrontRequest $request);

  final protected function hasAccount() {
    return (bool)$this->account;
  }

  final protected function getAccount() {
    if ($this->account === null) {
      throw new Exception(
        pht(
          'Unable to "getAccount()" before loading or setting account '.
          'context.'));
    }

    return $this->account;
  }

  protected function buildApplicationCrumbs() {
    $crumbs = parent::buildApplicationCrumbs();

    // If we hit a policy exception, we can make it here without finding
    // an account.
    if ($this->hasAccount()) {
      $account = $this->getAccount();
      $crumbs->addTextCrumb($account->getName(), $account->getURI());
    }

    return $crumbs;
  }

  private function loadAccountForEdit() {
    return $this->loadAccountWithCapabilities(
      array(
        PhabricatorPolicyCapability::CAN_VIEW,
        PhabricatorPolicyCapability::CAN_EDIT,
      ));
  }

  private function loadAccountForView() {
    return $this->loadAccountWithCapabilities(
      array(
        PhabricatorPolicyCapability::CAN_VIEW,
      ));
  }

  private function loadAccountWithCapabilities(array $capabilities) {
    $viewer = $this->getViewer();
    $request = $this->getRequest();

    $account_id = $request->getURIData('accountID');
    if (!$account_id) {
      throw new Exception(
        pht(
          'Controller ("%s") extends controller "%s", but is reachable '.
          'with no "accountID" in URI.',
          get_class($this),
          __CLASS__));
    }

    $account = id(new PhortuneAccountQuery())
      ->setViewer($viewer)
      ->withIDs(array($account_id))
      ->requireCapabilities($capabilities)
      ->executeOne();
    if (!$account) {
      return new Aphront404Response();
    }

    $this->setAccount($account);

    return null;
  }

  private function setAccount(PhortuneAccount $account) {
    $this->account = $account;

    $viewer = $this->getViewer();
    if (!$account->isUserAccountMember($viewer)) {
      $merchant_phids = $account->getMerchantPHIDs();
      $merchants = id(new PhortuneMerchantQuery())
        ->setViewer($viewer)
        ->withPHIDs($merchant_phids)
        ->withMemberPHIDs(array($viewer->getPHID()))
        ->requireCapabilities(
          array(
            PhabricatorPolicyCapability::CAN_VIEW,
            PhabricatorPolicyCapability::CAN_EDIT,
          ))
        ->execute();

      $this->merchants = $merchants;
    } else {
      $this->merchants = array();
    }

    return $this;
  }

  final protected function getMerchants() {
    if ($this->merchants === null) {
      throw new Exception(
        pht(
          'Unable to "getMerchants()" before loading or setting account '.
          'context.'));
    }

    return $this->merchants;
  }

  final protected function newAccountAuthorityView() {
    $viewer = $this->getViewer();

    $merchants = $this->getMerchants();
    if (!$merchants) {
      return null;
    }

    $merchant_phids = mpull($merchants, 'getPHID');
    $merchant_handles = $viewer->loadHandles($merchant_phids);
    $merchant_handles = iterator_to_array($merchant_handles);

    $merchant_list = mpull($merchant_handles, 'renderLink');
    $merchant_list = phutil_implode_html(', ', $merchant_list);

    $merchant_message = pht(
      'You can view this account because you control %d merchant(s) it '.
      'has a relationship with: %s.',
      phutil_count($merchants),
      $merchant_list);

    return id(new PHUIInfoView())
      ->setSeverity(PHUIInfoView::SEVERITY_NOTICE)
      ->setErrors(
        array(
          $merchant_message,
        ));
  }

}
