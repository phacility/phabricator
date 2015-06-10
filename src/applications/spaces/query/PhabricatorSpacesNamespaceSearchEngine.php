<?php

final class PhabricatorSpacesNamespaceSearchEngine
  extends PhabricatorApplicationSearchEngine {

  public function getApplicationClassName() {
    return 'PhabricatorSpacesApplication';
  }

  public function getResultTypeDescription() {
    return pht('Spaces');
  }

  public function buildSavedQueryFromRequest(AphrontRequest $request) {
    $saved = new PhabricatorSavedQuery();

    return $saved;
  }

  public function buildQueryFromSavedQuery(PhabricatorSavedQuery $saved) {
    $query = id(new PhabricatorSpacesNamespaceQuery());

    return $query;
  }

  public function buildSearchForm(
    AphrontFormView $form,
    PhabricatorSavedQuery $saved_query) {}

  protected function getURI($path) {
    return '/spaces/'.$path;
  }

  protected function getBuiltinQueryNames() {
    $names = array(
      'all' => pht('All Spaces'),
    );

    return $names;
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
    array $spaces,
    PhabricatorSavedQuery $query,
    array $handles) {
    assert_instances_of($spaces, 'PhabricatorSpacesNamespace');

    $viewer = $this->requireViewer();

    $list = new PHUIObjectItemListView();
    $list->setUser($viewer);
    foreach ($spaces as $space) {
      $item = id(new PHUIObjectItemView())
        ->setObjectName($space->getMonogram())
        ->setHeader($space->getNamespaceName())
        ->setHref('/'.$space->getMonogram());

      if ($space->getIsDefaultNamespace()) {
        $item->addIcon('fa-certificate', pht('Default Space'));
      }

      $list->addItem($item);
    }

    return $list;
  }

}
