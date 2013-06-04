<?php

final class DivinerLiveBook extends DivinerDAO
  implements PhabricatorPolicyInterface {

  protected $phid;
  protected $name;
  protected $viewPolicy;
  protected $configurationData = array();

  public function getConfiguration() {
    return array(
      self::CONFIG_AUX_PHID => true,
      self::CONFIG_SERIALIZATION => array(
        'configurationData' => self::SERIALIZATION_JSON,
      ),
    ) + parent::getConfiguration();
  }

  public function getConfig($key, $default = null) {
    return idx($this->configurationData, $key, $default);
  }

  public function setConfig($key, $value) {
    $this->configurationData[$key] = $value;
    return $this;
  }

  public function generatePHID() {
    return PhabricatorPHID::generateNewPHID(
      PhabricatorPHIDConstants::PHID_TYPE_BOOK);
  }

  public function getTitle() {
    return $this->getConfig('title', $this->getName());
  }

/* -(  PhabricatorPolicyInterface  )----------------------------------------- */

  public function getCapabilities() {
    return array(
      PhabricatorPolicyCapability::CAN_VIEW,
    );
  }

  public function getPolicy($capability) {
    return $this->viewPolicy;
  }

  public function hasAutomaticCapability($capability, PhabricatorUser $viewer) {
    return false;
  }

}
