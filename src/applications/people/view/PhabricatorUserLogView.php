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

    $viewer_phid = $viewer->getPHID();

    $rows = array();
    foreach ($logs as $log) {
      $session = substr($log->getSession(), 0, 6);

      $actor_phid = $log->getActorPHID();
      $user_phid = $log->getUserPHID();

      if ($viewer->getIsAdmin()) {
        $can_see_ip = true;
      } else if ($viewer_phid == $actor_phid) {
        // You can see the address if you took the action.
        $can_see_ip = true;
      } else if (!$actor_phid && ($viewer_phid == $user_phid)) {
        // You can see the address if it wasn't authenticated and applied
        // to you (partial login).
        $can_see_ip = true;
      } else {
        // You can't see the address when an administrator disables your
        // account, since it's their address.
        $can_see_ip = false;
      }

      if ($can_see_ip) {
        $ip = $log->getRemoteAddr();
        if ($base_uri) {
          $ip = phutil_tag(
            'a',
            array(
              'href' => $base_uri.'?ip='.$ip.'#R',
            ),
            $ip);
        }
      } else {
        $ip = null;
      }

      $action = $log->getAction();
      $action_name = idx($action_map, $action, $action);

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

      $rows[] = array(
        phabricator_date($log->getDateCreated(), $viewer),
        phabricator_time($log->getDateCreated(), $viewer),
        $action_name,
        $actor_name,
        $user_name,
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
