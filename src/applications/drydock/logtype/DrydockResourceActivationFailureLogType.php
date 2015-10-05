<?php

final class DrydockResourceActivationFailureLogType extends DrydockLogType {

  const LOGCONST = 'core.resource.activation-failure';

  public function getLogTypeName() {
    return pht('Activation Failed');
  }

  public function getLogTypeIcon(array $data) {
    return 'fa-times red';
  }

  public function renderLog(array $data) {
    $class = idx($data, 'class');
    $message = idx($data, 'message');

    return pht('Resource activation failed: [%s] %s', $class, $message);
  }

}
