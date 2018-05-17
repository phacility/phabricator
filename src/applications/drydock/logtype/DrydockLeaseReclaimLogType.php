<?php

final class DrydockLeaseReclaimLogType extends DrydockLogType {

  const LOGCONST = 'core.lease.reclaim';

  public function getLogTypeName() {
    return pht('Reclaimed Resources');
  }

  public function getLogTypeIcon(array $data) {
    return 'fa-refresh yellow';
  }

  public function renderLog(array $data) {
    $resource_phids = idx($data, 'resourcePHIDs', array());

    return pht(
      'Reclaimed resource %s.',
      $this->renderHandleList($resource_phids));
  }

}
