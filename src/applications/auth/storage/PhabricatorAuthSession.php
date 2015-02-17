<?php

final class PhabricatorAuthSession extends PhabricatorAuthDAO
  implements PhabricatorPolicyInterface {

  const TYPE_WEB      = 'web';
  const TYPE_CONDUIT  = 'conduit';

  protected $userPHID;
  protected $type;
  protected $sessionKey;
  protected $sessionStart;
  protected $sessionExpires;
  protected $highSecurityUntil;
  protected $isPartial;
  protected $signedLegalpadDocuments;

  private $identityObject = self::ATTACHABLE;

  protected function getConfiguration() {
    return array(
      self::CONFIG_TIMESTAMPS => false,
      self::CONFIG_COLUMN_SCHEMA => array(
        'type' => 'text32',
        'sessionKey' => 'bytes40',
        'sessionStart' => 'epoch',
        'sessionExpires' => 'epoch',
        'highSecurityUntil' => 'epoch?',
        'isPartial' => 'bool',
        'signedLegalpadDocuments' => 'bool',
      ),
      self::CONFIG_KEY_SCHEMA => array(
        'sessionKey' => array(
          'columns' => array('sessionKey'),
          'unique' => true,
        ),
        'key_identity' => array(
          'columns' => array('userPHID', 'type'),
        ),
        'key_expires' => array(
          'columns' => array('sessionExpires'),
        ),
      ),
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

  public static function getSessionTypeTTL($session_type) {
    switch ($session_type) {
      case self::TYPE_WEB:
        return phutil_units('30 days in seconds');
      case self::TYPE_CONDUIT:
        return phutil_units('24 hours in seconds');
      default:
        throw new Exception(pht('Unknown session type "%s".', $session_type));
    }
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
