<?php

final class PhabricatorCustomFieldApplicationSearchNoneFunctionDatasource
  extends PhabricatorTypeaheadDatasource {

  public function getBrowseTitle() {
    return pht('Browse No Value');
  }

  public function getPlaceholderText() {
    return pht('Type "none()"...');
  }

  public function getDatasourceApplicationClass() {
    return null;
  }

  public function getDatasourceFunctions() {
    return array(
      'none' => array(
        'name' => pht('No Value'),
        'summary' => pht('Find results with no value.'),
        'description' => pht(
          "This function includes results which have no value. Use a query ".
          "like this to find results with no value:\n\n%s\n\n".
          'If you combine this function with other constraints, results '.
          'which have no value or the specified values will be returned.',
          '> any()'),
      ),
    );
  }

  public function loadResults() {
    $results = array(
      $this->newNoneFunction(),
    );
    return $this->filterResultsAgainstTokens($results);
  }

  protected function evaluateFunction($function, array $argv_list) {
    $results = array();

    foreach ($argv_list as $argv) {
      $results[] = new PhabricatorQueryConstraint(
        PhabricatorQueryConstraint::OPERATOR_NULL,
        null);
    }

    return $results;
  }

  public function renderFunctionTokens($function, array $argv_list) {
    $results = array();
    foreach ($argv_list as $argv) {
      $results[] = PhabricatorTypeaheadTokenView::newFromTypeaheadResult(
        $this->newNoneFunction());
    }
    return $results;
  }

  private function newNoneFunction() {
    $name = pht('No Value');
    return $this->newFunctionResult()
      ->setName($name.' none')
      ->setDisplayName($name)
      ->setIcon('fa-ban')
      ->setPHID('none()')
      ->setUnique(true)
      ->addAttribute(pht('Select results with no value.'));
  }

}
