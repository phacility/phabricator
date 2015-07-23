<?php

final class PhabricatorDashboardPanelListController
  extends PhabricatorDashboardController {

  private $queryKey;

  public function shouldAllowPublic() {
    return true;
  }

  public function handleRequest(AphrontRequest $request) {
    $query_key = $request->getURIData('queryKey');

    $controller = id(new PhabricatorApplicationSearchController())
      ->setQueryKey($query_key)
      ->setSearchEngine(new PhabricatorDashboardPanelSearchEngine())
      ->setNavigation($this->buildSideNavView());
    return $this->delegateToController($controller);
  }

  public function buildSideNavView() {
    $user = $this->getRequest()->getUser();

    $nav = new AphrontSideNavFilterView();
    $nav->setBaseURI(new PhutilURI($this->getApplicationURI()));

    id(new PhabricatorDashboardPanelSearchEngine())
      ->setViewer($user)
      ->addNavigationItems($nav->getMenu());

    $nav->selectFilter(null);

    return $nav;
  }

  protected function buildApplicationCrumbs() {
    $crumbs = parent::buildApplicationCrumbs();

    $crumbs->addTextCrumb(pht('Panels'), $this->getApplicationURI().'panel/');

    $crumbs->addAction(
      id(new PHUIListItemView())
        ->setIcon('fa-plus-square')
        ->setName(pht('Create Panel'))
        ->setHref($this->getApplicationURI().'panel/create/'));

    return $crumbs;
  }

}
