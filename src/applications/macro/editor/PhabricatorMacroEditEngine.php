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
    return pht('Edit %s', $object->getName());
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

  protected function buildCustomEditFields($object) {

    return array(
      id(new PhabricatorTextEditField())
        ->setKey('name')
        ->setLabel(pht('Name'))
        ->setDescription(pht('Macro name.'))
        ->setConduitDescription(pht('Rename the macro.'))
        ->setConduitTypeDescription(pht('New macro name.'))
        ->setTransactionType(PhabricatorMacroNameTransaction::TRANSACTIONTYPE)
        ->setValue($object->getName()),
      id(new PhabricatorFileEditField())
        ->setKey('filePHID')
        ->setLabel(pht('Image File'))
        ->setDescription(pht('Image file to import.'))
        ->setTransactionType(PhabricatorMacroFileTransaction::TRANSACTIONTYPE)
        ->setConduitDescription(pht('File PHID to import.'))
        ->setConduitTypeDescription(pht('File PHID.')),
    );

  }

}
