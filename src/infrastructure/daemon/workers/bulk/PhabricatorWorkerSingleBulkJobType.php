<?php

/**
 * An bulk job which can not be parallelized and executes only one task.
 */
abstract class PhabricatorWorkerSingleBulkJobType
  extends PhabricatorWorkerBulkJobType {

  public function getDescriptionForConfirm(PhabricatorWorkerBulkJob $job) {
    return null;
  }

  public function getJobSize(PhabricatorWorkerBulkJob $job) {
    return 1;
  }

  public function createTasks(PhabricatorWorkerBulkJob $job) {
    $tasks = array();

    $tasks[] = PhabricatorWorkerBulkTask::initializeNewTask(
      $job,
      $job->getPHID());

    return $tasks;
  }

}
