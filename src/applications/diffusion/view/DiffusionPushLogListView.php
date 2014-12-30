<?php

final class DiffusionPushLogListView extends AphrontView {

  private $logs;
  private $handles;

  public function setLogs(array $logs) {
    assert_instances_of($logs, 'PhabricatorRepositoryPushLog');
    $this->logs = $logs;
    return $this;
  }

  public function setHandles(array $handles) {
    $this->handles = $handles;
    return $this;
  }

  public function render() {
    $logs = $this->logs;
    $viewer = $this->getUser();
    $handles = $this->handles;

    // Figure out which repositories are editable. We only let you see remote
    // IPs if you have edit capability on a repository.
    $editable_repos = array();
    if ($logs) {
      $editable_repos = id(new PhabricatorRepositoryQuery())
        ->setViewer($viewer)
        ->requireCapabilities(
          array(
            PhabricatorPolicyCapability::CAN_VIEW,
            PhabricatorPolicyCapability::CAN_EDIT,
          ))
        ->withPHIDs(mpull($logs, 'getRepositoryPHID'))
        ->execute();
      $editable_repos = mpull($editable_repos, null, 'getPHID');
    }

    $rows = array();
    foreach ($logs as $log) {

      // Reveal this if it's valid and the user can edit the repository.
      $remote_addr = '-';
      if (isset($editable_repos[$log->getRepositoryPHID()])) {
        $remote_long = $log->getPushEvent()->getRemoteAddress();
        if ($remote_long) {
          $remote_addr = long2ip($remote_long);
        }
      }

      $event_id = $log->getPushEvent()->getID();

      $callsign = $log->getRepository()->getCallsign();
      $old_ref_link = null;
      if ($log->getRefOld() != DiffusionCommitHookEngine::EMPTY_HASH) {
        $old_ref_link = phutil_tag(
          'a',
          array(
            'href' => '/r'.$callsign.$log->getRefOld(),
          ),
          $log->getRefOldShort());
      }
      $rows[] = array(
        phutil_tag(
          'a',
          array(
            'href' => '/diffusion/pushlog/view/'.$event_id.'/',
          ),
          $event_id),
        phutil_tag(
          'a',
          array(
            'href' => '/diffusion/'.$callsign.'/',
          ),
          $callsign),
        $handles[$log->getPusherPHID()]->renderLink(),
        $remote_addr,
        $log->getPushEvent()->getRemoteProtocol(),
        $log->getRefType(),
        $log->getRefName(),
        $old_ref_link,
        phutil_tag(
          'a',
          array(
            'href' => '/r'.$callsign.$log->getRefNew(),
          ),
          $log->getRefNewShort()),

        // TODO: Make these human-readable.
        $log->getChangeFlags(),
        $log->getPushEvent()->getRejectCode(),
        phabricator_datetime($log->getEpoch(), $viewer),
      );
    }

    $table = id(new AphrontTableView($rows))
      ->setHeaders(
        array(
          pht('Push'),
          pht('Repository'),
          pht('Pusher'),
          pht('From'),
          pht('Via'),
          pht('Type'),
          pht('Name'),
          pht('Old'),
          pht('New'),
          pht('Flags'),
          pht('Code'),
          pht('Date'),
        ))
      ->setColumnClasses(
        array(
          '',
          '',
          '',
          '',
          '',
          '',
          'wide',
          'n',
          'n',
          'date',
        ));

    return $table;
  }

}
