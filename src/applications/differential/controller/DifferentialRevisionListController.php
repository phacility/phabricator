<?php

final class DifferentialRevisionListController extends DifferentialController {

  public function shouldAllowPublic() {
    return true;
  }

  public function handleRequest(AphrontRequest $request) {
    $controller = id(new PhabricatorApplicationSearchController())
      ->setQueryKey($request->getURIData('queryKey'))
      ->setSearchEngine(new DifferentialRevisionSearchEngine())
      ->setNavigation($this->buildSideNavView());

    return $this->delegateToController($controller);
  }

  protected function buildApplicationCrumbs() {
    $crumbs = parent::buildApplicationCrumbs();

    $crumbs->addAction(
      id(new PHUIListItemView())
        ->setHref($this->getApplicationURI('/diff/create/'))
        ->setName(pht('Create Diff'))
        ->setIcon('fa-plus-square'));

    return $crumbs;
  }

}
