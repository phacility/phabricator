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
  protected $objectPHID;
  protected $containerPHID;

  private $data;
  private $executionException;

  protected function getConfiguration() {
    return array(
      self::CONFIG_COLUMN_SCHEMA => array(
        'taskClass' => 'text64',
        'leaseOwner' => 'text64?',
        'leaseExpires' => 'epoch?',
        'failureCount' => 'uint32',
        'failureTime' => 'epoch?',
        'priority' => 'uint32',
        'objectPHID' => 'phid?',
        'containerPHID' => 'phid?',
      ),
      self::CONFIG_KEY_SCHEMA => array(
        'key_object' => array(
          'columns' => array('objectPHID'),
        ),
        'key_container' => array(
          'columns' => array('containerPHID'),
        ),
      ),
    ) + parent::getConfiguration();
  }

  final public function setExecutionException($execution_exception) {
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

    try {
      // NOTE: If the class does not exist, the autoloader will throw an
      // exception.
      class_exists($class);
    } catch (PhutilMissingSymbolException $ex) {
      throw new PhabricatorWorkerPermanentFailureException(
        pht(
          "Task class '%s' does not exist!",
          $class));
    }

    if (!is_subclass_of($class, 'PhabricatorWorker')) {
      throw new PhabricatorWorkerPermanentFailureException(
        pht(
          "Task class '%s' does not extend %s.",
          $class,
          'PhabricatorWorker'));
    }

    return newv($class, array($this->getData()));
  }

}
