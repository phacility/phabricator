<?php

abstract class PhabricatorWorkerBulkJobWorker
  extends PhabricatorWorker {

  final protected function acquireJobLock() {
    return PhabricatorGlobalLock::newLock('bulkjob.'.$this->getJobID())
      ->lock(15);
  }

  final protected function acquireTaskLock() {
    return PhabricatorGlobalLock::newLock('bulktask.'.$this->getTaskID())
      ->lock(15);
  }

  final protected function getJobID() {
    $data = $this->getTaskData();
    $id = idx($data, 'jobID');
    if (!$id) {
      throw new PhabricatorWorkerPermanentFailureException(
        pht('Worker has no job ID.'));
    }
    return $id;
  }

  final protected function getTaskID() {
    $data = $this->getTaskData();
    $id = idx($data, 'taskID');
    if (!$id) {
      throw new PhabricatorWorkerPermanentFailureException(
        pht('Worker has no task ID.'));
    }
    return $id;
  }

  final protected function loadJob() {
    $id = $this->getJobID();
    $job = id(new PhabricatorWorkerBulkJobQuery())
      ->setViewer(PhabricatorUser::getOmnipotentUser())
      ->withIDs(array($id))
      ->executeOne();
    if (!$job) {
      throw new PhabricatorWorkerPermanentFailureException(
        pht('Worker has invalid job ID ("%s").', $id));
    }
    return $job;
  }

  final protected function loadTask() {
    $id = $this->getTaskID();
    $task = id(new PhabricatorWorkerBulkTask())->load($id);
    if (!$task) {
      throw new PhabricatorWorkerPermanentFailureException(
        pht('Worker has invalid task ID ("%s").', $id));
    }
    return $task;
  }

  final protected function loadActor(PhabricatorWorkerBulkJob $job) {
    $actor_phid = $job->getAuthorPHID();
    $actor = id(new PhabricatorPeopleQuery())
      ->setViewer(PhabricatorUser::getOmnipotentUser())
      ->withPHIDs(array($actor_phid))
      ->executeOne();
    if (!$actor) {
      throw new PhabricatorWorkerPermanentFailureException(
        pht('Worker has invalid actor PHID ("%s").', $actor_phid));
    }

    $can_edit = PhabricatorPolicyFilter::hasCapability(
      $actor,
      $job,
      PhabricatorPolicyCapability::CAN_EDIT);

    if (!$can_edit) {
      throw new PhabricatorWorkerPermanentFailureException(
        pht('Job actor does not have permission to edit job.'));
    }

    return $actor;
  }

  final protected function updateJob(PhabricatorWorkerBulkJob $job) {
    $has_work = $this->hasRemainingWork($job);
    if ($has_work) {
      return;
    }

    $lock = $this->acquireJobLock();

    $job = $this->loadJob();
    if ($job->getStatus() == PhabricatorWorkerBulkJob::STATUS_RUNNING) {
      if (!$this->hasRemainingWork($job)) {
        $this->updateJobStatus(
          $job,
          PhabricatorWorkerBulkJob::STATUS_COMPLETE);
      }
    }

    $lock->unlock();
  }

  private function hasRemainingWork(PhabricatorWorkerBulkJob $job) {
    return (bool)queryfx_one(
      $job->establishConnection('r'),
      'SELECT * FROM %T WHERE bulkJobPHID = %s
        AND status NOT IN (%Ls) LIMIT 1',
      id(new PhabricatorWorkerBulkTask())->getTableName(),
      $job->getPHID(),
      array(
        PhabricatorWorkerBulkTask::STATUS_DONE,
        PhabricatorWorkerBulkTask::STATUS_FAIL,
      ));
  }

  protected function updateJobStatus(PhabricatorWorkerBulkJob $job, $status) {
    $type_status = PhabricatorWorkerBulkJobTransaction::TYPE_STATUS;

    $xactions = array();
    $xactions[] = id(new PhabricatorWorkerBulkJobTransaction())
      ->setTransactionType($type_status)
      ->setNewValue($status);

    $daemon_source = $this->newContentSource();

    $app_phid = id(new PhabricatorDaemonsApplication())->getPHID();

    $editor = id(new PhabricatorWorkerBulkJobEditor())
      ->setActor(PhabricatorUser::getOmnipotentUser())
      ->setActingAsPHID($app_phid)
      ->setContentSource($daemon_source)
      ->setContinueOnMissingFields(true)
      ->applyTransactions($job, $xactions);
  }

}
