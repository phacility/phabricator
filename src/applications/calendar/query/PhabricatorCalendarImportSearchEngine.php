<?php

final class PhabricatorCalendarImportSearchEngine
  extends PhabricatorApplicationSearchEngine {

  public function getResultTypeDescription() {
    return pht('Calendar Imports');
  }

  public function getApplicationClassName() {
    return 'PhabricatorCalendarApplication';
  }

  public function canUseInPanelContext() {
    return false;
  }

  public function newQuery() {
    return new PhabricatorCalendarImportQuery();
  }

  protected function buildCustomSearchFields() {
    return array();
  }

  protected function buildQueryFromParameters(array $map) {
    $query = $this->newQuery();

    return $query;
  }

  protected function getURI($path) {
    return '/calendar/import/'.$path;
  }

  protected function getBuiltinQueryNames() {
    $names = array(
      'all' => pht('All Imports'),
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
    array $imports,
    PhabricatorSavedQuery $query,
    array $handles) {

    assert_instances_of($imports, 'PhabricatorCalendarImport');
    $viewer = $this->requireViewer();

    $list = new PHUIObjectItemListView();
    foreach ($imports as $import) {
      $item = id(new PHUIObjectItemView())
        ->setViewer($viewer)
        ->setObjectName(pht('Import %d', $import->getID()))
        ->setHeader($import->getDisplayName())
        ->setHref($import->getURI());

      if ($import->getIsDisabled()) {
        $item->setDisabled(true);
      }

      $list->addItem($item);
    }

    $result = new PhabricatorApplicationSearchResultView();
    $result->setObjectList($list);
    $result->setNoDataString(pht('No imports found.'));

    return $result;
  }
}
