<?php

final class PassphraseCredential extends PassphraseDAO
  implements PhabricatorPolicyInterface,
  PhabricatorDestructibleInterface {

  protected $name;
  protected $credentialType;
  protected $providesType;
  protected $viewPolicy;
  protected $editPolicy;
  protected $description;
  protected $username;
  protected $secretID;
  protected $isDestroyed;
  protected $isLocked = 0;
  protected $allowConduit = 0;

  private $secret = self::ATTACHABLE;

  public static function initializeNewCredential(PhabricatorUser $actor) {
    return id(new PassphraseCredential())
      ->setName('')
      ->setUsername('')
      ->setDescription('')
      ->setIsDestroyed(0)
      ->setViewPolicy($actor->getPHID())
      ->setEditPolicy($actor->getPHID());
  }

  public function getMonogram() {
    return 'K'.$this->getID();
  }

  public function getConfiguration() {
    return array(
      self::CONFIG_AUX_PHID => true,
    ) + parent::getConfiguration();
  }

  public function generatePHID() {
    return PhabricatorPHID::generateNewPHID(
      PassphraseCredentialPHIDType::TYPECONST);
  }

  public function attachSecret(PhutilOpaqueEnvelope $secret = null) {
    $this->secret = $secret;
    return $this;
  }

  public function getSecret() {
    return $this->assertAttached($this->secret);
  }

  public function getCredentialTypeImplementation() {
    $type = $this->getCredentialType();
    return PassphraseCredentialType::getTypeByConstant($type);
  }


/* -(  PhabricatorPolicyInterface  )----------------------------------------- */


  public function getCapabilities() {
    return array(
      PhabricatorPolicyCapability::CAN_VIEW,
      PhabricatorPolicyCapability::CAN_EDIT,
    );
  }

  public function getPolicy($capability) {
    switch ($capability) {
      case PhabricatorPolicyCapability::CAN_VIEW:
        return $this->getViewPolicy();
      case PhabricatorPolicyCapability::CAN_EDIT:
        return $this->getEditPolicy();
    }
  }

  public function hasAutomaticCapability($capability, PhabricatorUser $viewer) {
    return false;
  }

  public function describeAutomaticCapability($capability) {
    return null;
  }

/* -(  PhabricatorDestructibleInterface  )----------------------------------- */

  public function destroyObjectPermanently(
    PhabricatorDestructionEngine $engine) {

    $this->openTransaction();
      $secrets = id(new PassphraseSecret())->loadAllWhere(
        'id = %d',
        $this->getSecretID());
      foreach ($secrets as $secret) {
        $secret->delete();
      }
      $this->delete();
    $this->saveTransaction();
  }
}
