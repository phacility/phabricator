<?php

abstract class PhabricatorWorkerBulkJobType extends Phobject {

  abstract public function getJobName(PhabricatorWorkerBulkJob $job);
  abstract public function getBulkJobTypeKey();
  abstract public function getJobSize(PhabricatorWorkerBulkJob $job);
  abstract public function getDescriptionForConfirm(
    PhabricatorWorkerBulkJob $job);

  abstract public function createTasks(PhabricatorWorkerBulkJob $job);
  abstract public function runTask(
    PhabricatorUser $actor,
    PhabricatorWorkerBulkJob $job,
    PhabricatorWorkerBulkTask $task);

  public function getDoneURI(PhabricatorWorkerBulkJob $job) {
    return $job->getManageURI();
  }

  final public static function getAllJobTypes() {
    return id(new PhutilClassMapQuery())
      ->setAncestorClass(__CLASS__)
      ->setUniqueMethod('getBulkJobTypeKey')
      ->execute();
  }

  public function getCurtainActions(
    PhabricatorUser $viewer,
    PhabricatorWorkerBulkJob $job) {

    if ($job->isConfirming()) {
      $continue_uri = $job->getMonitorURI();
    } else {
      $continue_uri = $job->getDoneURI();
    }

    $continue = id(new PhabricatorActionView())
      ->setHref($continue_uri)
      ->setIcon('fa-arrow-circle-o-right')
      ->setName(pht('Continue'));

    return array(
      $continue,
    );
  }

}
