<?php

final class DrydockResourceActivationYieldLogType extends DrydockLogType {

  const LOGCONST = 'core.resource.activation-yield';

  public function getLogTypeName() {
    return pht('Waiting for Activation');
  }

  public function getLogTypeIcon(array $data) {
    return 'fa-clock-o green';
  }

  public function renderLog(array $data) {
    $duration = idx($data, 'duration');

    return pht(
      'Waiting %s second(s) for resource to activate.',
      new PhutilNumber($duration));
  }

}
