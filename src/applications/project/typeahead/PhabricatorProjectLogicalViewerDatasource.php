<?php

final class PhabricatorProjectLogicalViewerDatasource
  extends PhabricatorTypeaheadDatasource {

  public function getBrowseTitle() {
    return pht('Browse Viewer Projects');
  }

  public function getPlaceholderText() {
    return pht('Type viewerprojects()...');
  }

  public function getDatasourceApplicationClass() {
    return 'PhabricatorProjectApplication';
  }

  public function getDatasourceFunctions() {
    return array(
      'viewerprojects' => array(
        'name' => pht("Current Viewer's Projects"),
        'summary' => pht(
          "Find results in any of the current viewer's projects."),
        'description' => pht(
          "This function matches results in any of the current viewing ".
          "user's projects:".
          "\n\n".
          "> viewerprojects()".
          "\n\n".
          "This normally means //your// projects, but if you save a query ".
          "using this function and send it to someone else, it will mean ".
          "//their// projects when they run it (they become the current ".
          "viewer). This can be useful for building dashboard panels."),
      ),
    );
  }

  public function loadResults() {
    if ($this->getViewer()->getPHID()) {
      $results = array($this->renderViewerProjectsFunctionToken());
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
    $viewer = $this->getViewer();

    $projects = id(new PhabricatorProjectQuery())
      ->setViewer($viewer)
      ->withMemberPHIDs(array($viewer->getPHID()))
      ->execute();
    $phids = mpull($projects, 'getPHID');

    $results = array();
    if ($phids) {
      foreach ($phids as $phid) {
        $results[] = new PhabricatorQueryConstraint(
          PhabricatorQueryConstraint::OPERATOR_OR,
          $phid);
      }
    } else {
      $results[] = new PhabricatorQueryConstraint(
        PhabricatorQueryConstraint::OPERATOR_EMPTY,
        null);
    }

    return $results;
  }

  public function renderFunctionTokens(
    $function,
    array $argv_list) {

    $tokens = array();
    foreach ($argv_list as $argv) {
      $tokens[] = PhabricatorTypeaheadTokenView::newFromTypeaheadResult(
        $this->renderViewerProjectsFunctionToken());
    }

    return $tokens;
  }

  private function renderViewerProjectsFunctionToken() {
    return $this->newFunctionResult()
      ->setName(pht('Current Viewer\'s Projects'))
      ->setPHID('viewerprojects()')
      ->setIcon('fa-asterisk')
      ->setUnique(true)
      ->addAttribute(pht('Select projects current viewer is a member of.'));
  }

}
