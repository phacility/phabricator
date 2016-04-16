<?php

final class DiffusionRepositoryEditEngine
  extends PhabricatorEditEngine {

  const ENGINECONST = 'diffusion.repository';

  public function isEngineConfigurable() {
    return false;
  }

  public function getEngineName() {
    return pht('Repositories');
  }

  public function getSummaryHeader() {
    return pht('Edit Repositories');
  }

  public function getSummaryText() {
    return pht('Creates and edits repositories.');
  }

  public function getEngineApplicationClass() {
    return 'PhabricatorDiffusionApplication';
  }

  protected function newEditableObject() {
    $viewer = $this->getViewer();
    return PhabricatorRepository::initializeNewRepository($viewer);
  }

  protected function newObjectQuery() {
    return new PhabricatorRepositoryQuery();
  }

  protected function getObjectCreateTitleText($object) {
    return pht('Create Repository');
  }

  protected function getObjectCreateButtonText($object) {
    return pht('Create Repository');
  }

  protected function getObjectEditTitleText($object) {
    return pht('Edit Repository: %s', $object->getName());
  }

  protected function getObjectEditShortText($object) {
    return $object->getDisplayName();
  }

  protected function getObjectCreateShortText() {
    return pht('Create Repository');
  }

  protected function getObjectName() {
    return pht('Repository');
  }

  protected function getObjectViewURI($object) {
    return $object->getPathURI('manage/');
  }

  protected function getCreateNewObjectPolicy() {
    return $this->getApplication()->getPolicy(
      DiffusionCreateRepositoriesCapability::CAPABILITY);
  }

  protected function buildCustomEditFields($object) {
    return array(
      id(new PhabricatorTextEditField())
        ->setKey('name')
        ->setLabel(pht('Name'))
        ->setIsRequired(true)
        ->setTransactionType(PhabricatorRepositoryTransaction::TYPE_NAME)
        ->setDescription(pht('The repository name.'))
        ->setConduitDescription(pht('Rename the repository.'))
        ->setConduitTypeDescription(pht('New repository name.'))
        ->setValue($object->getName()),
    );
  }

}
