<?php

final class PhabricatorPeopleAnyOwnerDatasource
  extends PhabricatorTypeaheadDatasource {

  const FUNCTION_TOKEN = 'anyone()';

  public function getBrowseTitle() {
    return pht('Browse Any Owner');
  }

  public function getPlaceholderText() {
    return pht('Type "anyone()"...');
  }

  public function getDatasourceApplicationClass() {
    return 'PhabricatorPeopleApplication';
  }

  public function getDatasourceFunctions() {
    return array(
      'anyone' => array(
        'name' => pht('Anyone'),
        'summary' => pht('Find results which are assigned to anyone.'),
        'description' => pht(
          'This function includes results which have any owner. It excludes '.
          'unassigned or unowned results.'),
      ),
    );
  }

  public function loadResults() {
    $results = array(
      $this->buildAnyoneResult(),
    );
    return $this->filterResultsAgainstTokens($results);
  }

  protected function evaluateFunction($function, array $argv_list) {
    $results = array();

    foreach ($argv_list as $argv) {
      $results[] = self::FUNCTION_TOKEN;
    }

    return $results;
  }

  public function renderFunctionTokens($function, array $argv_list) {
    $results = array();
    foreach ($argv_list as $argv) {
      $results[] = PhabricatorTypeaheadTokenView::newFromTypeaheadResult(
        $this->buildAnyoneResult());
    }
    return $results;
  }

  private function buildAnyoneResult() {
    $name = pht('Any Owner');
    return $this->newFunctionResult()
      ->setName($name.' anyone')
      ->setDisplayName($name)
      ->setIcon('fa-certificate')
      ->setPHID(self::FUNCTION_TOKEN)
      ->setUnique(true)
      ->addAttribute(pht('Select results with any owner.'));
  }

}
