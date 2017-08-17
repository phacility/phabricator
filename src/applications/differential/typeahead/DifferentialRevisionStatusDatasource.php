<?php

final class DifferentialRevisionStatusDatasource
  extends PhabricatorTypeaheadDatasource {

  public function getBrowseTitle() {
    return pht('Browse Statuses');
  }

  public function getPlaceholderText() {
    return pht('Type a revision status name...');
  }

  public function getDatasourceApplicationClass() {
    return 'PhabricatorDifferentialApplication';
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

    $statuses = DifferentialRevisionStatus::getAll();
    foreach ($statuses as $status) {
      $key = $status->getKey();

      $result = id(new PhabricatorTypeaheadResult())
        ->setIcon($status->getIcon())
        ->setPHID($key)
        ->setName($status->getDisplayName());

      if ($status->isClosedStatus()) {
        $result->addAttribute(pht('Closed Status'));
      } else {
        $result->addAttribute(pht('Open Status'));
      }

      $results[$key] = $result;
    }

    return $results;
  }

}
