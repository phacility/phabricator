<?php

final class PhortuneChargeListController
  extends PhortuneController {

  private $accountID;
  private $queryKey;

  private $account;

  public function willProcessRequest(array $data) {
    $this->accountID = idx($data, 'accountID');
    $this->queryKey = idx($data, 'queryKey');
  }

  public function processRequest() {
    $request = $this->getRequest();
    $viewer = $request->getUser();

    $engine = new PhortuneChargeSearchEngine();

    if ($this->accountID) {
      $account = id(new PhortuneAccountQuery())
        ->setViewer($viewer)
        ->withIDs(array($this->accountID))
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
      $engine->setAccount($account);
    } else {
      return new Aphront404Response();
    }

    $controller = id(new PhabricatorApplicationSearchController())
      ->setQueryKey($this->queryKey)
      ->setSearchEngine($engine)
      ->setNavigation($this->buildSideNavView());

    return $this->delegateToController($controller);
  }

  public function buildSideNavView() {
    $viewer = $this->getRequest()->getUser();

    $nav = new AphrontSideNavFilterView();
    $nav->setBaseURI(new PhutilURI($this->getApplicationURI()));

    id(new PhortuneChargeSearchEngine())
      ->setViewer($viewer)
      ->addNavigationItems($nav->getMenu());

    $nav->selectFilter(null);

    return $nav;
  }

  protected function buildApplicationCrumbs() {
    $crumbs = parent::buildApplicationCrumbs();

    $account = $this->account;
    if ($account) {
      $id = $account->getID();
      $crumbs->addTextCrumb(
        $account->getName(),
        $this->getApplicationURI("{$id}/"));
      $crumbs->addTextCrumb(
        pht('Charges'),
        $this->getApplicationURI("{$id}/charge/"));
    }

    return $crumbs;
  }

}
