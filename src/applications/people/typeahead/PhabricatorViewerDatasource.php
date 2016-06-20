<?php

final class PhabricatorViewerDatasource
  extends PhabricatorTypeaheadDatasource {

  public function getBrowseTitle() {
    return pht('Browse Viewer');
  }

  public function getPlaceholderText() {
    return pht('Type viewer()...');
  }

  public function getDatasourceApplicationClass() {
    return 'PhabricatorPeopleApplication';
  }

  public function getDatasourceFunctions() {
    return array(
      'viewer' => array(
        'name' => pht('Current Viewer'),
        'summary' => pht('Use the current viewing user.'),
        'description' => pht(
          "This function allows you to change the behavior of a query ".
          "based on who is running it. When you use this function, you will ".
          "be the current viewer, so it works like you typed your own ".
          "username.\n\n".
          "However, if you save a query using this function and send it ".
          "to someone else, it will work like //their// username was the ".
          "one that was typed. This can be useful for building dashboard ".
          "panels that always show relevant information to the user who ".
          "is looking at them."),
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
      ->setName(pht('Current Viewer'))
      ->setPHID('viewer()')
      ->setIcon('fa-user')
      ->setUnique(true)
      ->addAttribute(pht('Select current viewer.'));
  }

}
