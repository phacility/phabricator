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
        throw new Exception(
          "Temporary failure!");
      case 'fail-permanent':
        throw new PhabricatorWorkerPermanentFailureException(
          "Permanent failure!");
      default:
        return;
    }
  }

}
