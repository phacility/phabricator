<?php

final class PhabricatorUserLogView extends AphrontView {

  private $logs;
  private $searchBaseURI;

  public function setSearchBaseURI($search_base_uri) {
    $this->searchBaseURI = $search_base_uri;
    return $this;
  }

  public function setLogs(array $logs) {
    assert_instances_of($logs, 'PhabricatorUserLog');
    $this->logs = $logs;
    return $this;
  }

  public function render() {
    $logs = $this->logs;
    $viewer = $this->getUser();

    $phids = array();
    foreach ($logs as $log) {
      $phids[] = $log->getActorPHID();
      $phids[] = $log->getUserPHID();
    }
    $handles = $viewer->loadHandles($phids);

    $action_map = PhabricatorUserLog::getActionTypeMap();
    $base_uri = $this->searchBaseURI;

    $rows = array();
    foreach ($logs as $log) {
      $ip = $log->getRemoteAddr();
      $session = substr($log->getSession(), 0, 6);

      if ($base_uri) {
        $ip = phutil_tag(
          'a',
          array(
            'href' => $base_uri.'?ip='.$ip.'#R',
          ),
          $ip);
      }

      $action = $log->getAction();
      $action_name = idx($action_map, $action, $action);

      $rows[] = array(
        phabricator_date($log->getDateCreated(), $viewer),
        phabricator_time($log->getDateCreated(), $viewer),
        $action_name,
        $log->getActorPHID()
          ? $handles[$log->getActorPHID()]->getName()
          : null,
        $username = $handles[$log->getUserPHID()]->renderLink(),
        $ip,
        $session,
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

    return $table;
  }
}
