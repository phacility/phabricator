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

  protected function newEditableObjectFromConduit(array $raw_xactions) {
    $type = null;
    foreach ($raw_xactions as $raw_xaction) {
      if ($raw_xaction['type'] !== 'type') {
        continue;
      }

      $type = $raw_xaction['value'];
    }

    if ($type === null) {
      throw new Exception(
        pht(
          'When creating a new Drydock blueprint via the Conduit API, you '.
          'must provide a "type" transaction to select a type.'));
    }

    $map = DrydockBlueprintImplementation::getAllBlueprintImplementations();
    if (!isset($map[$type])) {
      throw new Exception(
        pht(
          'Blueprint type "%s" is unrecognized. Valid types are: %s.',
          $type,
          implode(', ', array_keys($map))));
    }

    $impl = clone $map[$type];
    $this->setBlueprintImplementation($impl);

    return $this->newEditableObject();
  }

  protected function newEditableObjectForDocumentation() {
    // In order to generate the proper list of fields/transactions for a
    // blueprint, a blueprint's type needs to be known upfront, and there's
    // currently no way to pre-specify the type. Hardcoding an implementation
    // here prevents the fatal on the Conduit API page and allows transactions
    // to be edited.
    $impl = new DrydockWorkingCopyBlueprintImplementation();
    $this->setBlueprintImplementation($impl);
    return $this->newEditableObject();
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
      // This field appears in the web UI
      id(new PhabricatorStaticEditField())
        ->setKey('displayType')
        ->setLabel(pht('Blueprint Type'))
        ->setDescription(pht('Type of blueprint.'))
        ->setValue($impl->getBlueprintName()),
      id(new PhabricatorTextEditField())
        ->setKey('type')
        ->setLabel(pht('Type'))
        ->setIsFormField(false)
        ->setTransactionType(
          DrydockBlueprintTypeTransaction::TRANSACTIONTYPE)
        ->setDescription(pht('When creating a blueprint, set the type.'))
        ->setConduitDescription(pht('Set the blueprint type.'))
        ->setConduitTypeDescription(pht('Blueprint type.'))
        ->setValue($object->getClassName()),
      id(new PhabricatorTextEditField())
        ->setKey('name')
        ->setLabel(pht('Name'))
        ->setDescription(pht('Name of the blueprint.'))
        ->setTransactionType(DrydockBlueprintNameTransaction::TRANSACTIONTYPE)
        ->setIsRequired(true)
        ->setValue($object->getBlueprintName()),
    );
  }

}
