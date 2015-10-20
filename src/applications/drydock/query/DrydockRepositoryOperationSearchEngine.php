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

    return $query;
  }

  protected function buildCustomSearchFields() {
    return array(
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

      $item->addIcon($icon, $name);
      $item->addByline(
        array(
          pht('Via:'),
          ' ',
          $viewer->renderHandle($operation->getAuthorPHID()),
        ));

      $item->addAttribute(
        $viewer->renderHandle(
          $operation->getObjectPHID()));

      $item->addAttribute(
        $viewer->renderHandle(
          $operation->getRepositoryPHID()));

      $view->addItem($item);
    }

    $result = id(new PhabricatorApplicationSearchResultView())
      ->setObjectList($view)
      ->setNoDataString(pht('No matching operations.'));

    return $result;
  }

}
