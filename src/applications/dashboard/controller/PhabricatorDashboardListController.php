<?php

final class PhabricatorDashboardListController
  extends PhabricatorDashboardController {

  private $queryKey;

  public function shouldAllowPublic() {
    return true;
  }

  public function willProcessRequest(array $data) {
    $this->queryKey = idx($data, 'queryKey');
  }

  public function processRequest() {
    $controller = id(new PhabricatorApplicationSearchController())
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

  protected function buildApplicationCrumbs() {
    $crumbs = parent::buildApplicationCrumbs();

    $crumbs->addAction(
      id(new PHUIListItemView())
        ->setIcon('fa-plus-square')
        ->setName(pht('Create Dashboard'))
        ->setHref($this->getApplicationURI().'create/'));

    return $crumbs;
  }

}
