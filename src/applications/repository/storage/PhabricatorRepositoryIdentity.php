<?php

final class PhabricatorRepositoryIdentity
  extends PhabricatorRepositoryDAO
  implements
    PhabricatorPolicyInterface,
    PhabricatorApplicationTransactionInterface {

  protected $authorPHID;
  protected $identityNameHash;
  protected $identityNameRaw;
  protected $identityNameEncoding;
  protected $automaticGuessedUserPHID;
  protected $manuallySetUserPHID;
  protected $currentEffectiveUserPHID;

  private $effectiveUser = self::ATTACHABLE;

  public function attachEffectiveUser(PhabricatorUser $user) {
    $this->effectiveUser = $user;
    return $this;
  }

  public function getEffectiveUser() {
    return $this->assertAttached($this->effectiveUser);
  }

  protected function getConfiguration() {
    return array(
      self::CONFIG_AUX_PHID => true,
      self::CONFIG_BINARY => array(
        'identityNameRaw' => true,
      ),
      self::CONFIG_COLUMN_SCHEMA => array(
        'identityNameHash' => 'bytes12',
        'identityNameEncoding' => 'text16?',
        'automaticGuessedUserPHID' => 'phid?',
        'manuallySetUserPHID' => 'phid?',
        'currentEffectiveUserPHID' => 'phid?',
      ),
      self::CONFIG_KEY_SCHEMA => array(
        'key_identity' => array(
          'columns' => array('identityNameHash'),
          'unique' => true,
        ),
      ),
    ) + parent::getConfiguration();
  }

  public function getPHIDType() {
    return PhabricatorRepositoryIdentityPHIDType::TYPECONST;
  }

  public function setIdentityName($name_raw) {
    $this->setIdentityNameRaw($name_raw);
    $this->setIdentityNameHash(PhabricatorHash::digestForIndex($name_raw));
    $this->setIdentityNameEncoding($this->detectEncodingForStorage($name_raw));

    return $this;
  }

  public function getIdentityName() {
    return $this->getUTF8StringFromStorage(
      $this->getIdentityNameRaw(),
      $this->getIdentityNameEncoding());
  }

  public function getIdentityEmailAddress() {
    $address = new PhutilEmailAddress($this->getIdentityName());
    return $address->getAddress();
  }

  public function getIdentityDisplayName() {
    $address = new PhutilEmailAddress($this->getIdentityName());
    return $address->getDisplayName();
  }

  public function getIdentityShortName() {
    // TODO
    return $this->getIdentityName();
  }

  public function getURI() {
    return '/diffusion/identity/view/'.$this->getID().'/';
  }

  public function hasEffectiveUser() {
    return ($this->currentEffectiveUserPHID != null);
  }

  public function getIdentityDisplayPHID() {
    if ($this->hasEffectiveUser()) {
      return $this->getCurrentEffectiveUserPHID();
    } else {
      return $this->getPHID();
    }
  }

  public function save() {
    if ($this->manuallySetUserPHID) {
      $this->currentEffectiveUserPHID = $this->manuallySetUserPHID;
    } else {
      $this->currentEffectiveUserPHID = $this->automaticGuessedUserPHID;
    }

    return parent::save();
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

  public function hasAutomaticCapability(
    $capability, PhabricatorUser $viewer) {
    return false;
  }


/* -(  PhabricatorApplicationTransactionInterface  )------------------------- */


  public function getApplicationTransactionEditor() {
    return new DiffusionRepositoryIdentityEditor();
  }

  public function getApplicationTransactionTemplate() {
    return new PhabricatorRepositoryIdentityTransaction();
  }

}
