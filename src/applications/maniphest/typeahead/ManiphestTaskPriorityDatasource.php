<?php

final class ManiphestTaskPriorityDatasource
  extends PhabricatorTypeaheadDatasource {

  public function getBrowseTitle() {
    return pht('Browse Priorities');
  }

  public function getPlaceholderText() {
    return pht('Type a task priority name...');
  }

  public function getDatasourceApplicationClass() {
    return 'PhabricatorManiphestApplication';
  }

  public function loadResults() {
    $results = $this->buildResults();
    return $this->filterResultsAgainstTokens($results);
  }

  public function renderTokens(array $values) {
    return $this->renderTokensFromResults($this->buildResults(), $values);
  }

  private function buildResults() {
    $results = array();

    $priority_map = ManiphestTaskPriority::getTaskPriorityMap();
    foreach ($priority_map as $value => $name) {
      $results[$value] = id(new PhabricatorTypeaheadResult())
        ->setIcon(ManiphestTaskPriority::getTaskPriorityIcon($value))
        ->setPHID($value)
        ->setName($name);
    }

    return $results;
  }

}
