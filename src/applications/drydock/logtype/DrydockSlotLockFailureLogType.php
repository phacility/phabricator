<?php

final class DrydockSlotLockFailureLogType extends DrydockLogType {

  const LOGCONST = 'core.resource.slot-lock.failure';

  public function getLogTypeName() {
    return pht('Slot Lock Failure');
  }

  public function getLogTypeIcon(array $data) {
    return 'fa-lock yellow';
  }

  public function renderLog(array $data) {
    $locks = idx($data, 'locks', array());
    if ($locks) {
      return pht(
        'Failed to acquire slot locks: %s.',
        implode(', ', array_keys($locks)));
    } else {
      return pht('Failed to acquire slot locks.');
    }
  }

}
