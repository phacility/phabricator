<?php

final class DifferentialRevisionEditEngine
  extends PhabricatorEditEngine {

  const ENGINECONST = 'differential.revision';

  public function getEngineName() {
    return pht('Revisions');
  }

  public function getSummaryHeader() {
    return pht('Configure Revision Forms');
  }

  public function getSummaryText() {
    return pht(
      'Configure creation and editing revision forms in Differential.');
  }

  public function getEngineApplicationClass() {
    return 'PhabricatorDifferentialApplication';
  }

  protected function supportsEditEngineConfiguration() {
    return false;
  }

  protected function newEditableObject() {
    $viewer = $this->getViewer();
    return DifferentialRevision::initializeNewRevision($viewer);
  }

  protected function newObjectQuery() {
    return new DifferentialRevisionQuery();
  }

  protected function getObjectCreateTitleText($object) {
    return pht('Create New Revision');
  }

  protected function getObjectEditTitleText($object) {
    return pht('Edit Revision: %s', $object->getTitle());
  }

  protected function getObjectEditShortText($object) {
    return $object->getMonogram();
  }

  protected function getObjectCreateShortText() {
    return pht('Create Revision');
  }

  protected function getObjectName() {
    return pht('Revision');
  }

  protected function getObjectViewURI($object) {
    return $object->getURI();
  }

  protected function buildCustomEditFields($object) {
    return array(
      id(new PhabricatorTextEditField())
        ->setKey('title')
        ->setLabel(pht('Title'))
        ->setTransactionType(
          DifferentialRevisionTitleTransaction::TRANSACTIONTYPE)
        ->setDescription(pht('The title of the revision.'))
        ->setConduitDescription(pht('Retitle the revision.'))
        ->setConduitTypeDescription(pht('New revision title.'))
        ->setValue($object->getTitle()),
    );
  }

}
