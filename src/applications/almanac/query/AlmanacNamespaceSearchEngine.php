<?php

final class AlmanacNamespaceSearchEngine
  extends PhabricatorApplicationSearchEngine {

  public function getResultTypeDescription() {
    return pht('Almanac Namespaces');
  }

  public function getApplicationClassName() {
    return 'PhabricatorAlmanacApplication';
  }

  public function newQuery() {
    return new AlmanacNamespaceQuery();
  }

  protected function buildCustomSearchFields() {
    return array(
      id(new PhabricatorSearchTextField())
        ->setLabel(pht('Name Contains'))
        ->setKey('match')
        ->setDescription(pht('Search for namespaces by name substring.')),
    );
  }

  protected function buildQueryFromParameters(array $map) {
    $query = $this->newQuery();

    if ($map['match'] !== null) {
      $query->withNameNgrams($map['match']);
    }

    return $query;
  }

  protected function getURI($path) {
    return '/almanac/namespace/'.$path;
  }

  protected function getBuiltinQueryNames() {
    $names = array(
      'all' => pht('All Namespaces'),
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
    array $namespaces,
    PhabricatorSavedQuery $query,
    array $handles) {
    assert_instances_of($namespaces, 'AlmanacNamespace');

    $viewer = $this->requireViewer();

    $list = new PHUIObjectItemListView();
    $list->setUser($viewer);
    foreach ($namespaces as $namespace) {
      $id = $namespace->getID();

      $item = id(new PHUIObjectItemView())
        ->setObjectName(pht('Namespace %d', $id))
        ->setHeader($namespace->getName())
        ->setHref($this->getApplicationURI("namespace/{$id}/"))
        ->setObject($namespace);

      $list->addItem($item);
    }

    $result = new PhabricatorApplicationSearchResultView();
    $result->setObjectList($list);
    $result->setNoDataString(pht('No Almanac namespaces found.'));

    return $result;
  }
}
