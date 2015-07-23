<?php

final class PhabricatorWorkerBulkTask
  extends PhabricatorWorkerDAO {

  const STATUS_WAITING = 'waiting';
  const STATUS_RUNNING = 'running';
  const STATUS_DONE = 'done';
  const STATUS_FAIL = 'fail';

  protected $bulkJobPHID;
  protected $objectPHID;
  protected $status;
  protected $data = array();

  protected function getConfiguration() {
    return array(
      self::CONFIG_TIMESTAMPS => false,
      self::CONFIG_SERIALIZATION => array(
        'data' => self::SERIALIZATION_JSON,
      ),
      self::CONFIG_COLUMN_SCHEMA => array(
        'status' => 'text32',
      ),
      self::CONFIG_KEY_SCHEMA => array(
        'key_job' => array(
          'columns' => array('bulkJobPHID', 'status'),
        ),
        'key_object' => array(
          'columns' => array('objectPHID'),
        ),
      ),
    ) + parent::getConfiguration();
  }

  public static function initializeNewTask(
    PhabricatorWorkerBulkJob $job,
    $object_phid) {

    return id(new PhabricatorWorkerBulkTask())
      ->setBulkJobPHID($job->getPHID())
      ->setStatus(self::STATUS_WAITING)
      ->setObjectPHID($object_phid);
  }

}
