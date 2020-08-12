<?php

final class DiffusionInternalCommitSearchEngine
  extends PhabricatorApplicationSearchEngine {

  public function getResultTypeDescription() {
    return pht('Diffusion Raw Commits');
  }

  public function getApplicationClassName() {
    return 'PhabricatorDiffusionApplication';
  }

  public function newQuery() {
    return new DiffusionCommitQuery();
  }

  protected function buildQueryFromParameters(array $map) {
    $query = $this->newQuery();

    if ($map['repositoryPHIDs']) {
      $query->withRepositoryPHIDs($map['repositoryPHIDs']);
    }

    return $query;
  }

  protected function buildCustomSearchFields() {
    return array(
      id(new PhabricatorSearchDatasourceField())
        ->setLabel(pht('Repositories'))
        ->setKey('repositoryPHIDs')
        ->setDatasource(new DiffusionRepositoryFunctionDatasource())
        ->setDescription(pht('Find commits in particular repositories.')),
    );
  }

  protected function getURI($path) {
    return null;
  }

  protected function renderResultList(
    array $commits,
    PhabricatorSavedQuery $query,
    array $handles) {
    return null;
  }

  protected function getObjectWireFieldsForConduit(
    $object,
    array $field_extensions,
    array $extension_data) {

    $commit = $object;
    $viewer = $this->requireViewer();

    $repository = $commit->getRepository();
    $identifier = $commit->getCommitIdentifier();

    id(new DiffusionRepositoryClusterEngine())
      ->setViewer($viewer)
      ->setRepository($repository)
      ->synchronizeWorkingCopyBeforeRead();

    $ref = id(new DiffusionLowLevelCommitQuery())
      ->setRepository($repository)
      ->withIdentifier($identifier)
      ->execute();

    return array(
      'ref' => $ref->newDictionary(),
    );
  }

}
