<?php

final class DrydockLeaseWaitingForResourcesLogType extends DrydockLogType {

  const LOGCONST = 'core.lease.waiting-for-resources';

  public function getLogTypeName() {
    return pht('Waiting For Resource');
  }

  public function getLogTypeIcon(array $data) {
    return 'fa-clock-o yellow';
  }

  public function renderLog(array $data) {
    $viewer = $this->getViewer();

    $blueprint_phids = idx($data, 'blueprintPHIDs', array());

    return pht(
      'Waiting for available resources from: %s.',
      $viewer->renderHandleList($blueprint_phids)->render());
  }

}
