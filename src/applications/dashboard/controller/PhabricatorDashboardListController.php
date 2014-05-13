<?php

final class PhabricatorDashboardListController
  extends PhabricatorDashboardController {

  private $queryKey;

  public function willProcessRequest(array $data) {
    $this->queryKey = idx($data, 'queryKey');
  }

  public function processRequest() {
    $request = $this->getRequest();
    $controller = id(new PhabricatorApplicationSearchController($request))
      ->setQueryKey($this->queryKey)
      ->setSearchEngine(new PhabricatorDashboardSearchEngine())
      ->setNavigation($this->buildSideNavView());
    return $this->delegateToController($controller);
  }

  public function buildSideNavView() {
    $user = $this->getRequest()->getUser();

    $nav = new AphrontSideNavFilterView();
    $nav->setBaseURI(new PhutilURI($this->getApplicationURI()));

    id(new PhabricatorDashboardSearchEngine())
      ->setViewer($user)
      ->addNavigationItems($nav->getMenu());

    $nav->addLabel(pht('Panels'));
    $nav->addFilter('panel/', pht('Manage Panels'));

    $nav->selectFilter(null);

    return $nav;
  }

  public function buildApplicationCrumbs() {
    $crumbs = parent::buildApplicationCrumbs();

    $crumbs->addAction(
      id(new PHUIListItemView())
        ->setIcon('fa-plus-square')
        ->setName(pht('Create Dashboard'))
        ->setHref($this->getApplicationURI().'create/'));

    return $crumbs;
  }

}
