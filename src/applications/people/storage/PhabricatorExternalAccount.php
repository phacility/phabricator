<?php

final class PhabricatorExternalAccount
  extends PhabricatorUserDAO
  implements
    PhabricatorPolicyInterface,
    PhabricatorDestructibleInterface {

  protected $userPHID;
  protected $accountSecret;
  protected $displayName;
  protected $username;
  protected $realName;
  protected $email;
  protected $emailVerified = 0;
  protected $accountURI;
  protected $profileImagePHID;
  protected $properties = array();
  protected $providerConfigPHID;

  // TODO: Remove these (see T13493). These columns are obsolete and have
  // no readers and only trivial writers.
  protected $accountType;
  protected $accountDomain;
  protected $accountID;

  private $profileImageFile = self::ATTACHABLE;
  private $providerConfig = self::ATTACHABLE;
  private $accountIdentifiers = self::ATTACHABLE;

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
        'key_user' => array(
          'columns' => array('userPHID'),
        ),
        'key_provider' => array(
          'columns' => array('providerConfigPHID', 'userPHID'),
        ),
      ),
    ) + parent::getConfiguration();
  }

  public function save() {
    if (!$this->getAccountSecret()) {
      $this->setAccountSecret(Filesystem::readRandomCharacters(32));
    }

    $this->openTransaction();

      $result = parent::save();

      $account_phid = $this->getPHID();
      $config_phid = $this->getProviderConfigPHID();

      if ($this->accountIdentifiers !== self::ATTACHABLE) {
        foreach ($this->getAccountIdentifiers() as $identifier) {
          $identifier
            ->setExternalAccountPHID($account_phid)
            ->setProviderConfigPHID($config_phid)
            ->save();
        }
      }

    $this->saveTransaction();

    return $result;
  }

  public function unlinkAccount() {

    // When unlinking an account, we disassociate it from the user and
    // remove all the identifying information. We retain the PHID, the
    // object itself, and the "ExternalAccountIdentifier" objects in the
    // external table.

    // TODO: This unlinks (but does not destroy) any profile image.

    return $this
      ->setUserPHID(null)
      ->setDisplayName(null)
      ->setUsername(null)
      ->setRealName(null)
      ->setEmail(null)
      ->setEmailVerified(0)
      ->setProfileImagePHID(null)
      ->setAccountURI(null)
      ->setProperties(array())
      ->save();
  }

  public function setProperty($key, $value) {
    $this->properties[$key] = $value;
    return $this;
  }

  public function getProperty($key, $default = null) {
    return idx($this->properties, $key, $default);
  }

  public function isUsableForLogin() {
    $config = $this->getProviderConfig();
    if (!$config->getIsEnabled()) {
      return false;
    }

    $provider = $config->getProvider();
    if (!$provider->shouldAllowLogin()) {
      return false;
    }

    return true;
  }

  public function attachProviderConfig(PhabricatorAuthProviderConfig $config) {
    $this->providerConfig = $config;
    return $this;
  }

  public function getProviderConfig() {
    return $this->assertAttached($this->providerConfig);
  }

  public function getAccountIdentifiers() {
    $raw = $this->assertAttached($this->accountIdentifiers);
    return array_values($raw);
  }

  public function attachAccountIdentifiers(array $identifiers) {
    assert_instances_of($identifiers, 'PhabricatorExternalAccountIdentifier');
    $this->accountIdentifiers = mpull($identifiers, null, 'getIdentifierRaw');
    return $this;
  }

  public function appendIdentifier(
    PhabricatorExternalAccountIdentifier $identifier) {

    $this->assertAttached($this->accountIdentifiers);

    $map = $this->accountIdentifiers;
    $raw = $identifier->getIdentifierRaw();

    $old = idx($map, $raw);
    $new = $identifier;

    if ($old === null) {
      $result = $new;
    } else {
      // Here, we already know about an identifier and have rediscovered it.

      // We could copy properties from the new version of the identifier here,
      // or merge them in some other way (for example, update a "last seen
      // from the provider" timestamp), but no such properties currently exist.
      $result = $old;
    }

    $this->accountIdentifiers[$raw] = $result;

    return $this;
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


/* -(  PhabricatorDestructibleInterface  )----------------------------------- */


  public function destroyObjectPermanently(
    PhabricatorDestructionEngine $engine) {

    $viewer = $engine->getViewer();

    $identifiers = id(new PhabricatorExternalAccountIdentifierQuery())
      ->setViewer($viewer)
      ->withExternalAccountPHIDs(array($this->getPHID()))
      ->newIterator();
    foreach ($identifiers as $identifier) {
      $engine->destroyObject($identifier);
    }

    // TODO: This may leave a profile image behind.

    $this->delete();
  }

}
