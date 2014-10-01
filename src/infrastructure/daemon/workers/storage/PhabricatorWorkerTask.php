<?php

abstract class PhabricatorWorkerTask extends PhabricatorWorkerDAO {

  // NOTE: If you provide additional fields here, make sure they are handled
  // correctly in the archiving process.
  protected $taskClass;
  protected $leaseOwner;
  protected $leaseExpires;
  protected $failureCount;
  protected $dataID;
  protected $priority;

  private $data;
  private $executionException;

  public function getConfiguration() {
    return array(
      self::CONFIG_COLUMN_SCHEMA => array(
        'taskClass' => 'text64',
        'leaseOwner' => 'text64?',
        'leaseExpires' => 'epoch?',
        'failureCount' => 'uint32',
        'failureTime' => 'epoch?',
        'priority' => 'uint32',
      ),
    ) + parent::getConfiguration();
  }

  final public function setExecutionException(Exception $execution_exception) {
    $this->executionException = $execution_exception;
    return $this;
  }

  final public function getExecutionException() {
    return $this->executionException;
  }

  final public function setData($data) {
    $this->data = $data;
    return $this;
  }

  final public function getData() {
    return $this->data;
  }

  final public function isArchived() {
    return ($this instanceof PhabricatorWorkerArchiveTask);
  }

  final public function getWorkerInstance() {
    $id = $this->getID();
    $class = $this->getTaskClass();

    if (!class_exists($class)) {
      throw new PhabricatorWorkerPermanentFailureException(
        "Task class '{$class}' does not exist!");
    }

    if (!is_subclass_of($class, 'PhabricatorWorker')) {
      throw new PhabricatorWorkerPermanentFailureException(
        "Task class '{$class}' does not extend PhabricatorWorker.");
    }

    return newv($class, array($this->getData()));
  }

}
