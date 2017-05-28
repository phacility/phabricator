<?php

final class PhabricatorMacroEditEngine
  extends PhabricatorEditEngine {

  const ENGINECONST = 'macro.image';

  public function getEngineName() {
    return pht('Macro Image');
  }

  public function getSummaryHeader() {
    return pht('Configure Macro Image Forms');
  }

  public function getSummaryText() {
    return pht('Configure creation and editing of Macro images.');
  }

  public function getEngineApplicationClass() {
    return 'PhabricatorMacroApplication';
  }

  public function isEngineConfigurable() {
    return false;
  }

  protected function newEditableObject() {
    $viewer = $this->getViewer();
    return PhabricatorFileImageMacro::initializeNewFileImageMacro($viewer);
  }

  protected function newObjectQuery() {
    return new PhabricatorMacroQuery();
  }

  protected function getObjectCreateTitleText($object) {
    return pht('Create New Macro');
  }

  protected function getObjectEditTitleText($object) {
    return pht('Edit Macro %s', $object->getName());
  }

  protected function getObjectEditShortText($object) {
    return $object->getName();
  }

  protected function getObjectCreateShortText() {
    return pht('Create Macro');
  }

  protected function getObjectName() {
    return pht('Macro');
  }

  protected function getObjectViewURI($object) {
    return $object->getViewURI();
  }

  protected function getEditorURI() {
    return $this->getApplication()->getApplicationURI('edit/');
  }

  protected function getCreateNewObjectPolicy() {
    return $this->getApplication()->getPolicy(
      PhabricatorMacroManageCapability::CAPABILITY);
  }

  protected function willConfigureFields($object, array $fields) {
    if ($this->getIsCreate()) {
      $subscribers_field = idx($fields,
        PhabricatorSubscriptionsEditEngineExtension::FIELDKEY);
      if ($subscribers_field) {
        // By default, hide the subscribers field when creating a macro
        // because it makes the workflow SO HARD and wastes SO MUCH TIME.
        $subscribers_field->setIsHidden(true);
      }
    }
    return $fields;
  }

  protected function buildCustomEditFields($object) {

    return array(
      id(new PhabricatorTextEditField())
        ->setKey('name')
        ->setLabel(pht('Name'))
        ->setDescription(pht('Macro name.'))
        ->setConduitDescription(pht('Name of the macro.'))
        ->setConduitTypeDescription(pht('New macro name.'))
        ->setTransactionType(PhabricatorMacroNameTransaction::TRANSACTIONTYPE)
        ->setIsRequired(true)
        ->setValue($object->getName()),
      id(new PhabricatorFileEditField())
        ->setKey('filePHID')
        ->setLabel(pht('Image File'))
        ->setDescription(pht('Image file to import.'))
        ->setTransactionType(PhabricatorMacroFileTransaction::TRANSACTIONTYPE)
        ->setConduitDescription(pht('File PHID to import.'))
        ->setConduitTypeDescription(pht('File PHID.'))
        ->setValue($object->getFilePHID()),
    );

  }

}
