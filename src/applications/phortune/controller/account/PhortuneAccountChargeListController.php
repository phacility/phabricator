<?php

final class PhortuneAccountChargeListController
  extends PhortuneAccountProfileController {

  protected function shouldRequireAccountEditCapability() {
    return false;
  }

  protected function handleAccountRequest(AphrontRequest $request) {
    $viewer = $request->getViewer();
    $account = $this->getAccount();

    return id(new PhortuneChargeSearchEngine())
      ->setAccount($account)
      ->setController($this)
      ->buildResponse();
  }

  protected function buildApplicationCrumbs() {
    $crumbs = parent::buildApplicationCrumbs();

    if ($this->hasAccount()) {
      $account = $this->getAccount();
      $id = $account->getID();

      $crumbs->addTextCrumb(
        pht('Charges'),
        $account->getChargesURI());
    }

    return $crumbs;
  }

}
