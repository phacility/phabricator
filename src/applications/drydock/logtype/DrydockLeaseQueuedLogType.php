<?php

final class DrydockLeaseQueuedLogType extends DrydockLogType {

  const LOGCONST = 'core.lease.queued';

  public function getLogTypeName() {
    return pht('Lease Queued');
  }

  public function getLogTypeIcon(array $data) {
    return 'fa-link blue';
  }

  public function renderLog(array $data) {
    return pht('Lease queued for acquisition.');
  }

}
