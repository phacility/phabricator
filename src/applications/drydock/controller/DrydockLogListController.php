<?php

final class DrydockLogListController extends DrydockLogController
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
      ->setSearchEngine(new DrydockLogSearchEngine())
      ->setNavigation($this->buildSideNavView());

    return $this->delegateToController($controller);
  }

  public function renderResultsList(
    array $logs,
    PhabricatorSavedQuery $query) {
    assert_instances_of($logs, 'DrydockLog');

    return $this->buildLogTableView($logs);
  }

}
