<?php

final class DrydockBlueprint extends DrydockDAO
  implements PhabricatorPolicyInterface {

  protected $phid;
  protected $className;
  protected $viewPolicy;
  protected $editPolicy;
  protected $details;

  private $implementation = self::ATTACHABLE;

  public function getConfiguration() {
    return array(
      self::CONFIG_AUX_PHID => true,
      self::CONFIG_SERIALIZATION => array(
        'details' => self::SERIALIZATION_JSON,
      )
    ) + parent::getConfiguration();
  }

  public function generatePHID() {
    return PhabricatorPHID::generateNewPHID(
      DrydockPHIDTypeBlueprint::TYPECONST);
  }

  public function getImplementation() {
    $class = $this->className;
    $implementations =
      DrydockBlueprintImplementation::getAllBlueprintImplementations();
    if (!isset($implementations[$class])) {
      throw new Exception(
        "Invalid class name for blueprint (got '".$class."')");
    }
    return id(new $class())->attachInstance($this);
  }

  public function attachImplementation(DrydockBlueprintImplementation $impl) {
    $this->implementation = $impl;
    return $this;
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
        return $this->getViewPolicy();
      case PhabricatorPolicyCapability::CAN_EDIT:
        return $this->getEditPolicy();
    }
  }

  public function hasAutomaticCapability($capability, PhabricatorUser $viewer) {
    switch ($capability) {
      case PhabricatorPolicyCapability::CAN_VIEW:
      case PhabricatorPolicyCapability::CAN_EDIT:
        return $viewer->getIsAdmin();
    }
  }

  public function describeAutomaticCapability($capability) {
    switch ($capability) {
      case PhabricatorPolicyCapability::CAN_VIEW:
        return pht('Administrators can always view blueprints.');
      case PhabricatorPolicyCapability::CAN_EDIT:
        return pht('Administrators can always edit blueprints.');
    }
  }
}
