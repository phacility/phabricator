<?php

final class PhabricatorEditEngineSearchEngine
  extends PhabricatorApplicationSearchEngine {

  public function getResultTypeDescription() {
    return pht('Edit Engines');
  }

  public function getApplicationClassName() {
    return 'PhabricatorTransactionsApplication';
  }

  public function newQuery() {
    return id(new PhabricatorEditEngineQuery());
  }

  public function canUseInPanelContext() {
    return false;
  }

  protected function buildQueryFromParameters(array $map) {
    $query = $this->newQuery();
    return $query;
  }

  protected function buildCustomSearchFields() {
    return array();
  }

  protected function getDefaultFieldOrder() {
    return array();
  }

  protected function getURI($path) {
    return '/transactions/editengine/'.$path;
  }

  protected function getBuiltinQueryNames() {
    $names = array(
      'all' => pht('All Edit Engines'),
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
    array $engines,
    PhabricatorSavedQuery $query,
    array $handles) {
    assert_instances_of($engines, 'PhabricatorEditEngine');
    $viewer = $this->requireViewer();

    $list = id(new PHUIObjectItemListView())
      ->setUser($viewer);
    foreach ($engines as $engine) {
      if (!$engine->isEngineConfigurable()) {
        continue;
      }

      $engine_key = $engine->getEngineKey();
      $query_uri = "/transactions/editengine/{$engine_key}/";

      $application = $engine->getApplication();
      $app_icon = $application->getIcon();

      $item = id(new PHUIObjectItemView())
        ->setHeader($engine->getSummaryHeader())
        ->setHref($query_uri)
        ->setStatusIcon($app_icon)
        ->addAttribute($engine->getSummaryText());

      $list->addItem($item);
    }

    return id(new PhabricatorApplicationSearchResultView())
      ->setObjectList($list);
  }
}
