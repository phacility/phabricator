<?php

final class PhabricatorRoleLogicalViewerDatasource
  extends PhabricatorTypeaheadDatasource {

  public function getBrowseTitle() {
    return pht('Browse Viewer Roles');
  }

  public function getPlaceholderText() {
    return pht('Type viewerroles()...');
  }

  public function getDatasourceApplicationClass() {
    return 'PhabricatorRoleApplication';
  }

  public function getDatasourceFunctions() {
    return array(
      'viewerroles' => array(
        'name' => pht("Current Viewer's Roles"),
        'summary' => pht(
          "Find results in any of the current viewer's roles."),
        'description' => pht(
          "This function matches results in any of the current viewing ".
          "user's roles:".
          "\n\n".
          "> viewerroles()".
          "\n\n".
          "This normally means //your// roles, but if you save a query ".
          "using this function and send it to someone else, it will mean ".
          "//their// roles when they run it (they become the current ".
          "viewer). This can be useful for building dashboard panels."),
      ),
    );
  }

  public function loadResults() {
    if ($this->getViewer()->getPHID()) {
      $results = array($this->renderViewerRolesFunctionToken());
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

    $roles = id(new PhabricatorRoleQuery())
      ->setViewer($viewer)
      ->withMemberPHIDs(array($viewer->getPHID()))
      ->execute();
    $phids = mpull($roles, 'getPHID');

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
        $this->renderViewerRolesFunctionToken());
    }

    return $tokens;
  }

  private function renderViewerRolesFunctionToken() {
    return $this->newFunctionResult()
      ->setName(pht('Current Viewer\'s Roles'))
      ->setPHID('viewerroles()')
      ->setIcon('fa-asterisk')
      ->setUnique(true)
      ->addAttribute(pht('Select roles current viewer is a member of.'));
  }

}
