<?php

final class DrydockResource extends DrydockDAO {

  protected $id;
  protected $phid;
  protected $blueprintClass;
  protected $status;

  protected $type;
  protected $name;
  protected $attributes   = array();
  protected $capabilities = array();
  protected $ownerPHID;

  private $blueprint;

  public function getConfiguration() {
    return array(
      self::CONFIG_AUX_PHID => true,
      self::CONFIG_SERIALIZATION => array(
        'attributes'    => self::SERIALIZATION_JSON,
        'capabilities'  => self::SERIALIZATION_JSON,
      ),
    ) + parent::getConfiguration();
  }

  public function generatePHID() {
    return PhabricatorPHID::generateNewPHID(
      PhabricatorPHIDConstants::PHID_TYPE_DRYR);
  }

  public function getAttribute($key, $default = null) {
    return idx($this->attributes, $key, $default);
  }

  public function setAttribute($key, $value) {
    $this->attributes[$key] = $value;
    return $this;
  }

  public function getCapability($key, $default = null) {
    return idx($this->capbilities, $key, $default);
  }

  public function getInterface(DrydockLease $lease, $type) {
    return $this->getBlueprint()->getInterface($this, $lease, $type);
  }

  public function getBlueprint() {
    if (empty($this->blueprint)) {
      $this->blueprint = newv($this->blueprintClass, array());
    }
    return $this->blueprint;
  }

}
