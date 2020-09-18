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
  protected $isDismissed;

  private $repository = self::ATTACHABLE;
  private $object = self::ATTACHABLE;
  private $implementation = self::ATTACHABLE;
  private $workingCopyLease = self::ATTACHABLE;

  public static function initializeNewOperation(
    DrydockRepositoryOperationType $op) {

    return id(new DrydockRepositoryOperation())
      ->setOperationState(self::STATE_WAIT)
      ->setOperationType($op->getOperationConstant())
      ->setIsDismissed(0);
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
        'isDismissed' => 'bool',
      ),
      self::CONFIG_KEY_SCHEMA => array(
        'key_object' => array(
          'columns' => array('objectPHID'),
        ),
        'key_repository' => array(
          'columns' => array('repositoryPHID', 'operationState'),
        ),
        'key_state' => array(
          'columns' => array('operationState'),
        ),
        'key_author' => array(
          'columns' => array('authorPHID', 'operationState'),
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

  public function getWorkingCopyLease() {
    return $this->assertAttached($this->workingCopyLease);
  }

  public function attachWorkingCopyLease(DrydockLease $lease) {
    $this->workingCopyLease = $lease;
    return $this;
  }

  public function hasWorkingCopyLease() {
    return ($this->workingCopyLease !== self::ATTACHABLE);
  }

  public function getProperty($key, $default = null) {
    return idx($this->properties, $key, $default);
  }

  public function setProperty($key, $value) {
    $this->properties[$key] = $value;
    return $this;
  }

  public static function getOperationStateNameMap() {
    return array(
      self::STATE_WAIT => pht('Waiting'),
      self::STATE_WORK => pht('Working'),
      self::STATE_DONE => pht('Done'),
      self::STATE_FAIL => pht('Failed'),
    );
  }

  public static function getOperationStateIcon($state) {
    $map = array(
      self::STATE_WAIT => 'fa-clock-o',
      self::STATE_WORK => 'fa-plane ph-spin blue',
      self::STATE_DONE => 'fa-check green',
      self::STATE_FAIL => 'fa-times red',
    );

    return idx($map, $state, null);
  }

  public static function getOperationStateName($state) {
    $map = self::getOperationStateNameMap();
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
    $impl = $this->getImplementation();
    $impl->setInterface($interface);
    return $impl->applyOperation($this, $interface);
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

  public function isDone() {
    return ($this->getOperationState() === self::STATE_DONE);
  }

  public function getWorkingCopyMerges() {
    return $this->getImplementation()->getWorkingCopyMerges(
      $this);
  }

  public function setWorkingCopyLeasePHID($lease_phid) {
    return $this->setProperty('exec.leasePHID', $lease_phid);
  }

  public function getWorkingCopyLeasePHID() {
    return $this->getProperty('exec.leasePHID');
  }

  public function setCommandError(array $error) {
    return $this->setProperty('exec.workingcopy.error', $error);
  }

  public function getCommandError() {
    return $this->getProperty('exec.workingcopy.error');
  }

  public function logText($text) {
    return $this->logEvent(
      DrydockTextLogType::LOGCONST,
      array(
        'text' => $text,
      ));
  }

  public function logEvent($type, array $data = array()) {
    $log = id(new DrydockLog())
      ->setEpoch(PhabricatorTime::getNow())
      ->setType($type)
      ->setData($data);

    $log->setOperationPHID($this->getPHID());

    if ($this->hasWorkingCopyLease()) {
      $lease = $this->getWorkingCopyLease();
      $log->setLeasePHID($lease->getPHID());

      $resource_phid = $lease->getResourcePHID();
      if ($resource_phid) {
        $resource = $lease->getResource();

        $log->setResourcePHID($resource->getPHID());
        $log->setBlueprintPHID($resource->getBlueprintPHID());
      }
    }

    return $log->save();
  }


/* -(  PhabricatorPolicyInterface  )----------------------------------------- */


  public function getCapabilities() {
    return array(
      PhabricatorPolicyCapability::CAN_VIEW,
      PhabricatorPolicyCapability::CAN_EDIT,
    );
  }

  public function getPolicy($capability) {
    $need_capability = $this->getRequiredRepositoryCapability($capability);

    return $this->getRepository()
      ->getPolicy($need_capability);
  }

  public function hasAutomaticCapability($capability, PhabricatorUser $viewer) {
    $need_capability = $this->getRequiredRepositoryCapability($capability);

    return $this->getRepository()
      ->hasAutomaticCapability($need_capability, $viewer);
  }

  public function describeAutomaticCapability($capability) {
    return pht(
      'A repository operation inherits the policies of the repository it '.
      'affects.');
  }

  private function getRequiredRepositoryCapability($capability) {
    // To edit a RepositoryOperation, require that the user be able to push
    // to the repository.

    $map = array(
      PhabricatorPolicyCapability::CAN_EDIT =>
        DiffusionPushCapability::CAPABILITY,
    );

    return idx($map, $capability, $capability);
  }


}
