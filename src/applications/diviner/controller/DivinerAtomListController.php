<?php

final class DivinerAtomListController extends DivinerController
  implements PhabricatorApplicationSearchResultsControllerInterface {

  private $key;

  public function shouldAllowPublic() {
    return true;
  }

  public function willProcessRequest(array $data) {
    $this->key = idx($data, 'key', 'all');
  }

  public function processRequest() {
    $request = $this->getRequest();
    $controller = id(new PhabricatorApplicationSearchController($request))
      ->setQueryKey($this->key)
      ->setSearchEngine(new DivinerAtomSearchEngine())
      ->setNavigation($this->buildSideNavView());

    return $this->delegateToController($controller);
  }

  public function renderResultsList(
    array $symbols,
    PhabricatorSavedQuery $query) {

    assert_instances_of($symbols, 'DivinerLiveSymbol');

    $request = $this->getRequest();
    $viewer = $request->getUser();

    $list = id(new PHUIObjectItemListView())
      ->setUser($viewer);

    foreach ($symbols as $symbol) {
      $type = $symbol->getType();
      $type_name = DivinerAtom::getAtomTypeNameString($type);

      $item = id(new PHUIObjectItemView())
        ->setHeader($symbol->getTitle())
        ->setHref($symbol->getURI())
        ->addAttribute($symbol->getSummary())
        ->addIcon('none', $type_name);

      $list->addItem($item);
    }

    return $list;
  }

}
