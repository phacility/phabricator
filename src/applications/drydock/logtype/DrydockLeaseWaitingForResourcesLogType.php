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
    $blueprint_phids = idx($data, 'blueprintPHIDs', array());

    return pht(
      'Waiting for available resources from: %s.',
      $this->renderHandleList($blueprint_phids));
  }

}
