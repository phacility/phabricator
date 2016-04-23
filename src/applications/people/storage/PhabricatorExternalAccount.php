<?php

final class PhabricatorExternalAccount extends PhabricatorUserDAO
  implements PhabricatorPolicyInterface {

  protected $userPHID;
  protected $accountType;
  protected $accountDomain;
  protected $accountSecret;
  protected $accountID;
  protected $displayName;
  protected $username;
  protected $realName;
  protected $email;
  protected $emailVerified = 0;
  protected $accountURI;
  protected $profileImagePHID;
  protected $properties = array();

  private $profileImageFile = self::ATTACHABLE;

  public function getProfileImageFile() {
    return $this->assertAttached($this->profileImageFile);
  }

  public function attachProfileImageFile(PhabricatorFile $file) {
    $this->profileImageFile = $file;
    return $this;
  }

  public function generatePHID() {
    return PhabricatorPHID::generateNewPHID(
      PhabricatorPeopleExternalPHIDType::TYPECONST);
  }

  protected function getConfiguration() {
    return array(
      self::CONFIG_AUX_PHID => true,
      self::CONFIG_SERIALIZATION => array(
        'properties' => self::SERIALIZATION_JSON,
      ),
      self::CONFIG_COLUMN_SCHEMA => array(
        'userPHID' => 'phid?',
        'accountType' => 'text16',
        'accountDomain' => 'text64',
        'accountSecret' => 'text?',
        'accountID' => 'text64',
        'displayName' => 'text255?',
        'username' => 'text255?',
        'realName' => 'text255?',
        'email' => 'text255?',
        'emailVerified' => 'bool',
        'profileImagePHID' => 'phid?',
        'accountURI' => 'text255?',
      ),
      self::CONFIG_KEY_SCHEMA => array(
        'account_details' => array(
          'columns' => array('accountType', 'accountDomain', 'accountID'),
          'unique' => true,
        ),
        'key_user' => array(
          'columns' => array('userPHID'),
        ),
      ),
    ) + parent::getConfiguration();
  }

  public function getPhabricatorUser() {
    $tmp_usr = id(new PhabricatorUser())
      ->makeEphemeral()
      ->setPHID($this->getPHID());
    return $tmp_usr;
  }

  public function getProviderKey() {
    return $this->getAccountType().':'.$this->getAccountDomain();
  }

  public function save() {
    if (!$this->getAccountSecret()) {
      $this->setAccountSecret(Filesystem::readRandomCharacters(32));
    }
    return parent::save();
  }

  public function setProperty($key, $value) {
    $this->properties[$key] = $value;
    return $this;
  }

  public function getProperty($key, $default = null) {
    return idx($this->properties, $key, $default);
  }

  public function isUsableForLogin() {
    $key = $this->getProviderKey();
    $provider = PhabricatorAuthProvider::getEnabledProviderByKey($key);

    if (!$provider) {
      return false;
    }

    if (!$provider->shouldAllowLogin()) {
      return false;
    }

    return true;
  }

  public function getDisplayName() {
    if (strlen($this->displayName)) {
      return $this->displayName;
    }

    // TODO: Figure out how much identifying information we're going to show
    // to users about external accounts. For now, just show a string which is
    // clearly not an error, but don't disclose any identifying information.

    $map = array(
      'email' => pht('Email User'),
    );

    $type = $this->getAccountType();

    return idx($map, $type, pht('"%s" User', $type));
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
        return PhabricatorPolicies::getMostOpenPolicy();
      case PhabricatorPolicyCapability::CAN_EDIT:
        return PhabricatorPolicies::POLICY_NOONE;
    }
  }

  public function hasAutomaticCapability($capability, PhabricatorUser $viewer) {
    return ($viewer->getPHID() == $this->getUserPHID());
  }

  public function describeAutomaticCapability($capability) {
    switch ($capability) {
      case PhabricatorPolicyCapability::CAN_VIEW:
        return null;
      case PhabricatorPolicyCapability::CAN_EDIT:
        return pht(
          'External accounts can only be edited by the account owner.');
    }
  }

}
