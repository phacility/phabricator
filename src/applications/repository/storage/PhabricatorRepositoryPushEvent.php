<?php

/**
 * Groups a set of push logs corresponding to changes which were all pushed in
 * the same transaction.
 */
final class PhabricatorRepositoryPushEvent
  extends PhabricatorRepositoryDAO
  implements PhabricatorPolicyInterface {

  protected $repositoryPHID;
  protected $epoch;
  protected $pusherPHID;
  protected $remoteAddress;
  protected $remoteProtocol;
  protected $rejectCode;
  protected $rejectDetails;

  private $repository = self::ATTACHABLE;
  private $logs = self::ATTACHABLE;

  public static function initializeNewEvent(PhabricatorUser $viewer) {
    return id(new PhabricatorRepositoryPushEvent())
      ->setPusherPHID($viewer->getPHID());
  }

  protected function getConfiguration() {
    return array(
      self::CONFIG_AUX_PHID => true,
      self::CONFIG_TIMESTAMPS => false,
      self::CONFIG_COLUMN_SCHEMA => array(
        'remoteAddress' => 'ipaddress?',
        'remoteProtocol' => 'text32?',
        'rejectCode' => 'uint32',
        'rejectDetails' => 'text64?',
      ),
      self::CONFIG_KEY_SCHEMA => array(
        'key_repository' => array(
          'columns' => array('repositoryPHID'),
        ),
      ),
    ) + parent::getConfiguration();
  }

  public function generatePHID() {
    return PhabricatorPHID::generateNewPHID(
      PhabricatorRepositoryPushEventPHIDType::TYPECONST);
  }

  public function attachRepository(PhabricatorRepository $repository) {
    $this->repository = $repository;
    return $this;
  }

  public function getRepository() {
    return $this->assertAttached($this->repository);
  }

  public function attachLogs(array $logs) {
    $this->logs = $logs;
    return $this;
  }

  public function getLogs() {
    return $this->assertAttached($this->logs);
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
      "A repository's push events are visible to users who can see the ".
      "repository.");
  }

}
