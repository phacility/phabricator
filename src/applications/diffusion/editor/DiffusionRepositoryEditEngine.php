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
    $viewer = $this->getViewer();

    $policies = id(new PhabricatorPolicyQuery())
      ->setViewer($viewer)
      ->setObject($object)
      ->execute();

    return array(
      id(new PhabricatorSelectEditField())
        ->setKey('vcs')
        ->setLabel(pht('Version Control System'))
        ->setTransactionType(PhabricatorRepositoryTransaction::TYPE_VCS)
        ->setIsConduitOnly(true)
        ->setIsCopyable(true)
        ->setOptions(PhabricatorRepositoryType::getAllRepositoryTypes())
        ->setDescription(pht('Underlying repository version control system.'))
        ->setConduitDescription(
          pht(
            'Choose which version control system to use when creating a '.
            'repository.'))
        ->setConduitTypeDescription(pht('Version control system selection.'))
        ->setValue($object->getVersionControlSystem()),
      id(new PhabricatorTextEditField())
        ->setKey('name')
        ->setLabel(pht('Name'))
        ->setIsRequired(true)
        ->setTransactionType(PhabricatorRepositoryTransaction::TYPE_NAME)
        ->setDescription(pht('The repository name.'))
        ->setConduitDescription(pht('Rename the repository.'))
        ->setConduitTypeDescription(pht('New repository name.'))
        ->setValue($object->getName()),
      id(new PhabricatorTextEditField())
        ->setKey('callsign')
        ->setLabel(pht('Callsign'))
        ->setTransactionType(PhabricatorRepositoryTransaction::TYPE_CALLSIGN)
        ->setDescription(pht('The repository callsign.'))
        ->setConduitDescription(pht('Change the repository callsign.'))
        ->setConduitTypeDescription(pht('New repository callsign.'))
        ->setValue($object->getCallsign()),
      id(new PhabricatorTextEditField())
        ->setKey('shortName')
        ->setLabel(pht('Short Name'))
        ->setTransactionType(PhabricatorRepositoryTransaction::TYPE_SLUG)
        ->setDescription(pht('Short, unique repository name.'))
        ->setConduitDescription(pht('Change the repository short name.'))
        ->setConduitTypeDescription(pht('New short name for the repository.'))
        ->setValue($object->getRepositorySlug()),
      id(new PhabricatorRemarkupEditField())
        ->setKey('description')
        ->setLabel(pht('Description'))
        ->setTransactionType(PhabricatorRepositoryTransaction::TYPE_DESCRIPTION)
        ->setDescription(pht('Repository description.'))
        ->setConduitDescription(pht('Change the repository description.'))
        ->setConduitTypeDescription(pht('New repository description.'))
        ->setValue($object->getDetail('description')),
      id(new PhabricatorTextEditField())
        ->setKey('encoding')
        ->setLabel(pht('Text Encoding'))
        ->setIsCopyable(true)
        ->setTransactionType(PhabricatorRepositoryTransaction::TYPE_ENCODING)
        ->setDescription(pht('Default text encoding.'))
        ->setConduitDescription(pht('Change the default text encoding.'))
        ->setConduitTypeDescription(pht('New text encoding.'))
        ->setValue($object->getDetail('encoding')),
      id(new PhabricatorSelectEditField())
        ->setKey('status')
        ->setLabel(pht('Status'))
        ->setTransactionType(PhabricatorRepositoryTransaction::TYPE_ACTIVATE)
        ->setIsConduitOnly(true)
        ->setOptions(PhabricatorRepository::getStatusNameMap())
        ->setDescription(pht('Active or inactive status.'))
        ->setConduitDescription(pht('Active or deactivate the repository.'))
        ->setConduitTypeDescription(pht('New repository status.'))
        ->setValue($object->getStatus()),
      id(new PhabricatorTextEditField())
        ->setKey('defaultBranch')
        ->setLabel(pht('Default Branch'))
        ->setTransactionType(
          PhabricatorRepositoryTransaction::TYPE_DEFAULT_BRANCH)
        ->setIsCopyable(true)
        ->setDescription(pht('Default branch name.'))
        ->setConduitDescription(pht('Set the default branch name.'))
        ->setConduitTypeDescription(pht('New default branch name.'))
        ->setValue($object->getDetail('default-branch')),
      id(new PhabricatorPolicyEditField())
        ->setKey('policy.push')
        ->setLabel(pht('Push Policy'))
        ->setAliases(array('push'))
        ->setIsCopyable(true)
        ->setCapability(DiffusionPushCapability::CAPABILITY)
        ->setPolicies($policies)
        ->setTransactionType(PhabricatorRepositoryTransaction::TYPE_PUSH_POLICY)
        ->setDescription(
          pht('Controls who can push changes to the repository.'))
        ->setConduitDescription(
          pht('Change the push policy of the repository.'))
        ->setConduitTypeDescription(pht('New policy PHID or constant.'))
        ->setValue($object->getPolicy(DiffusionPushCapability::CAPABILITY)),
    );
  }

}
