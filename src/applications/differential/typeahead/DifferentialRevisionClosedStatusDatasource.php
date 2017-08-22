<?php

final class DifferentialRevisionClosedStatusDatasource
  extends PhabricatorTypeaheadDatasource {

  const FUNCTION_TOKEN = 'closed()';

  public function getBrowseTitle() {
    return pht('Browse Any Closed Status');
  }

  public function getPlaceholderText() {
    return pht('Type closed()...');
  }

  public function getDatasourceApplicationClass() {
    return 'PhabricatorDifferentialApplication';
  }

  public function getDatasourceFunctions() {
    return array(
      'closed' => array(
        'name' => pht('Any Closed Status'),
        'summary' => pht('Find results with any closed status.'),
        'description' => pht(
          'This function includes results which have any closed status.'),
      ),
    );
  }

  public function loadResults() {
    $results = array(
      $this->buildClosedResult(),
    );
    return $this->filterResultsAgainstTokens($results);
  }

  protected function evaluateFunction($function, array $argv_list) {
    $results = array();

    $map = DifferentialRevisionStatus::getAll();
    foreach ($argv_list as $argv) {
      foreach ($map as $status) {
        if ($status->isClosedStatus()) {
          $results[] = $status->getKey();
        }
      }
    }

    return $results;
  }

  public function renderFunctionTokens($function, array $argv_list) {
    $results = array();

    foreach ($argv_list as $argv) {
      $results[] = PhabricatorTypeaheadTokenView::newFromTypeaheadResult(
        $this->buildClosedResult());
    }

    return $results;
  }

  private function buildClosedResult() {
    $name = pht('Any Closed Status');
    return $this->newFunctionResult()
      ->setName($name.' closed')
      ->setDisplayName($name)
      ->setPHID(self::FUNCTION_TOKEN)
      ->setUnique(true)
      ->addAttribute(pht('Select any closed status.'));
  }

}
