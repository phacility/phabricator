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
      PhabricatorPeoplePHIDTypeExternal::TYPECONST);
  }

  public function getConfiguration() {
    return array(
      self::CONFIG_AUX_PHID => true,
      self::CONFIG_SERIALIZATION => array(
        'properties' => self::SERIALIZATION_JSON,
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
    return ($viewer->getPHID() == $this->getUserPHID());
  }

  public function describeAutomaticCapability($capability) {
    // TODO: (T603) This is complicated.
    return null;
  }

}
