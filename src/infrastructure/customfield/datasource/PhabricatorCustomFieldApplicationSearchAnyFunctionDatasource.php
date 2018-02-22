<?php

final class PhabricatorCustomFieldApplicationSearchAnyFunctionDatasource
  extends PhabricatorTypeaheadDatasource {

  public function getBrowseTitle() {
    return pht('Browse Any');
  }

  public function getPlaceholderText() {
    return pht('Type "any()"...');
  }

  public function getDatasourceApplicationClass() {
    return null;
  }

  public function getDatasourceFunctions() {
    return array(
      'any' => array(
        'name' => pht('Any Value'),
        'summary' => pht('Find results with any value.'),
        'description' => pht(
          "This function includes results which have any value. Use a query ".
          "like this to find results with any value:\n\n%s",
          '> any()'),
      ),
    );
  }

  public function loadResults() {
    $results = array(
      $this->newAnyFunction(),
    );
    return $this->filterResultsAgainstTokens($results);
  }

  protected function evaluateFunction($function, array $argv_list) {
    $results = array();

    foreach ($argv_list as $argv) {
      $results[] = new PhabricatorQueryConstraint(
        PhabricatorQueryConstraint::OPERATOR_ANY,
        null);
    }

    return $results;
  }

  public function renderFunctionTokens($function, array $argv_list) {
    $results = array();
    foreach ($argv_list as $argv) {
      $results[] = PhabricatorTypeaheadTokenView::newFromTypeaheadResult(
        $this->newAnyFunction());
    }
    return $results;
  }

  private function newAnyFunction() {
    $name = pht('Any Value');
    return $this->newFunctionResult()
      ->setName($name.' any')
      ->setDisplayName($name)
      ->setIcon('fa-circle-o')
      ->setPHID('any()')
      ->setUnique(true)
      ->addAttribute(pht('Select results with any value.'));
  }

}
