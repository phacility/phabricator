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

  public function getIdentityShortName() {
    // TODO
    return $this->getIdentityName();
  }

  public function getURI() {
    return '/diffusion/identity/view/'.$this->getID().'/';
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

  public function getApplicationTransactionObject() {
    return $this;
  }

  public function getApplicationTransactionTemplate() {
    return new PhabricatorRepositoryIdentityTransaction();
  }

  public function willRenderTimeline(
    PhabricatorApplicationTransactionView $timeline,
    AphrontRequest $request) {

    return $timeline;
  }

}
