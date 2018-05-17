<?php

final class HeraldRuleTypeDatasource
  extends PhabricatorTypeaheadDatasource {

  public function getBrowseTitle() {
    return pht('Browse Rule Types');
  }

  public function getPlaceholderText() {
    return pht('Type a rule type...');
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

    $type_map = HeraldRuleTypeConfig::getRuleTypeMap();
    foreach ($type_map as $type => $name) {
      $result = id(new PhabricatorTypeaheadResult())
        ->setPHID($type)
        ->setName($name);

      $results[$type] = $result;
    }

    return $results;
  }

}
