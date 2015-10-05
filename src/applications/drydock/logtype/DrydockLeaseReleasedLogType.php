<?php

final class DrydockLeaseReleasedLogType extends DrydockLogType {

  const LOGCONST = 'core.lease.released';

  public function getLogTypeName() {
    return pht('Lease Released');
  }

  public function getLogTypeIcon(array $data) {
    return 'fa-link black';
  }

  public function renderLog(array $data) {
    return pht('Lease released.');
  }

}
