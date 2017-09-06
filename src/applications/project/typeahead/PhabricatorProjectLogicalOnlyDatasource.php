<?php

final class PhabricatorProjectLogicalOnlyDatasource
  extends PhabricatorTypeaheadDatasource {

  public function getBrowseTitle() {
    return pht('Browse Only');
  }

  public function getPlaceholderText() {
    return pht('Type only()...');
  }

  public function getDatasourceApplicationClass() {
    return 'PhabricatorProjectApplication';
  }

  public function getDatasourceFunctions() {
    return array(
      'only' => array(
        'name' => pht('Only Match Other Constraints'),
        'summary' => pht(
          'Find results with only the specified tags.'),
        'description' => pht(
          "This function is used with other tags, and causes the query to ".
          "match only results with exactly those tags. For example, to find ".
          "tasks tagged only iOS:".
          "\n\n".
          "> ios, only()".
          "\n\n".
          "This will omit results with any other project tag."),
      ),
    );
  }

  public function loadResults() {
    $results = array(
      $this->renderOnlyFunctionToken(),
    );
    return $this->filterResultsAgainstTokens($results);
  }

  protected function evaluateFunction($function, array $argv_list) {
    $results = array();

    $results[] = new PhabricatorQueryConstraint(
      PhabricatorQueryConstraint::OPERATOR_ONLY,
      null);

    return $results;
  }

  public function renderFunctionTokens(
    $function,
    array $argv_list) {

    $tokens = array();
    foreach ($argv_list as $argv) {
      $tokens[] = PhabricatorTypeaheadTokenView::newFromTypeaheadResult(
        $this->renderOnlyFunctionToken());
    }

    return $tokens;
  }

  private function renderOnlyFunctionToken() {
    return $this->newFunctionResult()
      ->setName(pht('Only'))
      ->setPHID('only()')
      ->setIcon('fa-asterisk')
      ->setUnique(true)
      ->addAttribute(
        pht('Select only results with exactly the other specified tags.'));
  }

}
