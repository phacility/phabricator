<?php

final class PhabricatorWorkerArchiveTask extends PhabricatorWorkerTask {

  const RESULT_SUCCESS    = 0;
  const RESULT_FAILURE    = 1;
  const RESULT_CANCELLED  = 2;

  protected $duration;
  protected $result;

  protected function getConfiguration() {
    $parent = parent::getConfiguration();

    $config = array(
      // We manage the IDs in this table; they are allocated in the ActiveTask
      // table and moved here without alteration.
      self::CONFIG_IDS => self::IDS_MANUAL,
    ) + $parent;


    $config[self::CONFIG_COLUMN_SCHEMA] = array(
      'result' => 'uint32',
      'duration' => 'uint64',
    ) + $config[self::CONFIG_COLUMN_SCHEMA];

    $config[self::CONFIG_KEY_SCHEMA] = array(
      'dateCreated' => array(
        'columns' => array('dateCreated'),
      ),
      'leaseOwner' => array(
        'columns' => array('leaseOwner', 'priority', 'id'),
      ),
    ) + $parent[self::CONFIG_KEY_SCHEMA];

    return $config;
  }

  public function save() {
    if ($this->getID() === null) {
      throw new Exception(pht('Trying to archive a task with no ID.'));
    }

    $other = new PhabricatorWorkerActiveTask();
    $conn_w = $this->establishConnection('w');

    $this->openTransaction();
      queryfx(
        $conn_w,
        'DELETE FROM %T WHERE id = %d',
        $other->getTableName(),
        $this->getID());
      $result = parent::insert();
    $this->saveTransaction();

    return $result;
  }

  public function delete() {
    $this->openTransaction();
      if ($this->getDataID()) {
        $conn_w = $this->establishConnection('w');
        $data_table = new PhabricatorWorkerTaskData();

        queryfx(
          $conn_w,
          'DELETE FROM %T WHERE id = %d',
          $data_table->getTableName(),
          $this->getDataID());
      }

      $result = parent::delete();
    $this->saveTransaction();
    return $result;
  }

  public function unarchiveTask() {
    $this->openTransaction();
      $active = id(new PhabricatorWorkerActiveTask())
        ->setID($this->getID())
        ->setTaskClass($this->getTaskClass())
        ->setLeaseOwner(null)
        ->setLeaseExpires(0)
        ->setFailureCount(0)
        ->setDataID($this->getDataID())
        ->setPriority($this->getPriority())
        ->setObjectPHID($this->getObjectPHID())
        ->insert();

      $this->setDataID(null);
      $this->delete();
    $this->saveTransaction();

    return $active;
  }

}
