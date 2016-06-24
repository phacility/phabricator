<?php

final class ManiphestTaskOpenStatusDatasource
  extends PhabricatorTypeaheadDatasource {

  const FUNCTION_TOKEN = 'open()';

  public function getBrowseTitle() {
    return pht('Browse Any Open Status');
  }

  public function getPlaceholderText() {
    return pht('Type open()...');
  }

  public function getDatasourceApplicationClass() {
    return 'PhabricatorManiphestApplication';
  }

  public function getDatasourceFunctions() {
    return array(
      'open' => array(
        'name' => pht('Any Open Status'),
        'summary' => pht('Find results with any open status.'),
        'description' => pht(
          'This function includes results which have any open status.'),
      ),
    );
  }

  public function loadResults() {
    $results = array(
      $this->buildOpenResult(),
    );
    return $this->filterResultsAgainstTokens($results);
  }

  protected function evaluateFunction($function, array $argv_list) {
    $results = array();

    $map = ManiphestTaskStatus::getTaskStatusMap();
    foreach ($argv_list as $argv) {
      foreach ($map as $status => $name) {
        if (ManiphestTaskStatus::isOpenStatus($status)) {
          $results[] = $status;
        }
      }
    }

    return $results;
  }

  public function renderFunctionTokens($function, array $argv_list) {
    $results = array();

    foreach ($argv_list as $argv) {
      $results[] = PhabricatorTypeaheadTokenView::newFromTypeaheadResult(
        $this->buildOpenResult());
    }

    return $results;
  }

  private function buildOpenResult() {
    $name = pht('Any Open Status');
    return $this->newFunctionResult()
      ->setName($name.' open')
      ->setDisplayName($name)
      ->setPHID(self::FUNCTION_TOKEN)
      ->setUnique(true)
      ->addAttribute(pht('Select any open status.'));
  }

}
