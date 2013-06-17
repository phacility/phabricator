<?php

final class PhabricatorAuthProviderConfig extends PhabricatorAuthDAO {

  protected $phid;
  protected $providerClass;
  protected $providerType;
  protected $providerDomain;

  protected $isEnabled                = 0;
  protected $shouldAllowLogin         = 0;
  protected $shouldAllowRegistration  = 0;
  protected $shouldAllowLink          = 0;
  protected $shouldAllowUnlink        = 0;

  protected $properties = array();

  public function generatePHID() {
    return PhabricatorPHID::generateNewPHID(
      PhabricatorPHIDConstants::PHID_TYPE_AUTH);
  }

  public function getConfiguration() {
    return array(
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


}
