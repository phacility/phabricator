<?php

final class PhabricatorEditEngineConfigurationSearchEngine
  extends PhabricatorApplicationSearchEngine {

  private $engineKey;

  public function setEngineKey($engine_key) {
    $this->engineKey = $engine_key;
    return $this;
  }

  public function getEngineKey() {
    return $this->engineKey;
  }

  public function canUseInPanelContext() {
    return false;
  }

  public function getResultTypeDescription() {
    return pht('Forms');
  }

  public function getApplicationClassName() {
    return 'PhabricatorTransactionsApplication';
  }

  public function newQuery() {
    return id(new PhabricatorEditEngineConfigurationQuery())
      ->withEngineKeys(array($this->getEngineKey()));
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
    return '/transactions/editengine/'.$this->getEngineKey().'/'.$path;
  }

  protected function getBuiltinQueryNames() {
    $names = array(
      'all' => pht('All Forms'),
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
    array $configs,
    PhabricatorSavedQuery $query,
    array $handles) {
    assert_instances_of($configs, 'PhabricatorEditEngineConfiguration');
    $viewer = $this->requireViewer();
    $engine_key = $this->getEngineKey();

    $list = id(new PHUIObjectItemListView())
      ->setUser($viewer);
    foreach ($configs as $config) {
      $item = id(new PHUIObjectItemView())
        ->setHeader($config->getDisplayName());

      $id = $config->getID();
      if ($id) {
        $item->setObjectName(pht('Form %d', $id));
        $key = $id;
      } else {
        $item->setObjectName(pht('Builtin'));
        $key = $config->getBuiltinKey();
      }
      $item->setHref("/transactions/editengine/{$engine_key}/view/{$key}/");

      if ($config->getIsDefault()) {
        $item->addIcon('fa-plus', pht('Default'));
      }

      if ($config->getIsDisabled()) {
        $item->addIcon('fa-ban', pht('Disabled'));
      }

      $list->addItem($item);
    }

    return id(new PhabricatorApplicationSearchResultView())
      ->setObjectList($list);
  }
}
