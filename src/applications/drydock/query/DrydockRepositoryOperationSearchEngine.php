<?php

final class DrydockRepositoryOperationSearchEngine
  extends PhabricatorApplicationSearchEngine {

  public function getResultTypeDescription() {
    return pht('Drydock Repository Operations');
  }

  public function getApplicationClassName() {
    return 'PhabricatorDrydockApplication';
  }

  public function newQuery() {
    return id(new DrydockRepositoryOperationQuery());
  }

  protected function buildQueryFromParameters(array $map) {
    $query = $this->newQuery();

    if ($map['repositoryPHIDs']) {
      $query->withRepositoryPHIDs($map['repositoryPHIDs']);
    }

    if ($map['authorPHIDs']) {
      $query->withAuthorPHIDs($map['authorPHIDs']);
    }

    if ($map['states']) {
      $query->withOperationStates($map['states']);
    }

    return $query;
  }

  protected function buildCustomSearchFields() {
    return array(
      id(new PhabricatorSearchDatasourceField())
        ->setLabel(pht('Repositories'))
        ->setKey('repositoryPHIDs')
        ->setAliases(array('repository', 'repositories', 'repositoryPHID'))
        ->setDatasource(new DiffusionRepositoryFunctionDatasource()),

      // NOTE: Repository operations aren't necessarily created by a real
      // user, but for now they normally are. Just use a user typeahead until
      // more use cases arise.
      id(new PhabricatorUsersSearchField())
        ->setLabel(pht('Authors'))
        ->setKey('authorPHIDs')
        ->setAliases(array('author', 'authors', 'authorPHID')),
      id(new PhabricatorSearchCheckboxesField())
        ->setLabel(pht('States'))
        ->setKey('states')
        ->setAliases(array('state'))
        ->setOptions(DrydockRepositoryOperation::getOperationStateNameMap()),
    );
  }

  protected function getURI($path) {
    return '/drydock/operation/'.$path;
  }

  protected function getBuiltinQueryNames() {
    return array(
      'all' => pht('All Operations'),
    );
  }

  public function buildSavedQueryFromBuiltin($query_key) {
    $query = $this->newSavedQuery();
    $query->setQueryKey($query_key);

    switch ($query_key) {
      case 'all':
        return $query;
    }

    return parent::buildSavedQueryFromBuiltin($query_key);
  }

  protected function renderResultList(
    array $operations,
    PhabricatorSavedQuery $query,
    array $handles) {
    assert_instances_of($operations, 'DrydockRepositoryOperation');

    $viewer = $this->requireViewer();

    $view = new PHUIObjectItemListView();
    foreach ($operations as $operation) {
      $id = $operation->getID();

      $item = id(new PHUIObjectItemView())
        ->setHeader($operation->getOperationDescription($viewer))
        ->setHref($this->getApplicationURI("operation/{$id}/"))
        ->setObjectName(pht('Repository Operation %d', $id));

      $state = $operation->getOperationState();

      $icon = DrydockRepositoryOperation::getOperationStateIcon($state);
      $name = DrydockRepositoryOperation::getOperationStateName($state);

      $item->setStatusIcon($icon, $name);


      $created = phabricator_datetime($operation->getDateCreated(), $viewer);
      $item->addIcon(null, $created);

      $item->addByline(
        array(
          pht('Via:'),
          ' ',
          $viewer->renderHandle($operation->getAuthorPHID()),
        ));

      $object_phid = $operation->getObjectPHID();
      $repository_phid = $operation->getRepositoryPHID();

      $item->addAttribute($viewer->renderHandle($object_phid));

      if ($repository_phid !== $object_phid) {
        $item->addAttribute($viewer->renderHandle($repository_phid));
      }

      $view->addItem($item);
    }

    $result = id(new PhabricatorApplicationSearchResultView())
      ->setObjectList($view)
      ->setNoDataString(pht('No matching operations.'));

    return $result;
  }

}
