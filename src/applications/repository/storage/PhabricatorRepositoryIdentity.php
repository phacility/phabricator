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
  protected $emailAddress;

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
        'emailAddress' => 'sort255?',
      ),
      self::CONFIG_KEY_SCHEMA => array(
        'key_identity' => array(
          'columns' => array('identityNameHash'),
          'unique' => true,
        ),
        'key_email' => array(
          'columns' => array('emailAddress(64)'),
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

  public function getObjectName() {
    return pht('Identity %d', $this->getID());
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
      $unassigned = DiffusionIdentityUnassignedDatasource::FUNCTION_TOKEN;
      if ($this->manuallySetUserPHID === $unassigned) {
        $effective_phid = null;
      } else {
        $effective_phid = $this->manuallySetUserPHID;
      }
    } else {
      $effective_phid = $this->automaticGuessedUserPHID;
    }

    $this->setCurrentEffectiveUserPHID($effective_phid);

    $email_address = $this->getIdentityEmailAddress();

    // Raw identities are unrestricted binary data, and may consequently
    // have arbitrarily long, binary email address information. We can't
    // store this kind of information in the "emailAddress" column, which
    // has column type "sort255".

    // This kind of address almost certainly not legitimate and users can
    // manually set the target of the identity, so just discard it rather
    // than trying especially hard to make it work.

    $byte_limit = $this->getColumnMaximumByteLength('emailAddress');
    $email_address = phutil_utf8ize($email_address);
    if (strlen($email_address) > $byte_limit) {
      $email_address = null;
    }

    $this->setEmailAddress($email_address);

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
    $capability,
    PhabricatorUser $viewer) {
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
