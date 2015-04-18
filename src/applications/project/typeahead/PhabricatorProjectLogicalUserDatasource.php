<?php

final class PhabricatorProjectLogicalUserDatasource
  extends PhabricatorTypeaheadCompositeDatasource {

  public function getPlaceholderText() {
    return pht('Type projects(<user>)...');
  }

  public function getDatasourceApplicationClass() {
    return 'PhabricatorProjectApplication';
  }

  public function getComponentDatasources() {
    return array(
      new PhabricatorPeopleDatasource(),
    );
  }

  public function getDatasourceFunctions() {
    return array(
      'projects' => array(
        'name' => pht("Find results in any of a user's projects."),
      ),
    );
  }

  protected function didLoadResults(array $results) {
    foreach ($results as $result) {
      $result
        ->setTokenType(PhabricatorTypeaheadTokenView::TYPE_FUNCTION)
        ->setIcon('fa-asterisk')
        ->setPHID('projects('.$result->getPHID().')')
        ->setDisplayName(pht("User's Projects: %s", $result->getDisplayName()))
        ->setName($result->getName().' projects');
    }

    return $results;
  }

  protected function evaluateFunction($function, array $argv_list) {
    $phids = array();
    foreach ($argv_list as $argv) {
      $phids[] = head($argv);
    }

    $projects = id(new PhabricatorProjectQuery())
      ->setViewer($this->getViewer())
      ->withMemberPHIDs($phids)
      ->execute();

    $results = array();
    foreach ($projects as $project) {
      $results[] = new PhabricatorQueryConstraint(
        PhabricatorQueryConstraint::OPERATOR_OR,
        $project->getPHID());
    }

    return $results;
  }

  public function renderFunctionTokens($function, array $argv_list) {
    $phids = array();
    foreach ($argv_list as $argv) {
      $phids[] = head($argv);
    }

    $tokens = $this->renderTokens($phids);
    foreach ($tokens as $token) {
      if ($token->isInvalid()) {
        $token
          ->setValue(pht("User's Projects: Invalid User"));
      } else {
        $token
          ->setIcon('fa-asterisk')
          ->setTokenType(PhabricatorTypeaheadTokenView::TYPE_FUNCTION)
          ->setKey('projects('.$token->getKey().')')
          ->setValue(pht("User's Projects: %s", $token->getValue()));
      }
    }

    return $tokens;
  }

}
