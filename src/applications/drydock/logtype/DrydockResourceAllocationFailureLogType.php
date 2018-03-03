<?php

final class DrydockResourceAllocationFailureLogType extends DrydockLogType {

  const LOGCONST = 'core.resource.allocation-failure';

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
      'Blueprint failed to allocate a resource after claiming it would '.
      'be able to: [%s] %s',
      $class,
      $message);
  }

}
