<?php

final class DrydockLeaseListController extends DrydockController
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
      ->setSearchEngine(new DrydockLeaseSearchEngine())
      ->setNavigation($this->buildSideNav());

    return $this->delegateToController($controller);
  }

  public function renderResultsList(
    array $leases,
    PhabricatorSavedQuery $query) {
    assert_instances_of($leases, 'DrydockLease');

    return $this->buildLeaseListView($leases);
  }

}
