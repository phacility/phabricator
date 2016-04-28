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
    return pht('Edit Repository URI: %s', $object->getDisplayURI());
  }

  protected function getObjectEditShortText($object) {
    return $object->getDisplayURI();
  }

  protected function getObjectCreateShortText() {
    return pht('Create Repository URI');
  }

  protected function getObjectName() {
    return pht('Repository URI');
  }

  protected function getObjectViewURI($object) {
    $repository = $this->getRepository();
    return $repository->getPathURI('manage/uris/');
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
    );
  }

}
