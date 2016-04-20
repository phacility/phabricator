<?php

final class DiffusionPushLogListView extends AphrontView {

  private $logs;

  public function setLogs(array $logs) {
    assert_instances_of($logs, 'PhabricatorRepositoryPushLog');
    $this->logs = $logs;
    return $this;
  }

  public function render() {
    $logs = $this->logs;
    $viewer = $this->getViewer();

    $handle_phids = array();
    foreach ($logs as $log) {
      $handle_phids[] = $log->getPusherPHID();
      $device_phid = $log->getDevicePHID();
      if ($device_phid) {
        $handle_phids[] = $device_phid;
      }
    }

    $handles = $viewer->loadHandles($handle_phids);

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
    $any_host = false;
    foreach ($logs as $log) {
      $repository = $log->getRepository();

      // Reveal this if it's valid and the user can edit the repository.
      $remote_address = '-';
      if (isset($editable_repos[$log->getRepositoryPHID()])) {
        $remote_address = $log->getPushEvent()->getRemoteAddress();
      }

      $event_id = $log->getPushEvent()->getID();

      $old_ref_link = null;
      if ($log->getRefOld() != DiffusionCommitHookEngine::EMPTY_HASH) {
        $old_ref_link = phutil_tag(
          'a',
          array(
            'href' => $repository->getCommitURI($log->getRefOld()),
          ),
          $log->getRefOldShort());
      }

      $device_phid = $log->getDevicePHID();
      if ($device_phid) {
        $device = $viewer->renderHandle($device_phid);
        $any_host = true;
      } else {
        $device = null;
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
            'href' => $repository->getURI(),
          ),
          $repository->getDisplayName()),
        $viewer->renderHandle($log->getPusherPHID()),
        $remote_address,
        $log->getPushEvent()->getRemoteProtocol(),
        $device,
        $log->getRefType(),
        $log->getRefName(),
        $old_ref_link,
        phutil_tag(
          'a',
          array(
            'href' => $repository->getCommitURI($log->getRefNew()),
          ),
          $log->getRefNewShort()),

        // TODO: Make these human-readable.
        $log->getChangeFlags(),
        $log->getPushEvent()->getRejectCode(),
        $viewer->formatShortDateTime($log->getEpoch()),
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
          pht('Host'),
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
          '',
          'wide',
          'n',
          'n',
          'right',
        ))
      ->setColumnVisibility(
        array(
          true,
          true,
          true,
          true,
          true,
          $any_host,
        ));

    return $table;
  }

}
