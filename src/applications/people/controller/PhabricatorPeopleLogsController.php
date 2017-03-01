<?php

final class PhabricatorPeopleLogsController
  extends PhabricatorPeopleController {

  public function handleRequest(AphrontRequest $request) {
   $controller = id(new PhabricatorApplicationSearchController())
      ->setQueryKey($request->getURIData('queryKey'))
      ->setSearchEngine(new PhabricatorPeopleLogSearchEngine())
      ->setNavigation($this->buildSideNavView());

    return $this->delegateToController($controller);
  }

  public function buildSideNavView($for_app = false) {
    $nav = new AphrontSideNavFilterView();
    $nav->setBaseURI(new PhutilURI($this->getApplicationURI()));

    $viewer = $this->getRequest()->getUser();

    id(new PhabricatorPeopleLogSearchEngine())
      ->setViewer($viewer)
      ->addNavigationItems($nav->getMenu());

    return $nav;
  }

}
