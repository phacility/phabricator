<?php

final class DifferentialExactResponsibleViewerFunctionDatasource
  extends PhabricatorTypeaheadDatasource {

  public function getBrowseTitle() {
    return pht('Browse Exact Viewer');
  }

  public function getPlaceholderText() {
    return pht('Type exact-viewer()...');
  }

  public function getDatasourceApplicationClass() {
    return 'PhabricatorPeopleApplication';
  }

  public function getDatasourceFunctions() {
    return array(
      'exact-viewer' => array(
        'name' => pht('Exact Current Viewer'),
        'summary' => pht('Results matching the current viewing user exactly.'),
        'description' => pht(
          'Find revisions the current viewer is responsible for, exactly,'.
          'and not include those through their projects or packages. '),
        ),
    );
  }

  public function loadResults() {
    if ($this->getViewer()->getPHID()) {
      $results = array($this->renderViewerFunctionToken());
    } else {
      $results = array();
    }

    return $this->filterResultsAgainstTokens($results);
  }

  protected function canEvaluateFunction($function) {
    if (!$this->getViewer()->getPHID()) {
      return false;
    }

    return parent::canEvaluateFunction($function);
  }

  protected function evaluateFunction($function, array $argv_list) {
    $results = array();
    foreach ($argv_list as $argv) {
      $results[] = $this->getViewer()->getPHID();
    }
    return $results;
  }

  public function renderFunctionTokens($function, array $argv_list) {
    $tokens = array();
    foreach ($argv_list as $argv) {
      $tokens[] = PhabricatorTypeaheadTokenView::newFromTypeaheadResult(
        $this->renderViewerFunctionToken());
    }
    return $tokens;
  }

  private function renderViewerFunctionToken() {
    return $this->newFunctionResult()
      ->setName(pht('Exact: Current Viewer'))
      ->setPHID('exact-viewer()')
      ->setIcon('fa-user')
      ->setUnique(true);
  }

}
