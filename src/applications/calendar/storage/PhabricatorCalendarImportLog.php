<?php

final class PhabricatorCalendarImportLog
  extends PhabricatorCalendarDAO
  implements
    PhabricatorPolicyInterface,
    PhabricatorDestructibleInterface {

  protected $importPHID;
  protected $parameters = array();

  private $import = self::ATTACHABLE;
  private $logType = self::ATTACHABLE;

  protected function getConfiguration() {
    return array(
      self::CONFIG_SERIALIZATION => array(
        'parameters' => self::SERIALIZATION_JSON,
      ),
      self::CONFIG_KEY_SCHEMA => array(
        'key_import' => array(
          'columns' => array('importPHID'),
        ),
      ),
    ) + parent::getConfiguration();
  }

  public function getParameter($key, $default = null) {
    return idx($this->parameters, $key, $default);
  }

  public function setParameter($key, $value) {
    $this->parameters[$key] = $value;
    return $this;
  }

  public function getImport() {
    return $this->assertAttached($this->import);
  }

  public function attachImport(PhabricatorCalendarImport $import) {
    $this->import = $import;
    return $this;
  }

  public function getDisplayIcon(PhabricatorUser $viewer) {
    return $this->getLogType()->getDisplayIcon($viewer, $this);
  }

  public function getDisplayColor(PhabricatorUser $viewer) {
    return $this->getLogType()->getDisplayColor($viewer, $this);
  }

  public function getDisplayType(PhabricatorUser $viewer) {
    return $this->getLogType()->getDisplayType($viewer, $this);
  }

  public function getDisplayDescription(PhabricatorUser $viewer) {
    return $this->getLogType()->getDisplayDescription($viewer, $this);
  }

  public function getLogType() {
    return $this->assertAttached($this->logType);
  }

  public function attachLogType(PhabricatorCalendarImportLogType $type) {
    $this->logType = $type;
    return $this;
  }


/* -(  PhabricatorPolicyInterface  )----------------------------------------- */


  public function getCapabilities() {
    return array(
      PhabricatorPolicyCapability::CAN_VIEW,
    );
  }

  public function getPolicy($capability) {
    return PhabricatorPolicies::getMostOpenPolicy();
  }

  public function hasAutomaticCapability($capability, PhabricatorUser $viewer) {
    return false;
  }


/* -(  PhabricatorDestructibleInterface  )----------------------------------- */


  public function destroyObjectPermanently(
    PhabricatorDestructionEngine $engine) {
    $viewer = $engine->getViewer();
    $this->delete();
  }

}
