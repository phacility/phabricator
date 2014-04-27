<?php

final class PhabricatorRepositoryAuditRequest
  extends PhabricatorRepositoryDAO
  implements PhabricatorPolicyInterface {

  protected $auditorPHID;
  protected $commitPHID;
  protected $auditReasons = array();
  protected $auditStatus;

  private $commit = self::ATTACHABLE;

  public function getConfiguration() {
    return array(
      self::CONFIG_TIMESTAMPS => false,
      self::CONFIG_SERIALIZATION => array(
        'auditReasons' => self::SERIALIZATION_JSON,
      ),
    ) + parent::getConfiguration();
  }

  public function isUser() {
    $user_type = PhabricatorPeoplePHIDTypeUser::TYPECONST;
    return (phid_get_type($this->getAuditorPHID()) == $user_type);
  }

  public function attachCommit(PhabricatorRepositoryCommit $commit) {
    $this->commit = $commit;
    return $this;
  }

  public function getCommit() {
    return $this->assertAttached($this->commit);
  }


/* -(  PhabricatorPolicyInterface  )----------------------------------------- */


  public function getCapabilities() {
    return array(
      PhabricatorPolicyCapability::CAN_VIEW,
    );
  }

  public function getPolicy($capability) {
    return $this->getCommit()->getPolicy($capability);
  }

  public function hasAutomaticCapability($capability, PhabricatorUser $viewer) {
    return $this->getCommit()->hasAutomaticCapability($capability, $viewer);
  }

  public function describeAutomaticCapability($capability) {
    return pht(
      'This audit is attached to a commit, and inherits its policies.');
  }
}
