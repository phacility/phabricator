<?php

final class DrydockLeaseAcquiredLogType extends DrydockLogType {

  const LOGCONST = 'core.lease.acquired';

  public function getLogTypeName() {
    return pht('Lease Acquired');
  }

  public function getLogTypeIcon(array $data) {
    return 'fa-link yellow';
  }

  public function renderLog(array $data) {
    return pht('Lease acquired.');
  }

}
