<?php

final class ReleephProductListController extends ReleephController
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
      ->setSearchEngine(new ReleephProductSearchEngine())
      ->setNavigation($this->buildSideNavView());

    return $this->delegateToController($controller);
  }

  public function renderResultsList(
    array $products,
    PhabricatorSavedQuery $query) {
    assert_instances_of($products, 'ReleephProject');
    $viewer = $this->getRequest()->getUser();

    $list = id(new PHUIObjectItemListView())
      ->setUser($viewer);

    foreach ($products as $product) {
      $id = $product->getID();

      $item = id(new PHUIObjectItemView())
        ->setHeader($product->getName())
        ->setHref($this->getApplicationURI("project/{$id}/"));

      if (!$product->getIsActive()) {
        $item->setDisabled(true);
        $item->addIcon('none', pht('Inactive'));
      }

      $repo = $product->getRepository();
      $item->addAttribute(
        phutil_tag(
          'a',
          array(
            'href' => '/diffusion/'.$repo->getCallsign().'/',
          ),
          'r'.$repo->getCallsign()));

      $arc = $product->loadArcanistProject();
      if ($arc) {
        $item->addAttribute($arc->getName());
      }

      $list->addItem($item);
    }

    return $list;
  }

  public function buildApplicationCrumbs() {
    $crumbs = parent::buildApplicationCrumbs();

    $crumbs->addAction(
      id(new PHUIListItemView())
        ->setName(pht('Create Product'))
        ->setHref($this->getApplicationURI('project/create/'))
        ->setIcon('create'));

    return $crumbs;
  }

}
