<?php

final class ManiphestTaskSubtypeDatasource
  extends PhabricatorTypeaheadDatasource {

  public function getBrowseTitle() {
    return pht('Browse Subtypes');
  }

  public function getPlaceholderText() {
    return pht('Type a task subtype name...');
  }

  public function getDatasourceApplicationClass() {
    return 'PhabricatorManiphestApplication';
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

    $subtype_map = id(new ManiphestTask())->newEditEngineSubtypeMap();
    foreach ($subtype_map as $key => $subtype) {

      $result = id(new PhabricatorTypeaheadResult())
        ->setIcon($subtype->getIcon())
        ->setColor($subtype->getColor())
        ->setPHID($key)
        ->setName($subtype->getName());

      $results[$key] = $result;
    }

    return $results;
  }

}
