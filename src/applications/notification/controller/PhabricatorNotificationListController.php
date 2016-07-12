<?php

final class PhabricatorNotificationListController
  extends PhabricatorNotificationController {

  public function handleRequest(AphrontRequest $request) {
    $querykey = $request->getURIData('queryKey');

    $controller = id(new PhabricatorApplicationSearchController())
      ->setQueryKey($querykey)
      ->setSearchEngine(new PhabricatorNotificationSearchEngine())
      ->setNavigation($this->buildSideNavView());

    return $this->delegateToController($controller);
  }

  public function buildSideNavView() {
    $viewer = $this->getViewer();

    $nav = new AphrontSideNavFilterView();
    $nav->setBaseURI(new PhutilURI($this->getApplicationURI()));

    id(new PhabricatorNotificationSearchEngine())
      ->setViewer($viewer)
      ->addNavigationItems($nav->getMenu());
    $nav->selectFilter(null);

    return $nav;
  }

}
