<?php

final class NuanceItemSearchEngine
  extends PhabricatorApplicationSearchEngine {

  public function getApplicationClassName() {
    return 'PhabricatorNuanceApplication';
  }

  public function getResultTypeDescription() {
    return pht('Nuance Items');
  }

  public function newQuery() {
    return new NuanceItemQuery();
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
    return '/nuance/item/'.$path;
  }

  protected function getBuiltinQueryNames() {
    $names = array(
      'all' => pht('All Items'),
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
    array $items,
    PhabricatorSavedQuery $query,
    array $handles) {
    assert_instances_of($items, 'NuanceItem');

    $viewer = $this->requireViewer();

    $list = new PHUIObjectItemListView();
    $list->setUser($viewer);
    foreach ($items as $item) {
      $impl = $item->getImplementation();

      $view = id(new PHUIObjectItemView())
        ->setObjectName(pht('Item %d', $item->getID()))
        ->setHeader($item->getDisplayName())
        ->setHref($item->getURI());

      $view->addIcon(
        $impl->getItemTypeDisplayIcon(),
        $impl->getItemTypeDisplayName());

      $list->addItem($view);
    }

    $result = new PhabricatorApplicationSearchResultView();
    $result->setObjectList($list);
    $result->setNoDataString(pht('No items found.'));

    return $result;
  }

}
