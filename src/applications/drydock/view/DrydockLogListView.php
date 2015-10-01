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
      $blueprint_phid = $log->getBlueprintPHID();
      if ($blueprint_phid) {
        $blueprint = $viewer->renderHandle($blueprint_phid);
      } else {
        $blueprint = null;
      }

      $resource_phid = $log->getResourcePHID();
      if ($resource_phid) {
        $resource = $viewer->renderHandle($resource_phid);
      } else {
        $resource = null;
      }

      $lease_phid = $log->getLeasePHID();
      if ($lease_phid) {
        $lease = $viewer->renderHandle($lease_phid);
      } else {
        $lease = null;
      }

      if ($log->isComplete()) {
        // TODO: This is a placeholder.
        $type = $log->getType();
        $data = print_r($log->getData(), true);
      } else {
        $type = phutil_tag('em', array(), pht('Restricted'));
        $data = phutil_tag(
          'em',
          array(),
          pht('You do not have permission to view this log event.'));
      }

      $rows[] = array(
        $blueprint,
        $resource,
        $lease,
        $type,
        $data,
        phabricator_datetime($log->getEpoch(), $viewer),
      );
    }

    $table = new AphrontTableView($rows);
    $table->setDeviceReadyTable(true);
    $table->setHeaders(
      array(
        pht('Blueprint'),
        pht('Resource'),
        pht('Lease'),
        pht('Type'),
        pht('Data'),
        pht('Date'),
      ));
    $table->setColumnClasses(
      array(
        '',
        '',
        '',
        '',
        'wide',
        '',
      ));

    return $table;
  }

}
