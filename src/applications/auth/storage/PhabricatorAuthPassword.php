<?php

final class PhabricatorAuthPassword
  extends PhabricatorAuthDAO
  implements
    PhabricatorPolicyInterface,
    PhabricatorDestructibleInterface,
    PhabricatorApplicationTransactionInterface {

  protected $objectPHID;
  protected $passwordType;
  protected $passwordHash;
  protected $passwordSalt;
  protected $isRevoked;
  protected $legacyDigestFormat;

  private $object = self::ATTACHABLE;

  const PASSWORD_TYPE_ACCOUNT = 'account';
  const PASSWORD_TYPE_VCS = 'vcs';
  const PASSWORD_TYPE_TEST = 'test';

  public static function initializeNewPassword(
    PhabricatorAuthPasswordHashInterface $object,
    $type) {

    return id(new self())
      ->setObjectPHID($object->getPHID())
      ->attachObject($object)
      ->setPasswordType($type)
      ->setIsRevoked(0);
  }

  protected function getConfiguration() {
    return array(
      self::CONFIG_AUX_PHID => true,
      self::CONFIG_COLUMN_SCHEMA => array(
        'passwordType' => 'text64',
        'passwordHash' => 'text128',
        'passwordSalt' => 'text64',
        'isRevoked' => 'bool',
        'legacyDigestFormat' => 'text32?',
      ),
      self::CONFIG_KEY_SCHEMA => array(
        'key_role' => array(
          'columns' => array('objectPHID', 'passwordType'),
        ),
      ),
    ) + parent::getConfiguration();
  }

  public function getPHIDType() {
    return PhabricatorAuthPasswordPHIDType::TYPECONST;
  }

  public function getObject() {
    return $this->assertAttached($this->object);
  }

  public function attachObject($object) {
    $this->object = $object;
    return $this;
  }

  public function getHasher() {
    $hash = $this->newPasswordEnvelope();
    return PhabricatorPasswordHasher::getHasherForHash($hash);
  }

  public function canUpgrade() {
    // If this password uses a legacy digest format, we can upgrade it to the
    // new digest format even if a better hasher isn't available.
    if ($this->getLegacyDigestFormat() !== null) {
      return true;
    }

    $hash = $this->newPasswordEnvelope();
    return PhabricatorPasswordHasher::canUpgradeHash($hash);
  }

  public function upgradePasswordHasher(
    PhutilOpaqueEnvelope $envelope,
    PhabricatorAuthPasswordHashInterface $object) {

    // Before we make changes, double check that this is really the correct
    // password. It could be really bad if we "upgraded" a password and changed
    // the secret!

    if (!$this->comparePassword($envelope, $object)) {
      throw new Exception(
        pht(
          'Attempting to upgrade password hasher, but the password for the '.
          'upgrade is not the stored credential!'));
    }

    return $this->setPassword($envelope, $object);
  }

  public function setPassword(
    PhutilOpaqueEnvelope $password,
    PhabricatorAuthPasswordHashInterface $object) {

    $hasher = PhabricatorPasswordHasher::getBestHasher();
    return $this->setPasswordWithHasher($password, $object, $hasher);
  }

  public function setPasswordWithHasher(
    PhutilOpaqueEnvelope $password,
    PhabricatorAuthPasswordHashInterface $object,
    PhabricatorPasswordHasher $hasher) {

    if (!strlen($password->openEnvelope())) {
      throw new Exception(
        pht('Attempting to set an empty password!'));
    }

    // Generate (or regenerate) the salt first.
    $new_salt = Filesystem::readRandomCharacters(64);
    $this->setPasswordSalt($new_salt);

    // Clear any legacy digest format to force a modern digest.
    $this->setLegacyDigestFormat(null);

    $digest = $this->digestPassword($password, $object);
    $hash = $hasher->getPasswordHashForStorage($digest);
    $raw_hash = $hash->openEnvelope();

    return $this->setPasswordHash($raw_hash);
  }

  public function comparePassword(
    PhutilOpaqueEnvelope $password,
    PhabricatorAuthPasswordHashInterface $object) {

    $digest = $this->digestPassword($password, $object);
    $hash = $this->newPasswordEnvelope();

    return PhabricatorPasswordHasher::comparePassword($digest, $hash);
  }

  public function newPasswordEnvelope() {
    return new PhutilOpaqueEnvelope($this->getPasswordHash());
  }

  private function digestPassword(
    PhutilOpaqueEnvelope $password,
    PhabricatorAuthPasswordHashInterface $object) {

    $object_phid = $object->getPHID();

    if ($this->getObjectPHID() !== $object->getPHID()) {
      throw new Exception(
        pht(
          'This password is associated with an object PHID ("%s") for '.
          'a different object than the provided one ("%s").',
          $this->getObjectPHID(),
          $object->getPHID()));
    }

    $digest = $object->newPasswordDigest($password, $this);

    if (!($digest instanceof PhutilOpaqueEnvelope)) {
      throw new Exception(
        pht(
          'Failed to digest password: object ("%s") did not return an '.
          'opaque envelope with a password digest.',
          $object->getPHID()));
    }

    return $digest;
  }



/* -(  PhabricatorPolicyInterface  )----------------------------------------- */


  public function getCapabilities() {
    return array(
      PhabricatorPolicyCapability::CAN_VIEW,
      PhabricatorPolicyCapability::CAN_EDIT,
    );
  }

  public function getPolicy($capability) {
    return PhabricatorPolicies::getMostOpenPolicy();
  }

  public function hasAutomaticCapability($capability, PhabricatorUser $viewer) {
    return false;
  }


/* -(  PhabricatorExtendedPolicyInterface  )--------------------------------- */


  public function getExtendedPolicy($capability, PhabricatorUser $viewer) {
    return array(
      array($this->getObject(), $capability),
    );
  }


/* -(  PhabricatorDestructibleInterface  )----------------------------------- */


  public function destroyObjectPermanently(
    PhabricatorDestructionEngine $engine) {
    $this->delete();
  }


/* -(  PhabricatorApplicationTransactionInterface  )------------------------- */


  public function getApplicationTransactionEditor() {
    return new PhabricatorAuthPasswordEditor();
  }

  public function getApplicationTransactionTemplate() {
    return new PhabricatorAuthPasswordTransaction();
  }

}
