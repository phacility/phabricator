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
  protected $isRevoked;

  private $object = self::ATTACHABLE;

  const PASSWORD_TYPE_ACCOUNT = 'account';
  const PASSWORD_TYPE_VCS = 'vcs';
  const PASSWORD_TYPE_TEST = 'test';

  public static function initializeNewPassword(
    PhabricatorUser $object,
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
        'isRevoked' => 'bool',
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

  public function setPassword(
    PhutilOpaqueEnvelope $password,
    PhabricatorUser $object) {

    $hasher = PhabricatorPasswordHasher::getBestHasher();

    $digest = $this->digestPassword($password, $object);
    $hash = $hasher->getPasswordHashForStorage($digest);
    $raw_hash = $hash->openEnvelope();

    return $this->setPasswordHash($raw_hash);
  }

  public function comparePassword(
    PhutilOpaqueEnvelope $password,
    PhabricatorUser $object) {

    $digest = $this->digestPassword($password, $object);
    $raw_hash = $this->getPasswordHash();
    $hash = new PhutilOpaqueEnvelope($raw_hash);

    return PhabricatorPasswordHasher::comparePassword($digest, $hash);
  }

  private function digestPassword(
    PhutilOpaqueEnvelope $password,
    PhabricatorUser $object) {

    $object_phid = $object->getPHID();

    if ($this->getObjectPHID() !== $object->getPHID()) {
      throw new Exception(
        pht(
          'This password is associated with an object PHID ("%s") for '.
          'a different object than the provided one ("%s").',
          $this->getObjectPHID(),
          $object->getPHID()));
    }

    $raw_input = PhabricatorHash::digestPassword($password, $object_phid);

    return new PhutilOpaqueEnvelope($raw_input);
  }



/* -(  PhabricatorPolicyInterface  )----------------------------------------- */


  public function getCapabilities() {
    return array(
      PhabricatorPolicyCapability::CAN_VIEW,
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
      array($this->getObject(), PhabricatorPolicyCapability::CAN_VIEW),
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

  public function getApplicationTransactionObject() {
    return $this;
  }

  public function getApplicationTransactionTemplate() {
    return new PhabricatorAuthPasswordTransaction();
  }

  public function willRenderTimeline(
    PhabricatorApplicationTransactionView $timeline,
    AphrontRequest $request) {

    return $timeline;
  }


}
