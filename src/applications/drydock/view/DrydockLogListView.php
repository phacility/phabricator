<?php

final class DrydockLogListView extends AphrontView {

  private $logs;

  public function setLogs(array $logs) {
    assert_instances_of($logs, 'DrydockLog');
    $this->logs = $logs;
    return $this;
  }

  public function render() {
    $logs = $this->logs;
    $viewer = $this->getUser();

    $view = new PHUIObjectItemListView();

    $rows = array();
    foreach ($logs as $log) {
      $resource_uri = '/drydock/resource/'.$log->getResourceID().'/';
      $lease_uri = '/drydock/lease/'.$log->getLeaseID().'/';

      $rows[] = array(
        phutil_tag(
          'a',
          array(
            'href' => $resource_uri,
          ),
          $log->getResourceID()),
        phutil_tag(
          'a',
          array(
            'href' => $lease_uri,
          ),
          $log->getLeaseID()),
        $log->getMessage(),
        phabricator_date($log->getEpoch(), $viewer),
      );
    }

    $table = new AphrontTableView($rows);
    $table->setDeviceReadyTable(true);
    $table->setHeaders(
      array(
        pht('Resource'),
        pht('Lease'),
        pht('Message'),
        pht('Date'),
      ));
    $table->setShortHeaders(
      array(
        'R',
        'L',
        pht('Message'),
        '',
      ));
    $table->setColumnClasses(
      array(
        '',
        '',
        'wide',
        '',
      ));

    return $table;
  }

}
