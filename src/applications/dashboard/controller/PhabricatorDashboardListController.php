<?php

final class PhabricatorDashboardListController
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
        ->setIcon('create')
        ->setName(pht('Create Dashboard'))
        ->setHref($this->getApplicationURI().'create/'));

    return $crumbs;
  }

  public function renderResultsList(
    array $dashboards,
    PhabricatorSavedQuery $query) {
    $viewer = $this->getRequest()->getUser();

    $list = new PHUIObjectItemListView();
    $list->setUser($viewer);
    foreach ($dashboards as $dashboard) {
      $id = $dashboard->getID();

      $item = id(new PHUIObjectItemView())
        ->setObjectName(pht('Dashboard %d', $id))
        ->setHeader($dashboard->getName())
        ->setHref($this->getApplicationURI("view/{$id}/"))
        ->setObject($dashboard);

      $list->addItem($item);
    }

    return $list;
  }

}
