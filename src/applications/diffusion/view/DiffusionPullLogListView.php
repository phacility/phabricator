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

    // Only administrators can view remote addresses.
    $remotes_visible = $viewer->getIsAdmin();

    $rows = array();
    foreach ($events as $event) {
      if ($event->getRepositoryPHID()) {
        $repository = $event->getRepository();
      } else {
        $repository = null;
      }

      if ($remotes_visible) {
        $remote_address = $event->getRemoteAddress();
      } else {
        $remote_address = null;
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
        ))
      ->setColumnVisibility(
        array(
          true,
          true,
          true,
          $remotes_visible,
        ));

    return $table;
  }

}
