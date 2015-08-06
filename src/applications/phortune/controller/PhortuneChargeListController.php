<?php

final class PhortuneChargeListController
  extends PhortuneController {

  private $account;

  public function handleRequest(AphrontRequest $request) {
    $viewer = $request->getViewer();
    $querykey = $request->getURIData('queryKey');
    $account_id = $request->getURIData('accountID');

    $engine = new PhortuneChargeSearchEngine();

    if ($account_id) {
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
      $engine->setAccount($account);
    } else {
      return new Aphront404Response();
    }

    $controller = id(new PhabricatorApplicationSearchController())
      ->setQueryKey($querykey)
      ->setSearchEngine($engine)
      ->setNavigation($this->buildSideNavView());

    return $this->delegateToController($controller);
  }

  public function buildSideNavView() {
    $viewer = $this->getViewer();

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
