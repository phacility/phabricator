<?php

final class PhabricatorEditEngineBulkJobType
   extends PhabricatorWorkerBulkJobType {

  public function getBulkJobTypeKey() {
    return 'transaction.edit';
  }

  public function getJobName(PhabricatorWorkerBulkJob $job) {
    return pht('Bulk Edit');
  }

  public function getDescriptionForConfirm(PhabricatorWorkerBulkJob $job) {
    return pht(
      'You are about to apply a bulk edit which will affect '.
      '%s object(s).',
      new PhutilNumber($job->getSize()));
  }

  public function getJobSize(PhabricatorWorkerBulkJob $job) {
    return count($job->getParameter('objectPHIDs', array()));
  }

  public function getDoneURI(PhabricatorWorkerBulkJob $job) {
    return $job->getParameter('doneURI');
  }

  public function createTasks(PhabricatorWorkerBulkJob $job) {
    $tasks = array();

    foreach ($job->getParameter('objectPHIDs', array()) as $phid) {
      $tasks[] = PhabricatorWorkerBulkTask::initializeNewTask($job, $phid);
    }

    return $tasks;
  }

  public function runTask(
    PhabricatorUser $actor,
    PhabricatorWorkerBulkJob $job,
    PhabricatorWorkerBulkTask $task) {

    $object = id(new PhabricatorObjectQuery())
      ->setViewer($actor)
      ->withPHIDs(array($task->getObjectPHID()))
      ->requireCapabilities(
        array(
          PhabricatorPolicyCapability::CAN_VIEW,
          PhabricatorPolicyCapability::CAN_EDIT,
        ))
      ->executeOne();
    if (!$object) {
      return;
    }

    $raw_xactions = $job->getParameter('xactions');
    $xactions = $this->buildTransactions($object, $raw_xactions);

    $editor = $object->getApplicationTransactionEditor()
      ->setActor($actor)
      ->setContentSource($job->newContentSource())
      ->setContinueOnNoEffect(true)
      ->setContinueOnMissingFields(true)
      ->applyTransactions($object, $xactions);
  }

  private function buildTransactions($object, array $raw_xactions) {
    $xactions = array();

    foreach ($raw_xactions as $raw_xaction) {
      $xaction = $object->getApplicationTransactionTemplate()
        ->setTransactionType($raw_xaction['type'])
        ->setNewValue($raw_xaction['value']);

      $xactions[] = $xaction;
    }

    return $xactions;
  }

}
