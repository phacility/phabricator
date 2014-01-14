<?php

final class PhabricatorAuthSession extends PhabricatorAuthDAO
  implements PhabricatorPolicyInterface {

  const TYPE_WEB      = 'web';
  const TYPE_CONDUIT  = 'conduit';

  protected $userPHID;
  protected $type;
  protected $sessionKey;
  protected $sessionStart;

  private $identityObject = self::ATTACHABLE;

  public function getConfiguration() {
    return array(
      self::CONFIG_IDS => self::IDS_MANUAL,
      self::CONFIG_TIMESTAMPS => false,
    ) + parent::getConfiguration();
  }

  public function getApplicationName() {
    // This table predates the "Auth" application, and really all applications.
    return 'user';
  }

  public function getTableName() {
    // This is a very old table with a nonstandard name.
    return PhabricatorUser::SESSION_TABLE;
  }

  public function attachIdentityObject($identity_object) {
    $this->identityObject = $identity_object;
    return $this;
  }

  public function getIdentityObject() {
    return $this->assertAttached($this->identityObject);
  }

  public function delete() {
    // TODO: We don't have a proper `id` column yet, so make this work as
    // expected until we do.
    queryfx(
      $this->establishConnection('w'),
      'DELETE FROM %T WHERE sessionKey = %s',
      $this->getTableName(),
      $this->getSessionKey());
    return $this;
  }

/* -(  PhabricatorPolicyInterface  )----------------------------------------- */


  public function getCapabilities() {
    return array(
      PhabricatorPolicyCapability::CAN_VIEW,
    );
  }

  public function getPolicy($capability) {
    return PhabricatorPolicies::POLICY_NOONE;
  }

  public function hasAutomaticCapability($capability, PhabricatorUser $viewer) {
    if (!$viewer->getPHID()) {
      return false;
    }

    $object = $this->getIdentityObject();
    if ($object instanceof PhabricatorUser) {
      return ($object->getPHID() == $viewer->getPHID());
    } else if ($object instanceof PhabricatorExternalAccount) {
      return ($object->getUserPHID() == $viewer->getPHID());
    }

    return false;
  }

  public function describeAutomaticCapability($capability) {
    return pht('A session is visible only to its owner.');
  }

}
