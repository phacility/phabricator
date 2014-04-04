<?php

abstract class DiffusionPushLogController extends DiffusionController {

  public function renderPushLogTable(array $logs) {
    $viewer = $this->getRequest()->getUser();

    $this->loadHandles(mpull($logs, 'getPusherPHID'));

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
      $rows[] = array(
        phutil_tag(
          'a',
          array(
            'href' => $this->getApplicationURI('pushlog/view/'.$event_id.'/'),
          ),
          $event_id),
        phutil_tag(
          'a',
          array(
            'href' => $this->getApplicationURI($callsign.'/'),
          ),
          $callsign),
        $this->getHandle($log->getPusherPHID())->renderLink(),
        $remote_addr,
        $log->getPushEvent()->getRemoteProtocol(),
        $log->getRefType(),
        $log->getRefName(),
        phutil_tag(
          'a',
          array(
            'href' => '/r'.$callsign.$log->getRefOld(),
          ),
          $log->getRefOldShort()),
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
