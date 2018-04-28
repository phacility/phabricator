<?php

final class HeraldAdapterDatasource
  extends PhabricatorTypeaheadDatasource {

  public function getBrowseTitle() {
    return pht('Browse Herald Adapters');
  }

  public function getPlaceholderText() {
    return pht('Type an adapter name...');
  }

  public function getDatasourceApplicationClass() {
    return 'PhabricatorHeraldApplication';
  }

  public function loadResults() {
    $results = $this->buildResults();
    return $this->filterResultsAgainstTokens($results);
  }

  protected function renderSpecialTokens(array $values) {
    return $this->renderTokensFromResults($this->buildResults(), $values);
  }

  private function buildResults() {
    $results = array();

    $adapters = HeraldAdapter::getAllAdapters();
    foreach ($adapters as $adapter) {
      $value = $adapter->getAdapterContentType();
      $name = $adapter->getAdapterContentName();

      $result = id(new PhabricatorTypeaheadResult())
        ->setPHID($value)
        ->setName($name);

      $results[$value] = $result;
    }

    return $results;
  }

}
