<?php

final class DiffusionSyncLogListView extends AphrontView {

  private $logs;

  public function setLogs(array $logs) {
    assert_instances_of($logs, 'PhabricatorRepositorySyncEvent');
    $this->logs = $logs;
    return $this;
  }

  public function render() {
    $events = $this->logs;
    $viewer = $this->getViewer();

    $rows = array();
    foreach ($events as $event) {
      $repository = $event->getRepository();
      $repository_link = phutil_tag(
        'a',
        array(
          'href' => $repository->getURI(),
        ),
        $repository->getDisplayName());

      $event_id = $event->getID();

      $sync_wait = pht('%sus', new PhutilNumber($event->getSyncWait()));

      $device_link = $viewer->renderHandle($event->getDevicePHID());
      $from_device_link = $viewer->renderHandle($event->getFromDevicePHID());

      $rows[] = array(
        $event_id,
        $repository_link,
        $device_link,
        $from_device_link,
        $event->getDeviceVersion(),
        $event->getFromDeviceVersion(),
        $event->getResultType(),
        $event->getResultCode(),
        phabricator_datetime($event->getEpoch(), $viewer),
        $sync_wait,
      );
    }

    $table = id(new AphrontTableView($rows))
      ->setHeaders(
        array(
          pht('Sync'),
          pht('Repository'),
          pht('Device'),
          pht('From Device'),
          pht('Version'),
          pht('From Version'),
          pht('Result'),
          pht('Code'),
          pht('Date'),
          pht('Sync Wait'),
        ))
      ->setColumnClasses(
        array(
          'n',
          '',
          '',
          '',
          'n',
          'n',
          'wide right',
          'n',
          'right',
          'n right',
        ));

    return $table;
  }

}
