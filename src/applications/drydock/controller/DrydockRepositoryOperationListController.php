<?php

final class DrydockRepositoryOperationListController
  extends DrydockController {

  public function shouldAllowPublic() {
    return true;
  }

  public function handleRequest(AphrontRequest $request) {
    $query_key = $request->getURIData('queryKey');

    $engine = new DrydockRepositoryOperationSearchEngine();

    $controller = id(new PhabricatorApplicationSearchController())
      ->setQueryKey($query_key)
      ->setSearchEngine($engine)
      ->setNavigation($this->buildSideNavView());

    return $this->delegateToController($controller);
  }

  public function buildSideNavView() {
    $nav = new AphrontSideNavFilterView();
    $nav->setBaseURI(new PhutilURI($this->getApplicationURI()));

    $engine = id(new DrydockRepositoryOperationSearchEngine())
      ->setViewer($this->getViewer());

    $engine->addNavigationItems($nav->getMenu());

    $nav->selectFilter(null);

    return $nav;
  }

}
