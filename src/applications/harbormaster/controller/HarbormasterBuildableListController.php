<?php

final class HarbormasterBuildableListController
  extends HarbormasterController
  implements PhabricatorApplicationSearchResultsControllerInterface {

  private $queryKey;

  public function shouldAllowPublic() {
    return true;
  }

  public function willProcessRequest(array $data) {
    $this->queryKey = idx($data, 'queryKey');
  }

  public function processRequest() {
    $request = $this->getRequest();
    $controller = id(new PhabricatorApplicationSearchController($request))
      ->setQueryKey($this->queryKey)
      ->setSearchEngine(new HarbormasterBuildableSearchEngine())
      ->setNavigation($this->buildSideNavView());

    return $this->delegateToController($controller);
  }

  public function renderResultsList(
    array $buildables,
    PhabricatorSavedQuery $query) {
    assert_instances_of($buildables, 'HarbormasterBuildable');

    $viewer = $this->getRequest()->getUser();

    $list = new PHUIObjectItemListView();
    foreach ($buildables as $buildable) {
      $id = $buildable->getID();

      $item = id(new PHUIObjectItemView())
        ->setHeader(pht('Buildable %d', $buildable->getID()));

      if ($id) {
        $item->setHref("/B{$id}");
      }

      $list->addItem($item);
    }

    return $list;
  }

  public function buildSideNavView($for_app = false) {
    $user = $this->getRequest()->getUser();

    $nav = new AphrontSideNavFilterView();
    $nav->setBaseURI(new PhutilURI($this->getApplicationURI()));

    id(new HarbormasterBuildableSearchEngine())
      ->setViewer($user)
      ->addNavigationItems($nav->getMenu());

    if ($for_app) {
      $nav->addFilter('new/', pht('New Build Plan'));
    }

    $nav->addLabel('Utilities');
    $nav->addFilter('buildable/edit/', pht('New Manual Build'));
    $nav->addFilter('plan/', pht('Manage Build Plans'));

    $nav->selectFilter(null);

    return $nav;
  }

  public function buildApplicationMenu() {
    return $this->buildSideNavView(true)->getMenu();
  }

}
