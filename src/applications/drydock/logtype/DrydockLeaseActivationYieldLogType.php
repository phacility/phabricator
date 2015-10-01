<?php

final class DrydockLeaseActivationYieldLogType extends DrydockLogType {

  const LOGCONST = 'core.lease.activation-yield';

  public function getLogTypeName() {
    return pht('Waiting for Activation');
  }

  public function getLogTypeIcon(array $data) {
    return 'fa-clock-o green';
  }

  public function renderLog(array $data) {
    $duration = idx($data, 'duration');

    return pht(
      'Waiting %s second(s) for lease to activate.',
      new PhutilNumber($duration));
  }

}
