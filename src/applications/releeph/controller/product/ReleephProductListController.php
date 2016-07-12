<?php

final class ReleephProductListController extends ReleephController {

  public function shouldAllowPublic() {
    return true;
  }

  public function handleRequest(AphrontRequest $request) {
    $query_key = $request->getURIData('queryKey');
    $controller = id(new PhabricatorApplicationSearchController())
      ->setQueryKey($query_key)
      ->setSearchEngine(new ReleephProductSearchEngine())
      ->setNavigation($this->buildSideNavView());

    return $this->delegateToController($controller);
  }

  protected function buildApplicationCrumbs() {
    $crumbs = parent::buildApplicationCrumbs();

    $crumbs->addAction(
      id(new PHUIListItemView())
        ->setName(pht('Create Product'))
        ->setHref($this->getApplicationURI('product/create/'))
        ->setIcon('fa-plus-square'));

    return $crumbs;
  }

}
