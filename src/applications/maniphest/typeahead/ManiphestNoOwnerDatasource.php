<?php

final class ManiphestNoOwnerDatasource
  extends PhabricatorTypeaheadDatasource {

  const FUNCTION_TOKEN = 'none()';

  public function getBrowseTitle() {
    return pht('Browse No Owner');
  }

  public function getPlaceholderText() {
    return pht('Type "none"...');
  }

  public function getDatasourceApplicationClass() {
    return 'PhabricatorManiphestApplication';
  }

  public function getDatasourceFunctions() {
    return array(
      'none' => array(
        'name' => pht('Find results which are not assigned.'),
      ),
    );
  }

  public function loadResults() {
    $results = array(
      $this->buildNoOwnerResult(),
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
        $this->buildNoOwnerResult());
    }
    return $results;
  }

  private function buildNoOwnerResult() {
    $name = pht('No Owner');
    return $this->newFunctionResult()
      ->setName($name.' none')
      ->setDisplayName($name)
      ->setIcon('fa-ban')
      ->setPHID('none()')
      ->setUnique(true);
  }

}
