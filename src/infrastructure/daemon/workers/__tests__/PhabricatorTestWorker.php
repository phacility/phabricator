<?php

final class PhabricatorTestWorker extends PhabricatorWorker {

  public function getRequiredLeaseTime() {
    return idx(
      $this->getTaskData(),
      'getRequiredLeaseTime',
      parent::getRequiredLeaseTime());
  }

  public function getMaximumRetryCount() {
    return idx(
      $this->getTaskData(),
      'getMaximumRetryCount',
      parent::getMaximumRetryCount());
  }

  public function getWaitBeforeRetry(PhabricatorWorkerTask $task) {
    return idx(
      $this->getTaskData(),
      'getWaitBeforeRetry',
      parent::getWaitBeforeRetry($task));
  }

  protected function doWork() {
    switch (idx($this->getTaskData(), 'doWork')) {
      case 'fail-temporary':
        throw new Exception(pht('Temporary failure!'));
      case 'fail-permanent':
        throw new PhabricatorWorkerPermanentFailureException(
          pht('Permanent failure!'));
      default:
        return;
    }
  }

}
