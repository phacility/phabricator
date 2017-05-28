<?php

abstract class PhortuneAccountController
  extends PhortuneController {

  private $account;

  protected function getAccount() {
    return $this->account;
  }

  protected function buildApplicationCrumbs() {
    $crumbs = parent::buildApplicationCrumbs();

    $account = $this->getAccount();
    if ($account) {
      $crumbs->addTextCrumb($account->getName(), $account->getURI());
    }

    return $crumbs;
  }

  protected function loadAccount() {
    // TODO: Currently, you must be able to edit an account to view the detail
    // page, because the account must be broadly visible so merchants can
    // process orders but merchants should not be able to see all the details
    // of an account. Ideally the profile pages should be visible to merchants,
    // too, just with less information.
    return $this->loadAccountForEdit();
  }


  protected function loadAccountForEdit() {
    $viewer = $this->getViewer();
    $request = $this->getRequest();

    $account_id = $request->getURIData('accountID');
    if (!$account_id) {
      $account_id = $request->getURIData('id');
    }

    if (!$account_id) {
      return new Aphront404Response();
    }

    $account = id(new PhortuneAccountQuery())
      ->setViewer($viewer)
      ->withIDs(array($account_id))
      ->requireCapabilities(
        array(
          PhabricatorPolicyCapability::CAN_VIEW,
          PhabricatorPolicyCapability::CAN_EDIT,
        ))
      ->executeOne();
    if (!$account) {
      return new Aphront404Response();
    }

    $this->account = $account;

    return null;
  }

}
