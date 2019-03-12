<?php

final class PhabricatorProjectTriggerSearchEngine
  extends PhabricatorApplicationSearchEngine {

  public function getResultTypeDescription() {
    return pht('Triggers');
  }

  public function getApplicationClassName() {
    return 'PhabricatorProjectApplication';
  }

  public function newQuery() {
    return new PhabricatorProjectTriggerQuery();
  }

  protected function buildCustomSearchFields() {
    return array();
  }

  protected function buildQueryFromParameters(array $map) {
    $query = $this->newQuery();

    return $query;
  }

  protected function getURI($path) {
    return '/project/trigger/'.$path;
  }

  protected function getBuiltinQueryNames() {
    $names = array();

    $names['all'] = pht('All');

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
    array $triggers,
    PhabricatorSavedQuery $query,
    array $handles) {
    assert_instances_of($triggers, 'PhabricatorProjectTrigger');
    $viewer = $this->requireViewer();

    $list = id(new PHUIObjectItemListView())
      ->setViewer($viewer);
    foreach ($triggers as $trigger) {
      $item = id(new PHUIObjectItemView())
        ->setObjectName($trigger->getObjectName())
        ->setHeader($trigger->getDisplayName())
        ->setHref($trigger->getURI());

      $list->addItem($item);
    }

    return id(new PhabricatorApplicationSearchResultView())
      ->setObjectList($list)
      ->setNoDataString(pht('No triggers found.'));
  }

}
