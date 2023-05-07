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

    $types = PhabricatorUserLogType::getAllLogTypes();
    $types = mpull($types, 'getLogTypeName', 'getLogTypeKey');

    $base_uri = $this->searchBaseURI;

    $viewer_phid = $viewer->getPHID();

    $rows = array();
    foreach ($logs as $log) {
      // Events such as "Login Failure" will not have an associated session.
      $session = $log->getSession();
      if ($session === null) {
        $session = '';
      }
      $session = substr($session, 0, 6);

      $actor_phid = $log->getActorPHID();
      $user_phid = $log->getUserPHID();

      $remote_address = $log->getRemoteAddressForViewer($viewer);
      if ($remote_address !== null) {
        if ($base_uri) {
          $remote_address = phutil_tag(
            'a',
            array(
              'href' => $base_uri.'?ip='.$remote_address.'#R',
            ),
            $remote_address);
        }
      }

      $action = $log->getAction();
      $action_name = idx($types, $action, $action);

      if ($actor_phid) {
        $actor_name = $handles[$actor_phid]->renderLink();
      } else {
        $actor_name = null;
      }

      if ($user_phid) {
        $user_name = $handles[$user_phid]->renderLink();
      } else {
        $user_name = null;
      }

      $action_link = phutil_tag(
        'a',
        array(
          'href' => $log->getURI(),
        ),
        $action_name);

      $rows[] = array(
        $log->getID(),
        $action_link,
        $actor_name,
        $user_name,
        $remote_address,
        $session,
        phabricator_date($log->getDateCreated(), $viewer),
        phabricator_time($log->getDateCreated(), $viewer),
      );
    }

    $table = new AphrontTableView($rows);
    $table->setHeaders(
      array(
        pht('ID'),
        pht('Action'),
        pht('Actor'),
        pht('User'),
        pht('IP'),
        pht('Session'),
        pht('Date'),
        pht('Time'),
      ));
    $table->setColumnClasses(
      array(
        '',
        'wide',
        '',
        '',
        '',
        'n',
        '',
        'right',
      ));

    return $table;
  }
}
