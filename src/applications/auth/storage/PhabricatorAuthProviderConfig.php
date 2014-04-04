<?php

final class PhabricatorAuthProviderConfig extends PhabricatorAuthDAO
  implements PhabricatorPolicyInterface {

  protected $providerClass;
  protected $providerType;
  protected $providerDomain;

  protected $isEnabled;
  protected $shouldAllowLogin         = 0;
  protected $shouldAllowRegistration  = 0;
  protected $shouldAllowLink          = 0;
  protected $shouldAllowUnlink        = 0;

  protected $properties = array();

  private $provider;

  public function generatePHID() {
    return PhabricatorPHID::generateNewPHID(
      PhabricatorPHIDConstants::PHID_TYPE_AUTH);
  }

  public function getConfiguration() {
    return array(
      self::CONFIG_AUX_PHID => true,
      self::CONFIG_SERIALIZATION => array(
        'properties' => self::SERIALIZATION_JSON,
      ),
    ) + parent::getConfiguration();
  }

  public function getProperty($key, $default = null) {
    return idx($this->properties, $key, $default);
  }

  public function setProperty($key, $value) {
    $this->properties[$key] = $value;
    return $this;
  }

  public function getProvider() {
    if (!$this->provider) {
      $base = PhabricatorAuthProvider::getAllBaseProviders();
      $found = null;
      foreach ($base as $provider) {
        if (get_class($provider) == $this->providerClass) {
          $found = $provider;
          break;
        }
      }
      if ($found) {
        $this->provider = id(clone $found)->attachProviderConfig($this);
      }
    }
    return $this->provider;
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
        return PhabricatorPolicies::POLICY_USER;
      case PhabricatorPolicyCapability::CAN_EDIT:
        return PhabricatorPolicies::POLICY_ADMIN;
    }
  }

  public function hasAutomaticCapability($capability, PhabricatorUser $viewer) {
    return false;
  }

  public function describeAutomaticCapability($capability) {
    return null;
  }

}
