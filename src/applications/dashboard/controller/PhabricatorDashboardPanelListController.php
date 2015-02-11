<?php

final class PhabricatorDashboardPanelListController
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
