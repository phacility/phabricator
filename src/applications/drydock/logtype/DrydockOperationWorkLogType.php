<?php

final class DrydockOperationWorkLogType extends DrydockLogType {

  const LOGCONST = 'core.operation.work';

  public function getLogTypeName() {
    return pht('Started Work');
  }

  public function getLogTypeIcon(array $data) {
    return 'fa-check green';
  }

  public function renderLog(array $data) {
    return pht('Started this operation in a working copy.');
  }

}
