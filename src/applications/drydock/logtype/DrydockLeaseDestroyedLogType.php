<?php

final class DrydockLeaseDestroyedLogType extends DrydockLogType {

  const LOGCONST = 'core.lease.destroyed';

  public function getLogTypeName() {
    return pht('Lease Destroyed');
  }

  public function getLogTypeIcon(array $data) {
    return 'fa-link grey';
  }

  public function renderLog(array $data) {
    return pht('Lease destroyed.');
  }

}
