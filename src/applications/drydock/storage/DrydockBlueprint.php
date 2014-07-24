<?php

final class DrydockBlueprint extends DrydockDAO
  implements PhabricatorPolicyInterface {

  protected $className;
  protected $blueprintName;
  protected $viewPolicy;
  protected $editPolicy;
  protected $details = array();

  private $implementation = self::ATTACHABLE;

  public static function initializeNewBlueprint(PhabricatorUser $actor) {
    $app = id(new PhabricatorApplicationQuery())
      ->setViewer($actor)
      ->withClasses(array('PhabricatorDrydockApplication'))
      ->executeOne();

    $view_policy = $app->getPolicy(
      DrydockDefaultViewCapability::CAPABILITY);
    $edit_policy = $app->getPolicy(
      DrydockDefaultEditCapability::CAPABILITY);

    return id(new DrydockBlueprint())
      ->setViewPolicy($view_policy)
      ->setEditPolicy($edit_policy)
      ->setBlueprintName('');
  }

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
      DrydockBlueprintPHIDType::TYPECONST);
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
    return false;
  }

  public function describeAutomaticCapability($capability) {
    return null;
  }

}
