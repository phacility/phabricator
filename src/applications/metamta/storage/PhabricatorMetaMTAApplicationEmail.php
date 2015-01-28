<?php

final class PhabricatorMetaMTAApplicationEmail
  extends PhabricatorMetaMTADAO
  implements PhabricatorPolicyInterface {

  protected $applicationPHID;
  protected $address;
  protected $configData;

  private $application = self::ATTACHABLE;

  const CONFIG_DEFAULT_AUTHOR = 'config:default:author';

  protected function getConfiguration() {
    return array(
      self::CONFIG_AUX_PHID => true,
      self::CONFIG_SERIALIZATION => array(
        'configData' => self::SERIALIZATION_JSON,
      ),
      self::CONFIG_COLUMN_SCHEMA => array(
        'address' => 'sort128',
      ),
      self::CONFIG_KEY_SCHEMA => array(
        'key_address' => array(
          'columns' => array('address'),
          'unique' => true,
        ),
        'key_application' => array(
          'columns' => array('applicationPHID'),
        ),
      ),
    ) + parent::getConfiguration();
  }

  public function generatePHID() {
    return PhabricatorPHID::generateNewPHID(
      PhabricatorMetaMTAApplicationEmailPHIDType::TYPECONST);
  }

  public static function initializeNewAppEmail(PhabricatorUser $actor) {
    return id(new PhabricatorMetaMTAApplicationEmail())
      ->setConfigData(array());
  }

  public function attachApplication(PhabricatorApplication $app) {
    $this->application = $app;
    return $this;
  }

  public function getApplication() {
    return self::assertAttached($this->application);
  }

  public function setConfigValue($key, $value) {
    $this->configData[$key] = $value;
    return $this;
  }

  public function getConfigValue($key, $default = null) {
    return idx($this->configData, $key, $default);
  }

/* -(  PhabricatorPolicyInterface  )----------------------------------------- */


  public function getCapabilities() {
    return array(
      PhabricatorPolicyCapability::CAN_VIEW,
      PhabricatorPolicyCapability::CAN_EDIT,
    );
  }

  public function getPolicy($capability) {
    return $this->getApplication()->getPolicy($capability);
  }

  public function hasAutomaticCapability(
    $capability,
    PhabricatorUser $viewer) {

    return $this->getApplication()->hasAutomaticCapability(
      $capability,
      $viewer);
  }

  public function describeAutomaticCapability($capability) {
    return $this->getApplication()->describeAutomaticCapability($capability);
  }

}
