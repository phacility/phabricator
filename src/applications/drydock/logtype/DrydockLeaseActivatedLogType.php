<?php

final class DrydockLeaseActivatedLogType extends DrydockLogType {

  const LOGCONST = 'core.lease.activated';

  public function getLogTypeName() {
    return pht('Lease Activated');
  }

  public function getLogTypeIcon(array $data) {
    return 'fa-link green';
  }

  public function renderLog(array $data) {
    return pht('Lease activated.');
  }

}
