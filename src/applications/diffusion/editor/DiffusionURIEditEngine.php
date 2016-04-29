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
    $uri = PhabricatorRepositoryURI::initializeNewURI();

    $repository = $this->getRepository();
    if ($repository) {
      $uri->setRepositoryPHID($repository->getPHID());
      $uri->attachRepository($repository);
    }

    return $uri;
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
      id(new PhabricatorHandlesEditField())
        ->setKey('repository')
        ->setAliases(array('repositoryPHID'))
        ->setLabel(pht('Repository'))
        ->setIsRequired(true)
        ->setIsConduitOnly(true)
        ->setTransactionType(
          PhabricatorRepositoryURITransaction::TYPE_REPOSITORY)
        ->setDescription(pht('The repository this URI is associated with.'))
        ->setConduitDescription(
          pht(
            'Create a URI in a given repository. This transaction type '.
            'must be present when creating a new URI and must not be '.
            'present when editing an existing URI.'))
        ->setConduitTypeDescription(
          pht('Repository PHID to create a new URI for.'))
        ->setSingleValue($object->getRepositoryPHID()),
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
      id(new PhabricatorHandlesEditField())
        ->setKey('credential')
        ->setAliases(array('credentialPHID'))
        ->setLabel(pht('Credential'))
        ->setIsConduitOnly(true)
        ->setTransactionType(
          PhabricatorRepositoryURITransaction::TYPE_CREDENTIAL)
        ->setDescription(
          pht('The credential to use when interacting with this URI.'))
        ->setConduitDescription(pht('Change the credential for this URI.'))
        ->setConduitTypeDescription(pht('New credential PHID, or null.'))
        ->setSingleValue($object->getCredentialPHID()),
      id(new PhabricatorBoolEditField())
        ->setKey('disable')
        ->setLabel(pht('Disabled'))
        ->setIsConduitOnly(true)
        ->setTransactionType(PhabricatorRepositoryURITransaction::TYPE_DISABLE)
        ->setDescription(pht('Active status of the URI.'))
        ->setConduitDescription(pht('Disable or activate the URI.'))
        ->setConduitTypeDescription(pht('True to disable the URI.'))
        ->setOptions(pht('Enable'), pht('Disable'))
        ->setValue($object->getIsDisabled()),
    );
  }

}
