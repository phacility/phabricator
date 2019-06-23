<?php

final class DifferentialNoReviewersDatasource
  extends PhabricatorTypeaheadDatasource {

  const FUNCTION_TOKEN = 'none()';

  public function getBrowseTitle() {
    return pht('Browse No Reviewers');
  }

  public function getPlaceholderText() {
    return pht('Type "none"...');
  }

  public function getDatasourceApplicationClass() {
    return 'PhabricatorDifferentialApplication';
  }

  public function getDatasourceFunctions() {
    return array(
      'none' => array(
        'name' => pht('No Reviewers'),
        'summary' => pht('Find results which have no reviewers.'),
        'description' => pht(
          "This function includes results which have no reviewers. Use a ".
          "query like this to find results with no reviewers:\n\n%s\n\n".
          "If you combine this function with other functions, the query will ".
          "return results which match the other selectors //or// have no ".
          "reviewers. For example, this query will find results which have ".
          "`alincoln` as a reviewer, and will also find results which have ".
          "no reviewers:".
          "\n\n%s",
          '> none()',
          '> alincoln, none()'),
      ),
    );
  }

  public function loadResults() {
    $results = array(
      $this->buildNoReviewersResult(),
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
        $this->buildNoReviewersResult());
    }
    return $results;
  }

  private function buildNoReviewersResult() {
    $name = pht('No Reviewers');

    return $this->newFunctionResult()
      ->setName($name.' none')
      ->setDisplayName($name)
      ->setIcon('fa-ban')
      ->setPHID('none()')
      ->setUnique(true)
      ->addAttribute(pht('Select results with no reviewers.'));
  }

}
