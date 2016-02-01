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

    $types = DrydockLogType::getAllLogTypes();

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
        $type_key = $log->getType();
        if (isset($types[$type_key])) {
          $type_object = id(clone $types[$type_key])
            ->setLog($log)
            ->setViewer($viewer);

          $log_data = $log->getData();

          $type = $type_object->getLogTypeName();
          $icon = $type_object->getLogTypeIcon($log_data);
          $data = $type_object->renderLog($log_data);
        } else {
          $type = pht('<Unknown: %s>', $type_key);
          $data = null;
          $icon = 'fa-question-circle red';
        }
      } else {
        $type = phutil_tag('em', array(), pht('Restricted'));
        $data = phutil_tag(
          'em',
          array(),
          pht('You do not have permission to view this log event.'));
        $icon = 'fa-lock grey';
      }

      $rows[] = array(
        $blueprint,
        $resource,
        $lease,
        id(new PHUIIconView())->setIcon($icon),
        $type,
        $data,
        phabricator_datetime($log->getEpoch(), $viewer),
      );
    }

    $table = id(new AphrontTableView($rows))
      ->setDeviceReadyTable(true)
      ->setHeaders(
        array(
          pht('Blueprint'),
          pht('Resource'),
          pht('Lease'),
          null,
          pht('Type'),
          pht('Data'),
          pht('Date'),
        ))
      ->setColumnClasses(
        array(
          '',
          '',
          '',
          'icon',
          '',
          'wide',
          '',
        ));

    return $table;
  }

}
