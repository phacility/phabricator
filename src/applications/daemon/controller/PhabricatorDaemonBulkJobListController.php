<?php

final class PhabricatorDaemonBulkJobListController
  extends PhabricatorDaemonController {

  public function shouldAllowPublic() {
    return true;
  }

  public function handleRequest(AphrontRequest $request) {
    $controller = id(new PhabricatorApplicationSearchController())
      ->setQueryKey($request->getURIData('queryKey'))
      ->setSearchEngine(new PhabricatorWorkerBulkJobSearchEngine())
      ->setNavigation($this->buildSideNavView());
    return $this->delegateToController($controller);
  }

  protected function buildSideNavView($for_app = false) {
    $user = $this->getRequest()->getUser();

    $nav = new AphrontSideNavFilterView();
    $nav->setBaseURI(new PhutilURI($this->getApplicationURI()));

    id(new PhabricatorWorkerBulkJobSearchEngine())
      ->setViewer($user)
      ->addNavigationItems($nav->getMenu());
    $nav->selectFilter(null);

    return $nav;
  }
}
