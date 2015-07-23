<?php

final class ManiphestTaskStatusDatasource
  extends PhabricatorTypeaheadDatasource {

  public function getBrowseTitle() {
    return pht('Browse Statuses');
  }

  public function getPlaceholderText() {
    return pht('Type a task status name...');
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

    $status_map = ManiphestTaskStatus::getTaskStatusMap();
    foreach ($status_map as $value => $name) {
      $results[$value] = id(new PhabricatorTypeaheadResult())
        ->setIcon(ManiphestTaskStatus::getStatusIcon($value))
        ->setPHID($value)
        ->setName($name);
    }

    return $results;
  }

}
