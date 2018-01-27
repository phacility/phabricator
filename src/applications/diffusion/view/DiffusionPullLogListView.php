<?php

final class DiffusionPullLogListView extends AphrontView {

  private $logs;

  public function setLogs(array $logs) {
    assert_instances_of($logs, 'PhabricatorRepositoryPullEvent');
    $this->logs = $logs;
    return $this;
  }

  public function render() {
    $events = $this->logs;
    $viewer = $this->getViewer();

    $handle_phids = array();
    foreach ($events as $event) {
      if ($event->getPullerPHID()) {
        $handle_phids[] = $event->getPullerPHID();
      }
    }
    $handles = $viewer->loadHandles($handle_phids);

    // Figure out which repositories are editable. We only let you see remote
    // IPs if you have edit capability on a repository.
    $editable_repos = array();
    if ($events) {
      $editable_repos = id(new PhabricatorRepositoryQuery())
        ->setViewer($viewer)
        ->requireCapabilities(
          array(
            PhabricatorPolicyCapability::CAN_VIEW,
            PhabricatorPolicyCapability::CAN_EDIT,
          ))
        ->withPHIDs(mpull($events, 'getRepositoryPHID'))
        ->execute();
      $editable_repos = mpull($editable_repos, null, 'getPHID');
    }

    $rows = array();
    $any_host = false;
    foreach ($events as $event) {
      if ($event->getRepositoryPHID()) {
        $repository = $event->getRepository();
      } else {
        $repository = null;
      }

      // Reveal this if it's valid and the user can edit the repository. For
      // invalid requests you currently have to go fishing in the database.
      $remote_address = '-';
      if ($repository) {
        if (isset($editable_repos[$event->getRepositoryPHID()])) {
          $remote_address = $event->getRemoteAddress();
        }
      }

      $event_id = $event->getID();

      $repository_link = null;
      if ($repository) {
        $repository_link = phutil_tag(
          'a',
          array(
            'href' => $repository->getURI(),
          ),
          $repository->getDisplayName());
      }

      $puller_link = null;
      if ($event->getPullerPHID()) {
        $puller_link = $viewer->renderHandle($event->getPullerPHID());
      }

      $rows[] = array(
        $event_id,
        $repository_link,
        $puller_link,
        $remote_address,
        $event->getRemoteProtocolDisplayName(),
        $event->newResultIcon(),
        $event->getResultCode(),
        phabricator_datetime($event->getEpoch(), $viewer),
      );
    }

    $table = id(new AphrontTableView($rows))
      ->setHeaders(
        array(
          pht('Pull'),
          pht('Repository'),
          pht('Puller'),
          pht('From'),
          pht('Via'),
          null,
          pht('Code'),
          pht('Date'),
        ))
      ->setColumnClasses(
        array(
          'n',
          '',
          '',
          'n',
          'wide',
          '',
          'n',
          'right',
        ));

    return $table;
  }

}
