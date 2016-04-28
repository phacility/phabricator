<?php

final class DiffusionURIEditEngine
  extends PhabricatorEditEngine {

  const ENGINECONST = 'diffusion.uri';

  private $repository;

  public function setRepository(PhabricatorRepository $repository) {
    $this->repository = $repository;
    return $this;
  }

  public function getRepository() {
    return $this->repository;
  }

  public function isEngineConfigurable() {
    return false;
  }

  public function getEngineName() {
    return pht('Repository URIs');
  }

  public function getSummaryHeader() {
    return pht('Edit Repository URI');
  }

  public function getSummaryText() {
    return pht('Creates and edits repository URIs.');
  }

  public function getEngineApplicationClass() {
    return 'PhabricatorDiffusionApplication';
  }

  protected function newEditableObject() {
    $repository = $this->getRepository();
    return PhabricatorRepositoryURI::initializeNewURI($repository);
  }

  protected function newObjectQuery() {
    return new PhabricatorRepositoryURIQuery();
  }

  protected function getObjectCreateTitleText($object) {
    return pht('Create Repository URI');
  }

  protected function getObjectCreateButtonText($object) {
    return pht('Create Repository URI');
  }

  protected function getObjectEditTitleText($object) {
    return pht('Edit Repository URI %d', $object->getID());
  }

  protected function getObjectEditShortText($object) {
    return pht('URI %d', $object->getID());
  }

  protected function getObjectCreateShortText() {
    return pht('Create Repository URI');
  }

  protected function getObjectName() {
    return pht('Repository URI');
  }

  protected function getObjectViewURI($object) {
    return $object->getViewURI();
  }

  protected function buildCustomEditFields($object) {
    $viewer = $this->getViewer();

    return array(
      id(new PhabricatorTextEditField())
        ->setKey('uri')
        ->setLabel(pht('URI'))
        ->setIsRequired(true)
        ->setTransactionType(PhabricatorRepositoryURITransaction::TYPE_URI)
        ->setDescription(pht('The repository URI.'))
        ->setConduitDescription(pht('Change the repository URI.'))
        ->setConduitTypeDescription(pht('New repository URI.'))
        ->setValue($object->getURI()),
      id(new PhabricatorSelectEditField())
        ->setKey('io')
        ->setLabel(pht('I/O Type'))
        ->setTransactionType(PhabricatorRepositoryURITransaction::TYPE_IO)
        ->setDescription(pht('URI I/O behavior.'))
        ->setConduitDescription(pht('Adjust I/O behavior.'))
        ->setConduitTypeDescription(pht('New I/O behavior.'))
        ->setValue($object->getIOType())
        ->setOptions($object->getAvailableIOTypeOptions()),
      id(new PhabricatorSelectEditField())
        ->setKey('display')
        ->setLabel(pht('Display Type'))
        ->setTransactionType(PhabricatorRepositoryURITransaction::TYPE_DISPLAY)
        ->setDescription(pht('URI display behavior.'))
        ->setConduitDescription(pht('Change display behavior.'))
        ->setConduitTypeDescription(pht('New display behavior.'))
        ->setValue($object->getDisplayType())
        ->setOptions($object->getAvailableDisplayTypeOptions()),
    );
  }

}
