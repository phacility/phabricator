<?php

final class AlmanacServiceTypeDatasource
  extends PhabricatorTypeaheadDatasource {

  public function getBrowseTitle() {
    return pht('Browse Service Types');
  }

  public function getPlaceholderText() {
    return pht('Type a service type name...');
  }

  public function getDatasourceApplicationClass() {
    return 'PhabricatorAlmanacApplication';
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

    $types = AlmanacServiceType::getAllServiceTypes();

    $results = array();
    foreach ($types as $key => $type) {
      $results[$key] = id(new PhabricatorTypeaheadResult())
        ->setName($type->getServiceTypeName())
        ->setIcon($type->getServiceTypeIcon())
        ->setPHID($key);
    }

    return $results;
  }

}
