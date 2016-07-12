<?php

final class PhabricatorWorkerBulkJobCreateWorker
  extends PhabricatorWorkerBulkJobWorker {

  protected function doWork() {
    $lock = $this->acquireJobLock();

    $job = $this->loadJob();
    $actor = $this->loadActor($job);

    $status = $job->getStatus();
    switch ($status) {
      case PhabricatorWorkerBulkJob::STATUS_WAITING:
        // This is what we expect. Other statuses indicate some kind of race
        // is afoot.
        break;
      default:
        throw new PhabricatorWorkerPermanentFailureException(
          pht(
            'Found unexpected job status ("%s").',
            $status));
    }

    $tasks = $job->createTasks();
    foreach ($tasks as $task) {
      $task->save();
    }

    $this->updateJobStatus(
      $job,
      PhabricatorWorkerBulkJob::STATUS_RUNNING);

    $lock->unlock();

    foreach ($tasks as $task) {
      PhabricatorWorker::scheduleTask(
        'PhabricatorWorkerBulkJobTaskWorker',
        array(
          'jobID' => $job->getID(),
          'taskID' => $task->getID(),
        ),
        array(
          'priority' => PhabricatorWorker::PRIORITY_BULK,
        ));
    }

    $this->updateJob($job);
  }

}
