<?php

final class DrydockLeaseReacquireLogType extends DrydockLogType {

  const LOGCONST = 'core.lease.reacquire';

  public function getLogTypeName() {
    return pht('Reacquiring Resource');
  }

  public function getLogTypeIcon(array $data) {
    return 'fa-refresh yellow';
  }

  public function renderLog(array $data) {
    $class = idx($data, 'class');
    $message = idx($data, 'message');

    return pht(
      'Lease acquired a resource but failed to activate; acquisition '.
      'will be retried: [%s] %s',
      $class,
      $message);
  }

}
