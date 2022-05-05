<?php

final class DrydockLeaseWaitingForActivationLogType extends DrydockLogType {

  const LOGCONST = 'core.lease.waiting-for-activation';

  public function getLogTypeName() {
    return pht('Waiting For Activation');
  }

  public function getLogTypeIcon(array $data) {
    return 'fa-clock-o yellow';
  }

  public function renderLog(array $data) {
    $resource_phids = idx($data, 'resourcePHIDs', array());

    return pht(
      'Waiting for activation of resources: %s.',
      $this->renderHandleList($resource_phids));
  }

}
