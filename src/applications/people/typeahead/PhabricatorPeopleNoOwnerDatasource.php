<?php

final class PhabricatorPeopleNoOwnerDatasource
  extends PhabricatorTypeaheadDatasource {

  const FUNCTION_TOKEN = 'none()';

  public function getBrowseTitle() {
    return pht('Browse No Owner');
  }

  public function getPlaceholderText() {
    return pht('Type "none"...');
  }

  public function getDatasourceApplicationClass() {
    return 'PhabricatorPeopleApplication';
  }

  public function getDatasourceFunctions() {
    return array(
      'none' => array(
        'name' => pht('No Owner'),
        'summary' => pht('Find results which are not assigned.'),
        'description' => pht(
          "This function includes results which have no owner. Use a query ".
          "like this to find unassigned results:\n\n%s\n\n".
          "If you combine this function with other functions, the query will ".
          "return results which match the other selectors //or// have no ".
          "owner. For example, this query will find results which are owned ".
          "by `alincoln`, and will also find results which have no owner:".
          "\n\n%s",
          '> none()',
          '> alincoln, none()'),
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
