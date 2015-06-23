<?php

final class PhabricatorWorkerBulkJobTaskWorker
  extends PhabricatorWorkerBulkJobWorker {

  protected function doWork() {
    $lock = $this->acquireTaskLock();

    $task = $this->loadTask();
    $status = $task->getStatus();
    switch ($task->getStatus()) {
      case PhabricatorWorkerBulkTask::STATUS_WAITING:
        // This is what we expect.
        break;
      default:
        throw new PhabricatorWorkerPermanentFailureException(
          pht(
            'Found unexpected task status ("%s").',
            $status));
    }

    $task
      ->setStatus(PhabricatorWorkerBulkTask::STATUS_RUNNING)
      ->save();

    $lock->unlock();

    $job = $this->loadJob();
    $actor = $this->loadActor($job);

    try {
      $job->runTask($actor, $task);
      $status = PhabricatorWorkerBulkTask::STATUS_DONE;
    } catch (Exception $ex) {
      phlog($ex);
      $status = PhabricatorWorkerBulkTask::STATUS_FAIL;
    }

    $task
      ->setStatus($status)
      ->save();

    $this->updateJob($job);
  }

}
