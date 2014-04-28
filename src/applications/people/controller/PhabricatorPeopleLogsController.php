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

    $action_map = PhabricatorUserLog::getActionTypeMap();

    $rows = array();
    foreach ($logs as $log) {

      $ip_href = $this->getApplicationURI(
        'logs/?ip='.$log->getRemoteAddr());

      $session_href = $this->getApplicationURI(
        'logs/?sessions='.$log->getSession());

      $action = $log->getAction();
      $action_name = idx($action_map, $action, $action);

      $rows[] = array(
        phabricator_date($log->getDateCreated(), $viewer),
        phabricator_time($log->getDateCreated(), $viewer),
        $action_name,
        $log->getActorPHID()
          ? $handles[$log->getActorPHID()]->getName()
          : null,
        $handles[$log->getUserPHID()]->getName(),
        phutil_tag(
          'a',
          array(
            'href' => $ip_href,
          ),
          $log->getRemoteAddr()),
        phutil_tag(
          'a',
          array(
            'href' => $session_href,
          ),
          substr($log->getSession(), 0, 6)),
      );
    }

    $table = new AphrontTableView($rows);
    $table->setHeaders(
      array(
        pht('Date'),
        pht('Time'),
        pht('Action'),
        pht('Actor'),
        pht('User'),
        pht('IP'),
        pht('Session'),
      ));
    $table->setColumnClasses(
      array(
        '',
        'right',
        'wide',
        '',
        '',
        '',
        'n',
      ));

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
