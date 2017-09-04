<?php

final class PhabricatorNamedQueryConfig
  extends PhabricatorSearchDAO
  implements PhabricatorPolicyInterface {

  protected $engineClassName;
  protected $scopePHID;
  protected $properties = array();

  const SCOPE_GLOBAL = 'scope.global';

  const PROPERTY_PINNED = 'pinned';

  protected function getConfiguration() {
    return array(
      self::CONFIG_SERIALIZATION => array(
        'properties' => self::SERIALIZATION_JSON,
      ),
      self::CONFIG_COLUMN_SCHEMA => array(
        'engineClassName' => 'text128',
      ),
      self::CONFIG_KEY_SCHEMA => array(
        'key_scope' => array(
          'columns' => array('engineClassName', 'scopePHID'),
          'unique' => true,
        ),
      ),
    ) + parent::getConfiguration();
  }

  public static function initializeNewQueryConfig() {
    return new self();
  }

  public function isGlobal() {
    return ($this->getScopePHID() == self::SCOPE_GLOBAL);
  }

  public function getConfigProperty($key, $default = null) {
    return idx($this->properties, $key, $default);
  }

  public function setConfigProperty($key, $value) {
    $this->properties[$key] = $value;
    return $this;
  }

  public function getStrengthSortVector() {
    // Apply personal preferences before global preferences.
    if (!$this->isGlobal()) {
      $phase = 0;
    } else {
      $phase = 1;
    }

    return id(new PhutilSortVector())
      ->addInt($phase)
      ->addInt($this->getID());
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
    if ($this->isGlobal()) {
      return true;
    }

    if ($viewer->getPHID() == $this->getScopePHID()) {
      return true;
    }

    return false;
  }

  public function describeAutomaticCapability($capability) {
    return null;
  }

}
