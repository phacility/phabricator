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

  public function attachRepository(PhabricatorRepository $repository = null) {
    $this->repository = $repository;
    return $this;
  }

  public function getRepository() {
    return $this->assertAttached($this->repository);
  }

  public function getRemoteProtocolDisplayName() {
    $map = array(
      'ssh' => pht('SSH'),
      'http' => pht('HTTP'),
    );

    $protocol = $this->getRemoteProtocol();

    return idx($map, $protocol, $protocol);
  }

  public function newResultIcon() {
    $icon = new PHUIIconView();
    $type = $this->getResultType();

    switch ($type) {
      case 'wild':
        $icon
          ->setIcon('fa-question indigo')
          ->setTooltip(pht('Unknown ("%s")', $type));
        break;
      case 'pull':
        $icon
          ->setIcon('fa-download green')
          ->setTooltip(pht('Pull'));
        break;
    }

    return $icon;
  }



/* -(  PhabricatorPolicyInterface  )----------------------------------------- */


  public function getCapabilities() {
    return array(
      PhabricatorPolicyCapability::CAN_VIEW,
    );
  }

  public function getPolicy($capability) {
    if ($this->getRepository()) {
      return $this->getRepository()->getPolicy($capability);
    }

    return PhabricatorPolicies::POLICY_ADMIN;
  }

  public function hasAutomaticCapability($capability, PhabricatorUser $viewer) {
    if (!$this->getRepository()) {
      return false;
    }

    return $this->getRepository()->hasAutomaticCapability($capability, $viewer);
  }

  public function describeAutomaticCapability($capability) {
    return pht(
      "A repository's pull events are visible to users who can see the ".
      "repository.");
  }

}
