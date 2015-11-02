<?php

final class DrydockLeaseActivationFailureLogType extends DrydockLogType {

  const LOGCONST = 'core.lease.activation-failure';

  public function getLogTypeName() {
    return pht('Activation Failed');
  }

  public function getLogTypeIcon(array $data) {
    return 'fa-times red';
  }

  public function renderLog(array $data) {
    $class = idx($data, 'class');
    $message = idx($data, 'message');

    return pht('Lease activation failed: [%s] %s', $class, $message);
  }

}
