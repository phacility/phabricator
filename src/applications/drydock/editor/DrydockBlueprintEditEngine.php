<?php

final class DrydockBlueprintEditEngine
  extends PhabricatorEditEngine {

  private $blueprintImplementation;

  const ENGINECONST = 'drydock.blueprint';

  public function isEngineConfigurable() {
    return false;
  }

  public function getEngineName() {
    return pht('Drydock Blueprints');
  }

  public function getSummaryHeader() {
    return pht('Edit Drydock Blueprint Configurations');
  }

  public function getSummaryText() {
    return pht('This engine is used to edit Drydock blueprints.');
  }

  public function getEngineApplicationClass() {
    return 'PhabricatorDrydockApplication';
  }

  public function setBlueprintImplementation(
    DrydockBlueprintImplementation $impl) {
    $this->blueprintImplementation = $impl;
    return $this;
  }

  public function getBlueprintImplementation() {
    return $this->blueprintImplementation;
  }

  protected function newEditableObject() {
    $viewer = $this->getViewer();
    $blueprint = DrydockBlueprint::initializeNewBlueprint($viewer);

    $impl = $this->getBlueprintImplementation();
    if ($impl) {
      $blueprint
        ->setClassName(get_class($impl))
        ->attachImplementation(clone $impl);
    }

    return $blueprint;
  }

  protected function newObjectQuery() {
    return new DrydockBlueprintQuery();
  }

  protected function getObjectCreateTitleText($object) {
    return pht('Create Blueprint');
  }

  protected function getObjectCreateButtonText($object) {
    return pht('Create Blueprint');
  }

  protected function getObjectEditTitleText($object) {
    return pht('Edit Blueprint: %s', $object->getBlueprintName());
  }

  protected function getObjectEditShortText($object) {
    return pht('Edit Blueprint');
  }

  protected function getObjectCreateShortText() {
    return pht('Create Blueprint');
  }

  protected function getObjectName() {
    return pht('Blueprint');
  }

  protected function getEditorURI() {
    return '/drydock/blueprint/edit/';
  }

  protected function getObjectCreateCancelURI($object) {
    return '/drydock/blueprint/';
  }

  protected function getObjectViewURI($object) {
    $id = $object->getID();
    return "/drydock/blueprint/{$id}/";
  }

  protected function getCreateNewObjectPolicy() {
    return $this->getApplication()->getPolicy(
      DrydockCreateBlueprintsCapability::CAPABILITY);
  }

  protected function buildCustomEditFields($object) {
    $impl = $object->getImplementation();

    return array(
      id(new PhabricatorStaticEditField())
        ->setKey('type')
        ->setLabel(pht('Blueprint Type'))
        ->setDescription(pht('Type of blueprint.'))
        ->setValue($impl->getBlueprintName()),
      id(new PhabricatorTextEditField())
        ->setKey('name')
        ->setLabel(pht('Name'))
        ->setDescription(pht('Name of the blueprint.'))
        ->setTransactionType(DrydockBlueprintTransaction::TYPE_NAME)
        ->setIsRequired(true)
        ->setValue($object->getBlueprintName()),
    );
  }

}
