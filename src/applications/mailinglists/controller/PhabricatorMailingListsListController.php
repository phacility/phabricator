<?php

final class PhabricatorMailingListsListController
  extends PhabricatorMailingListsController
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
      ->setSearchEngine(new PhabricatorMailingListSearchEngine())
      ->setNavigation($this->buildSideNavView());

    return $this->delegateToController($controller);
  }

  public function renderResultsList(
    array $lists,
    PhabricatorSavedQuery $query) {
    assert_instances_of($lists, 'PhabricatorMetaMTAMailingList');

    $view = id(new PHUIObjectItemListView());

    foreach ($lists as $list) {
      $item = new PHUIObjectItemView();

      $item->setHeader($list->getName());
      $item->setHref($list->getURI());
      $item->addAttribute($list->getEmail());
      $item->addAction(
        id(new PHUIListItemView())
          ->setIcon('edit')
          ->setHref($this->getApplicationURI('/edit/'.$list->getID().'/')));

      $view->addItem($item);
    }

    return $view;
  }

}
