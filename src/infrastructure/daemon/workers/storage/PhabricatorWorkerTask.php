<?php

abstract class PhabricatorWorkerTask extends PhabricatorWorkerDAO {

  // NOTE: If you provide additional fields here, make sure they are handled
  // correctly in the archiving process.
  protected $taskClass;
  protected $leaseOwner;
  protected $leaseExpires;
  protected $failureCount;
  protected $dataID;

  private $data;
  private $executionException;

  public function setExecutionException(Exception $execution_exception) {
    $this->executionException = $execution_exception;
    return $this;
  }

  public function getExecutionException() {
    return $this->executionException;
  }

  public function setData($data) {
    $this->data = $data;
    return $this;
  }

  public function getData() {
    return $this->data;
  }

  public function isArchived() {
    return ($this instanceof PhabricatorWorkerArchiveTask);
  }

  public function getWorkerInstance() {
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
