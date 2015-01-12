<?php

final class DiffusionPushLogListController extends DiffusionPushLogController {

  public function shouldAllowPublic() {
    return true;
  }

  protected function processDiffusionRequest(AphrontRequest $request) {
    $request = $this->getRequest();
    $controller = id(new PhabricatorApplicationSearchController())
      ->setQueryKey($request->getURIData('queryKey'))
      ->setSearchEngine(new PhabricatorRepositoryPushLogSearchEngine())
      ->setNavigation($this->buildSideNavView());

    return $this->delegateToController($controller);
  }

  public function buildSideNavView($for_app = false) {
    $viewer = $this->getRequest()->getUser();

    $nav = new AphrontSideNavFilterView();
    $nav->setBaseURI(new PhutilURI($this->getApplicationURI()));

    id(new PhabricatorRepositoryPushLogSearchEngine())
      ->setViewer($viewer)
      ->addNavigationItems($nav->getMenu());

    $nav->selectFilter(null);

    return $nav;
  }

}
