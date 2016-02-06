<?php

final class PhabricatorRepositoryPullEvent
  extends PhabricatorRepositoryDAO
  implements PhabricatorPolicyInterface {

  protected $repositoryPHID;
  protected $epoch;
  protected $pullerPHID;
  protected $remoteAddress;
  protected $remoteProtocol;
  protected $resultType;
  protected $resultCode;
  protected $properties;

  private $repository = self::ATTACHABLE;

  public static function initializeNewEvent(PhabricatorUser $viewer) {
    return id(new PhabricatorRepositoryPushEvent())
      ->setPusherPHID($viewer->getPHID());
  }

  protected function getConfiguration() {
    return array(
      self::CONFIG_AUX_PHID => true,
      self::CONFIG_TIMESTAMPS => false,
      self::CONFIG_SERIALIZATION => array(
        'properties' => self::SERIALIZATION_JSON,
      ),
      self::CONFIG_COLUMN_SCHEMA => array(
        'repositoryPHID' => 'phid?',
        'pullerPHID' => 'phid?',
        'remoteAddress' => 'ipaddress?',
        'remoteProtocol' => 'text32?',
        'resultType' => 'text32',
        'resultCode' => 'uint32',
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

  public function generatePHID() {
    return PhabricatorPHID::generateNewPHID(
      PhabricatorRepositoryPullEventPHIDType::TYPECONST);
  }

  public function attachRepository(PhabricatorRepository $repository) {
    $this->repository = $repository;
    return $this;
  }

  public function getRepository() {
    return $this->assertAttached($this->repository);
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
      "A repository's pull events are visible to users who can see the ".
      "repository.");
  }

}
