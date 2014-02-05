<?php

final class PhabricatorDashboardPanelListController
  extends PhabricatorDashboardController
  implements PhabricatorApplicationSearchResultsControllerInterface {

  private $queryKey;
  public function willProcessRequest(array $data) {
    $this->queryKey = idx($data, 'queryKey');
  }

  public function processRequest() {
    $request = $this->getRequest();
    $controller = id(new PhabricatorApplicationSearchController($request))
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

  public function buildApplicationCrumbs() {
    $crumbs = parent::buildApplicationCrumbs();

    $crumbs->addTextCrumb(pht('Panels'), $this->getApplicationURI().'panel/');

    $crumbs->addAction(
      id(new PHUIListItemView())
        ->setIcon('create')
        ->setName(pht('Create Panel'))
        ->setHref($this->getApplicationURI().'panel/create/'));

    return $crumbs;
  }

  public function renderResultsList(
    array $panels,
    PhabricatorSavedQuery $query) {

    $viewer = $this->getRequest()->getUser();

    $list = new PHUIObjectItemListView();
    $list->setUser($viewer);
    foreach ($panels as $panel) {
      $item = id(new PHUIObjectItemView())
        ->setObjectName($panel->getMonogram())
        ->setHeader($panel->getName())
        ->setHref('/'.$panel->getMonogram())
        ->setObject($panel);

      $list->addItem($item);
    }

    return $list;
  }

}
