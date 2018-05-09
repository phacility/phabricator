<?php

final class DiffusionIdentityUnassignedDatasource
  extends PhabricatorTypeaheadDatasource {

  const FUNCTION_TOKEN = 'unassigned()';

  public function getBrowseTitle() {
    return pht('Browse Explicitly Unassigned');
  }

  public function getPlaceholderText() {
    return pht('Type "unassigned"...');
  }

  public function getDatasourceApplicationClass() {
    return 'PhabricatorDiffusionApplication';
  }

  public function getDatasourceFunctions() {
    return array(
      'unassigned' => array(
        'name' => pht('Explicitly Unassigned'),
        'summary' => pht('Find results which are not assigned.'),
        'description' => pht(
          "This function includes results which have been explicitly ".
          "unassigned. Use a query like this to find explicitly ".
          "unassigned results:\n\n%s\n\n".
          "If you combine this function with other functions, the query will ".
          "return results which match the other selectors //or// have no ".
          "assignee. For example, this query will find results which are ".
          "assigned to `alincoln`, and will also find results which have been ".
          "unassigned:\n\n%s",
          '> unassigned()',
          '> alincoln, unassigned()'),
      ),
    );
  }

  public function loadResults() {
    $results = array(
      $this->buildUnassignedResult(),
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
        $this->buildUnassignedResult());
    }
    return $results;
  }

  private function buildUnassignedResult() {
    $name = pht('Unassigned');
    return $this->newFunctionResult()
      ->setName($name.' unassigned')
      ->setDisplayName($name)
      ->setIcon('fa-ban')
      ->setPHID('unassigned()')
      ->setUnique(true)
      ->addAttribute(pht('Select results with no owner.'));
  }

}
