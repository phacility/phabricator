<?php

final class PhabricatorProjectNoProjectsDatasource
  extends PhabricatorTypeaheadDatasource {

  public function getBrowseTitle() {
    return pht('Browse Not Tagged With Any Projects');
  }

  public function getPlaceholderText() {
    return pht('Type "not tagged with any projects"...');
  }

  public function getDatasourceApplicationClass() {
    return 'PhabricatorProjectApplication';
  }

  public function getDatasourceFunctions() {
    return array(
      'null' => array(
        'name' => pht('Not Tagged With Any Projects'),
        'summary' => pht(
          'Find results which are not tagged with any projects.'),
        'description' => pht(
          "This function matches results which are not tagged with any ".
          "projects. It is usually most often used to find objects which ".
          "might have slipped through the cracks and not been organized ".
          "properly.\n\n%s",
          '> null()'),
      ),
    );
  }

  public function loadResults() {
    $results = array(
      $this->buildNullResult(),
    );

    return $this->filterResultsAgainstTokens($results);
  }

  protected function evaluateFunction($function, array $argv_list) {
    $results = array();

    foreach ($argv_list as $argv) {
      $results[] = new PhabricatorQueryConstraint(
        PhabricatorQueryConstraint::OPERATOR_NULL,
        'empty');
    }

    return $results;
  }

  public function renderFunctionTokens($function, array $argv_list) {
    $results = array();
    foreach ($argv_list as $argv) {
      $results[] = PhabricatorTypeaheadTokenView::newFromTypeaheadResult(
        $this->buildNullResult());
    }
    return $results;
  }

  private function buildNullResult() {
    $name = pht('Not Tagged With Any Projects');

    return $this->newFunctionResult()
      ->setUnique(true)
      ->setPHID('null()')
      ->setIcon('fa-ban')
      ->setName('null '.$name)
      ->setDisplayName($name)
      ->addAttribute(pht('Select results with no tags.'));
  }

}
