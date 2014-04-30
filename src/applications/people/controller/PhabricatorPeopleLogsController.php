<?php

final class PhabricatorPeopleLogsController extends PhabricatorPeopleController
  implements PhabricatorApplicationSearchResultsControllerInterface {

  private $queryKey;

  public function willProcessRequest(array $data) {
    $this->queryKey = idx($data, 'queryKey');
  }

  public function processRequest() {
    $request = $this->getRequest();
    $controller = id(new PhabricatorApplicationSearchController($request))
      ->setQueryKey($this->queryKey)
      ->setSearchEngine(new PhabricatorPeopleLogSearchEngine())
      ->setNavigation($this->buildSideNavView());

    return $this->delegateToController($controller);
  }

  public function renderResultsList(
    array $logs,
    PhabricatorSavedQuery $query) {
    assert_instances_of($logs, 'PhabricatorUserLog');

    $request = $this->getRequest();
    $viewer = $request->getUser();

    $phids = array();
    foreach ($logs as $log) {
      $phids[$log->getActorPHID()] = true;
      $phids[$log->getUserPHID()] = true;
    }
    $phids = array_keys($phids);
    $handles = $this->loadViewerHandles($phids);

    $table = id(new PhabricatorUserLogView())
      ->setUser($viewer)
      ->setLogs($logs)
      ->setSearchBaseURI($this->getApplicationURI('logs/'))
      ->setHandles($handles);

    return id(new PHUIObjectBoxView())
      ->setHeaderText(pht('User Activity Logs'))
      ->appendChild($table);
  }


  public function buildSideNavView() {
    $nav = new AphrontSideNavFilterView();
    $nav->setBaseURI(new PhutilURI($this->getApplicationURI()));

    $viewer = $this->getRequest()->getUser();

    id(new PhabricatorPeopleLogSearchEngine())
      ->setViewer($viewer)
      ->addNavigationItems($nav->getMenu());

    return $nav;
  }

}
