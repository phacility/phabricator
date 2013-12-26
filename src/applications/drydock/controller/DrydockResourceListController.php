<?php

final class DrydockResourceListController extends DrydockController
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
      ->setSearchEngine(new DrydockResourceSearchEngine())
      ->setNavigation($this->buildSideNav());

    return $this->delegateToController($controller);
  }

  public function renderResultsList(
    array $resources,
    PhabricatorSavedQuery $query) {
    assert_instances_of($resources, 'DrydockResource');

    return $this->buildResourceListView($resources);
  }

}
