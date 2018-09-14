<?php

final class DrydockLogListView extends AphrontView {

  private $logs;
  private $hideBlueprints;
  private $hideResources;
  private $hideLeases;
  private $hideOperations;

  public function setHideBlueprints($hide_blueprints) {
    $this->hideBlueprints = $hide_blueprints;
    return $this;
  }

  public function getHideBlueprints() {
    return $this->hideBlueprints;
  }

  public function setHideResources($hide_resources) {
    $this->hideResources = $hide_resources;
    return $this;
  }

  public function getHideResources() {
    return $this->hideResources;
  }

  public function setHideLeases($hide_leases) {
    $this->hideLeases = $hide_leases;
    return $this;
  }

  public function getHideLeases() {
    return $this->hideLeases;
  }

  public function setHideOperations($hide_operations) {
    $this->hideOperations = $hide_operations;
    return $this;
  }

  public function getHideOperations() {
    return $this->hideOperations;
  }

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

      $operation_phid = $log->getOperationPHID();
      if ($operation_phid) {
        $operation = $viewer->renderHandle($operation_phid);
      } else {
        $operation = null;
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
          $data = $type_object->renderLogForHTML($log_data);
          $data = phutil_escape_html_newlines($data);
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
        $operation,
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
          pht('Operation'),
          null,
          pht('Type'),
          pht('Data'),
          pht('Date'),
        ))
      ->setColumnVisibility(
        array(
          !$this->getHideBlueprints(),
          !$this->getHideResources(),
          !$this->getHideLeases(),
          !$this->getHideOperations(),
        ))
      ->setColumnClasses(
        array(
          '',
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
