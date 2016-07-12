<?php

final class PhamePostListController extends PhamePostController {

  public function shouldAllowPublic() {
    return true;
  }

  public function handleRequest(AphrontRequest $request) {
    $query_key = $request->getURIData('queryKey');
    $controller = id(new PhabricatorApplicationSearchController())
      ->setQueryKey($query_key)
      ->setSearchEngine(new PhamePostSearchEngine())
      ->setNavigation($this->buildSideNavView());

    return $this->delegateToController($controller);
  }

  public function buildSideNavView() {
    $viewer = $this->getRequest()->getUser();

    $nav = new AphrontSideNavFilterView();
    $nav->setBaseURI(new PhutilURI($this->getApplicationURI()));

    id(new PhamePostSearchEngine())
      ->setViewer($viewer)
      ->addNavigationItems($nav->getMenu());

    $nav->addLabel(pht('Blogs'));
    $nav->addFilter('blog/', pht('Manage Blogs'));

    $nav->selectFilter(null);

    return $nav;
  }


}
