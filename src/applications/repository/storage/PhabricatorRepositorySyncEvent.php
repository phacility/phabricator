<?php

final class PhabricatorRepositorySyncEvent
  extends PhabricatorRepositoryDAO
  implements PhabricatorPolicyInterface {

  protected $repositoryPHID;
  protected $epoch;
  protected $devicePHID;
  protected $fromDevicePHID;
  protected $deviceVersion;
  protected $fromDeviceVersion;
  protected $resultType;
  protected $resultCode;
  protected $syncWait;
  protected $properties = array();

  private $repository = self::ATTACHABLE;

  const RESULT_SYNC = 'sync';
  const RESULT_ERROR = 'error';
  const RESULT_TIMEOUT = 'timeout';
  const RESULT_EXCEPTION = 'exception';

  public static function initializeNewEvent() {
    return new self();
  }

  protected function getConfiguration() {
    return array(
      self::CONFIG_AUX_PHID => true,
      self::CONFIG_TIMESTAMPS => false,
      self::CONFIG_SERIALIZATION => array(
        'properties' => self::SERIALIZATION_JSON,
      ),
      self::CONFIG_COLUMN_SCHEMA => array(
        'deviceVersion' => 'uint32?',
        'fromDeviceVersion' => 'uint32?',
        'resultType' => 'text32',
        'resultCode' => 'uint32',
        'syncWait' => 'uint64',
      ),
      self::CONFIG_KEY_SCHEMA => array(
        'key_repository' => array(
          'columns' => array('repositoryPHID'),
        ),
        'key_epoch' => array(
          'columns' => array('epoch'),
        ),
      ),
    ) + parent::getConfiguration();
  }

  public function getPHIDType() {
    return PhabricatorRepositorySyncEventPHIDType::TYPECONST;
  }

  public function attachRepository(PhabricatorRepository $repository) {
    $this->repository = $repository;
    return $this;
  }

  public function getRepository() {
    return $this->assertAttached($this->repository);
  }

  public function setProperty($key, $value) {
    $this->properties[$key] = $value;
    return $this;
  }

  public function getProperty($key, $default = null) {
    return idx($this->properties, $key, $default);
  }

/* -(  PhabricatorPolicyInterface  )----------------------------------------- */


  public function getCapabilities() {
    return array(
      PhabricatorPolicyCapability::CAN_VIEW,
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
      "A repository's sync events are visible to users who can see the ".
      "repository.");
  }

}
