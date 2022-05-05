<?php

final class DrydockLeaseWaitingForReclamationLogType extends DrydockLogType {

  const LOGCONST = 'core.lease.waiting-for-reclamation';

  public function getLogTypeName() {
    return pht('Waiting For Reclamation');
  }

  public function getLogTypeIcon(array $data) {
    return 'fa-clock-o yellow';
  }

  public function renderLog(array $data) {
    $resource_phids = idx($data, 'resourcePHIDs', array());

    return pht(
      'Waiting for reclamation of resources: %s.',
      $this->renderHandleList($resource_phids));
  }

}
