<?php

final class DrydockLeaseAllocationFailureLogType extends DrydockLogType {

  const LOGCONST = 'core.lease.allocation-failure';

  public function getLogTypeName() {
    return pht('Allocation Failed');
  }

  public function getLogTypeIcon(array $data) {
    return 'fa-times red';
  }

  public function renderLog(array $data) {
    $class = idx($data, 'class');
    $message = idx($data, 'message');

    return pht(
      'One or more blueprints promised a new resource, but failed when '.
      'allocating: [%s] %s',
      $class,
      $message);
  }

}
