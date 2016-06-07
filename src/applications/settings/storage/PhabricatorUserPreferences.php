<?php

final class PhabricatorUserPreferences
  extends PhabricatorUserDAO
  implements
    PhabricatorPolicyInterface,
    PhabricatorDestructibleInterface,
    PhabricatorApplicationTransactionInterface {

  const BUILTIN_GLOBAL_DEFAULT = 'global';

  protected $userPHID;
  protected $preferences = array();
  protected $builtinKey;

  private $user = self::ATTACHABLE;
  private $defaultSettings;

  protected function getConfiguration() {
    return array(
      self::CONFIG_AUX_PHID => true,
      self::CONFIG_SERIALIZATION => array(
        'preferences' => self::SERIALIZATION_JSON,
      ),
      self::CONFIG_COLUMN_SCHEMA => array(
        'userPHID' => 'phid?',
        'builtinKey' => 'text32?',
      ),
      self::CONFIG_KEY_SCHEMA => array(
        'key_user' => array(
          'columns' => array('userPHID'),
          'unique' => true,
        ),
        'key_builtin' => array(
          'columns' => array('builtinKey'),
          'unique' => true,
        ),
      ),
    ) + parent::getConfiguration();
  }

  public function generatePHID() {
    return PhabricatorPHID::generateNewPHID(
      PhabricatorUserPreferencesPHIDType::TYPECONST);
  }

  public function getPreference($key, $default = null) {
    return idx($this->preferences, $key, $default);
  }

  public function setPreference($key, $value) {
    $this->preferences[$key] = $value;
    return $this;
  }

  public function unsetPreference($key) {
    unset($this->preferences[$key]);
    return $this;
  }

  public function getDefaultValue($key) {
    if ($this->defaultSettings) {
      return $this->defaultSettings->getSettingValue($key);
    }

    $setting = self::getSettingObject($key);

    if (!$setting) {
      return null;
    }

    $setting = id(clone $setting)
      ->setViewer($this->getUser());

    return $setting->getSettingDefaultValue();
  }

  public function getSettingValue($key) {
    if (array_key_exists($key, $this->preferences)) {
      return $this->preferences[$key];
    }

    return $this->getDefaultValue($key);
  }

  private static function getSettingObject($key) {
    $settings = PhabricatorSetting::getAllSettings();
    return idx($settings, $key);
  }

  public function attachDefaultSettings(PhabricatorUserPreferences $settings) {
    $this->defaultSettings = $settings;
    return $this;
  }

  public function attachUser(PhabricatorUser $user = null) {
    $this->user = $user;
    return $this;
  }

  public function getUser() {
    return $this->assertAttached($this->user);
  }

  public function hasManagedUser() {
    $user_phid = $this->getUserPHID();
    if (!$user_phid) {
      return false;
    }

    $user = $this->getUser();
    if ($user->getIsSystemAgent() || $user->getIsMailingList()) {
      return true;
    }

    return false;
  }

  /**
   * Load or create a preferences object for the given user.
   *
   * @param PhabricatorUser User to load or create preferences for.
   */
  public static function loadUserPreferences(PhabricatorUser $user) {
    $preferences = id(new PhabricatorUserPreferencesQuery())
      ->setViewer($user)
      ->withUsers(array($user))
      ->executeOne();
    if ($preferences) {
      return $preferences;
    }

    return id(new self())
      ->setUserPHID($user->getPHID())
      ->attachUser($user);
  }

  public function newTransaction($key, $value) {
    $setting_property = PhabricatorUserPreferencesTransaction::PROPERTY_SETTING;
    $xaction_type = PhabricatorUserPreferencesTransaction::TYPE_SETTING;

    return id(clone $this->getApplicationTransactionTemplate())
      ->setTransactionType($xaction_type)
      ->setMetadataValue($setting_property, $key)
      ->setNewValue($value);
  }

  public function getEditURI() {
    if ($this->getUser()) {
      return '/settings/user/'.$this->getUser()->getUsername().'/';
    } else {
      return '/settings/builtin/'.$this->getBuiltinKey().'/';
    }
  }

  public function getDisplayName() {
    if ($this->getBuiltinKey()) {
      return pht('Global Default Settings');
    }

    return pht('Personal Settings');
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
        $user_phid = $this->getUserPHID();
        if ($user_phid) {
          return $user_phid;
        }

        return PhabricatorPolicies::getMostOpenPolicy();
      case PhabricatorPolicyCapability::CAN_EDIT:
        if ($this->hasManagedUser()) {
          return PhabricatorPolicies::POLICY_ADMIN;
        }

        $user_phid = $this->getUserPHID();
        if ($user_phid) {
          return $user_phid;
        }

        return PhabricatorPolicies::POLICY_ADMIN;
    }
  }

  public function hasAutomaticCapability($capability, PhabricatorUser $viewer) {
    if ($this->hasManagedUser()) {
      if ($viewer->getIsAdmin()) {
        return true;
      }
    }

    return false;
  }

  public function describeAutomaticCapability($capability) {
    return null;
  }


/* -(  PhabricatorDestructibleInterface  )----------------------------------- */


  public function destroyObjectPermanently(
    PhabricatorDestructionEngine $engine) {
    $this->delete();
  }


/* -(  PhabricatorApplicationTransactionInterface  )------------------------- */


  public function getApplicationTransactionEditor() {
    return new PhabricatorUserPreferencesEditor();
  }

  public function getApplicationTransactionObject() {
    return $this;
  }

  public function getApplicationTransactionTemplate() {
    return new PhabricatorUserPreferencesTransaction();
  }

  public function willRenderTimeline(
    PhabricatorApplicationTransactionView $timeline,
    AphrontRequest $request) {
    return $timeline;
  }

}
