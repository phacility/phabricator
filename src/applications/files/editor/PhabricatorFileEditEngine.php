<?php

final class PhabricatorFileEditEngine
  extends PhabricatorEditEngine {

  const ENGINECONST = 'files.file';

  public function getEngineName() {
    return pht('Files');
  }

  protected function supportsEditEngineConfiguration() {
    return false;
  }

  protected function getCreateNewObjectPolicy() {
    // TODO: For now, this EditEngine can only edit objects, since there is
    // a lot of complexity in dealing with file data during file creation.
    return PhabricatorPolicies::POLICY_NOONE;
  }

  public function getSummaryHeader() {
    return pht('Configure Files Forms');
  }

  public function getSummaryText() {
    return pht('Configure creation and editing forms in Files.');
  }

  public function getEngineApplicationClass() {
    return 'PhabricatorFilesApplication';
  }

  protected function newEditableObject() {
    return PhabricatorFile::initializeNewFile();
  }

  protected function newObjectQuery() {
    $query = new PhabricatorFileQuery();
    $query->withIsDeleted(false);
    return $query;
  }

  protected function getObjectCreateTitleText($object) {
    return pht('Create New File');
  }

  protected function getObjectEditTitleText($object) {
    return pht('Edit File: %s', $object->getName());
  }

  protected function getObjectEditShortText($object) {
    return $object->getMonogram();
  }

  protected function getObjectCreateShortText() {
    return pht('Create File');
  }

  protected function getObjectName() {
    return pht('File');
  }

  protected function getObjectViewURI($object) {
    return $object->getURI();
  }

  protected function buildCustomEditFields($object) {
    return array(
      id(new PhabricatorTextEditField())
        ->setKey('name')
        ->setLabel(pht('Name'))
        ->setTransactionType(PhabricatorFileNameTransaction::TRANSACTIONTYPE)
        ->setDescription(pht('The name of the file.'))
        ->setConduitDescription(pht('Rename the file.'))
        ->setConduitTypeDescription(pht('New file name.'))
        ->setValue($object->getName()),
      id(new PhabricatorTextEditField())
        ->setKey('alt')
        ->setLabel(pht('Alt Text'))
        ->setTransactionType(PhabricatorFileAltTextTransaction::TRANSACTIONTYPE)
        ->setDescription(pht('Human-readable file description.'))
        ->setConduitDescription(pht('Set the file alt text.'))
        ->setConduitTypeDescription(pht('New alt text.'))
        ->setValue($object->getCustomAltText()),
    );
  }

}
