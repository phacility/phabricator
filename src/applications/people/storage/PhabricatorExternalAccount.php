<?php

final class PhabricatorExternalAccount extends PhabricatorUserDAO {

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

  public function generatePHID() {
    return PhabricatorPHID::generateNewPHID(
      PhabricatorPHIDConstants::PHID_TYPE_XUSR);
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

}
