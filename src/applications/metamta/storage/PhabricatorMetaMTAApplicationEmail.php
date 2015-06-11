<?php

final class PhabricatorMetaMTAApplicationEmail
  extends PhabricatorMetaMTADAO
  implements
    PhabricatorPolicyInterface,
    PhabricatorApplicationTransactionInterface,
    PhabricatorDestructibleInterface,
    PhabricatorSpacesInterface {

  protected $applicationPHID;
  protected $address;
  protected $configData;
  protected $spacePHID;

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
      ->setSpacePHID($actor->getDefaultSpacePHID())
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


  public function getInUseMessage() {
    $applications = PhabricatorApplication::getAllApplications();
    $applications = mpull($applications, null, 'getPHID');
    $application = idx(
      $applications,
      $this->getApplicationPHID());
    if ($application) {
      $message = pht(
        'The address %s is configured to be used by the %s Application.',
        $this->getAddress(),
        $application->getName());
    } else {
      $message = pht(
        'The address %s is configured to be used by an application.',
        $this->getAddress());
    }

    return $message;
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


/* -(  PhabricatorApplicationTransactionInterface  )------------------------- */


  public function getApplicationTransactionEditor() {
    return new PhabricatorMetaMTAApplicationEmailEditor();
  }

  public function getApplicationTransactionObject() {
    return $this;
  }

  public function getApplicationTransactionTemplate() {
    return new PhabricatorMetaMTAApplicationEmailTransaction();
  }

  public function willRenderTimeline(
    PhabricatorApplicationTransactionView $timeline,
    AphrontRequest $request) {
    return $timeline;
  }


/* -(  PhabricatorDestructibleInterface  )----------------------------------- */


  public function destroyObjectPermanently(
    PhabricatorDestructionEngine $engine) {
    $this->delete();
  }


/* -(  PhabricatorSpacesInterface  )----------------------------------------- */


  public function getSpacePHID() {
    return $this->spacePHID;
  }

}
