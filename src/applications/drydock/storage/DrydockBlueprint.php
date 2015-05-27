<?php

final class DrydockBlueprint extends DrydockDAO
  implements
    PhabricatorApplicationTransactionInterface,
    PhabricatorPolicyInterface,
    PhabricatorCustomFieldInterface {

  protected $className;
  protected $blueprintName;
  protected $viewPolicy;
  protected $editPolicy;
  protected $details = array();

  private $implementation = self::ATTACHABLE;
  private $customFields = self::ATTACHABLE;

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

  protected function getConfiguration() {
    return array(
      self::CONFIG_AUX_PHID => true,
      self::CONFIG_SERIALIZATION => array(
        'details' => self::SERIALIZATION_JSON,
      ),
      self::CONFIG_COLUMN_SCHEMA => array(
        'className' => 'text255',
        'blueprintName' => 'text255',
      ),
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
        pht(
          "Invalid class name for blueprint (got '%s')",
          $class));
    }
    return id(new $class())->attachInstance($this);
  }

  public function attachImplementation(DrydockBlueprintImplementation $impl) {
    $this->implementation = $impl;
    return $this;
  }

  public function getDetail($key, $default = null) {
    return idx($this->details, $key, $default);
  }

  public function setDetail($key, $value) {
    $this->details[$key] = $value;
    return $this;
  }


/* -(  PhabricatorApplicationTransactionInterface  )------------------------- */


  public function getApplicationTransactionEditor() {
    return new DrydockBlueprintEditor();
  }

  public function getApplicationTransactionObject() {
    return $this;
  }

  public function getApplicationTransactionTemplate() {
    return new DrydockBlueprintTransaction();
  }

  public function willRenderTimeline(
    PhabricatorApplicationTransactionView $timeline,
    AphrontRequest $request) {

    return $timeline;
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


/* -(  PhabricatorCustomFieldInterface  )------------------------------------ */


  public function getCustomFieldSpecificationForRole($role) {
    return array();
  }

  public function getCustomFieldBaseClass() {
    return 'DrydockBlueprintCustomField';
  }

  public function getCustomFields() {
    return $this->assertAttached($this->customFields);
  }

  public function attachCustomFields(PhabricatorCustomFieldAttachment $fields) {
    $this->customFields = $fields;
    return $this;
  }


}
