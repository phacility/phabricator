<?php

/**
 * Represents a request to perform a repository operation like a merge or
 * cherry-pick.
 */
final class DrydockRepositoryOperation extends DrydockDAO
  implements
    PhabricatorPolicyInterface {

  const STATE_WAIT = 'wait';
  const STATE_WORK = 'work';
  const STATE_DONE = 'done';
  const STATE_FAIL = 'fail';

  protected $authorPHID;
  protected $objectPHID;
  protected $repositoryPHID;
  protected $repositoryTarget;
  protected $operationType;
  protected $operationState;
  protected $properties = array();

  private $repository = self::ATTACHABLE;
  private $object = self::ATTACHABLE;
  private $implementation = self::ATTACHABLE;

  public static function initializeNewOperation(
    DrydockRepositoryOperationType $op) {

    return id(new DrydockRepositoryOperation())
      ->setOperationState(self::STATE_WAIT)
      ->setOperationType($op->getOperationConstant());
  }

  protected function getConfiguration() {
    return array(
      self::CONFIG_AUX_PHID => true,
      self::CONFIG_SERIALIZATION => array(
        'properties' => self::SERIALIZATION_JSON,
      ),
      self::CONFIG_COLUMN_SCHEMA => array(
        'repositoryTarget' => 'bytes',
        'operationType' => 'text32',
        'operationState' => 'text32',
      ),
      self::CONFIG_KEY_SCHEMA => array(
        'key_object' => array(
          'columns' => array('objectPHID'),
        ),
        'key_repository' => array(
          'columns' => array('repositoryPHID', 'operationState'),
        ),
      ),
    ) + parent::getConfiguration();
  }

  public function generatePHID() {
    return PhabricatorPHID::generateNewPHID(
      DrydockRepositoryOperationPHIDType::TYPECONST);
  }

  public function attachRepository(PhabricatorRepository $repository) {
    $this->repository = $repository;
    return $this;
  }

  public function getRepository() {
    return $this->assertAttached($this->repository);
  }

  public function attachObject($object) {
    $this->object = $object;
    return $this;
  }

  public function getObject() {
    return $this->assertAttached($this->object);
  }

  public function attachImplementation(DrydockRepositoryOperationType $impl) {
    $this->implementation = $impl;
    return $this;
  }

  public function getImplementation() {
    return $this->implementation;
  }

  public function getProperty($key, $default = null) {
    return idx($this->properties, $key, $default);
  }

  public function setProperty($key, $value) {
    $this->properties[$key] = $value;
    return $this;
  }

  public static function getOperationStateIcon($state) {
    $map = array(
      self::STATE_WAIT => 'fa-clock-o',
      self::STATE_WORK => 'fa-refresh blue',
      self::STATE_DONE => 'fa-check green',
      self::STATE_FAIL => 'fa-times red',
    );

    return idx($map, $state, null);
  }

  public static function getOperationStateName($state) {
    $map = array(
      self::STATE_WAIT => pht('Waiting'),
      self::STATE_WORK => pht('Working'),
      self::STATE_DONE => pht('Done'),
      self::STATE_FAIL => pht('Failed'),
    );

    return idx($map, $state, pht('<Unknown: %s>', $state));
  }

  public function scheduleUpdate() {
    PhabricatorWorker::scheduleTask(
      'DrydockRepositoryOperationUpdateWorker',
      array(
        'operationPHID' => $this->getPHID(),
      ),
      array(
        'objectPHID' => $this->getPHID(),
        'priority' => PhabricatorWorker::PRIORITY_ALERTS,
      ));
  }

  public function applyOperation(DrydockInterface $interface) {
    return $this->getImplementation()->applyOperation(
      $this,
      $interface);
  }

  public function getOperationDescription(PhabricatorUser $viewer) {
    return $this->getImplementation()->getOperationDescription(
      $this,
      $viewer);
  }

  public function getOperationCurrentStatus(PhabricatorUser $viewer) {
    return $this->getImplementation()->getOperationCurrentStatus(
      $this,
      $viewer);
  }

  public function isUnderway() {
    switch ($this->getOperationState()) {
      case self::STATE_WAIT:
      case self::STATE_WORK:
        return true;
    }

    return false;
  }


/* -(  PhabricatorPolicyInterface  )----------------------------------------- */


  public function getCapabilities() {
    return array(
      PhabricatorPolicyCapability::CAN_VIEW,
      PhabricatorPolicyCapability::CAN_EDIT,
    );
  }

  public function getPolicy($capability) {
    return $this->getRepository()->getPolicy($capability);
  }

  public function hasAutomaticCapability($capability, PhabricatorUser $viewer) {
    return $this->getRepository()->hasAutomaticCapability($capability, $viewer);
  }

  public function describeAutomaticCapability($capability) {
    return pht(
      'A repository operation inherits the policies of the repository it '.
      'affects.');
  }

}
