<?php

final class PhabricatorMetaMTAApplicationEmail
  extends PhabricatorMetaMTADAO
  implements PhabricatorPolicyInterface {

  protected $applicationPHID;
  protected $address;
  protected $configData;

  private $application = self::ATTACHABLE;

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
