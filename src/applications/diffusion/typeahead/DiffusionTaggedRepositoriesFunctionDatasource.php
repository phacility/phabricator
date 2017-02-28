<?php

final class DiffusionTaggedRepositoriesFunctionDatasource
  extends PhabricatorTypeaheadCompositeDatasource {

  public function getBrowseTitle() {
    return pht('Browse Repositories');
  }

  public function getPlaceholderText() {
    return pht('Type tagged(<project>)...');
  }

  public function getDatasourceApplicationClass() {
    return 'PhabricatorProjectApplication';
  }

  public function getComponentDatasources() {
    return array(
      new PhabricatorProjectDatasource(),
    );
  }

  public function getDatasourceFunctions() {
    return array(
      'tagged' => array(
        'name' => pht('Repositories: ...'),
        'arguments' => pht('project'),
        'summary' => pht('Find results for repositories of a project.'),
        'description' => pht(
          'This function allows you to find results for any of the `.
          `repositories of a project:'.
          "\n\n".
          '> tagged(engineering)'),
      ),
    );
  }

  protected function didLoadResults(array $results) {
    foreach ($results as $result) {
      $result
        ->setTokenType(PhabricatorTypeaheadTokenView::TYPE_FUNCTION)
        ->setColor(null)
        ->setPHID('tagged('.$result->getPHID().')')
        ->setDisplayName(pht('Tagged: %s', $result->getDisplayName()))
        ->setName('tagged '.$result->getName())
        ->resetAttributes()
        ->addAttribute(pht('Function'))
        ->addAttribute(pht('Select repositories tagged with this project.'));
    }

    return $results;
  }

  protected function evaluateFunction($function, array $argv_list) {
    $phids = array();
    foreach ($argv_list as $argv) {
      $phids[] = head($argv);
    }

    $repositories = id(new PhabricatorRepositoryQuery())
      ->setViewer($this->getViewer())
      ->withEdgeLogicPHIDs(
        PhabricatorProjectObjectHasProjectEdgeType::EDGECONST,
        PhabricatorQueryConstraint::OPERATOR_OR,
        $phids)
      ->execute();

    $results = array();
    foreach ($repositories as $repository) {
      $results[] = $repository->getPHID();
    }

    if (!$results) {
      // TODO: This is a little hacky, but if you query for "tagged(x)" and
      // there are no such repositories, we want to match nothing. If we
      // just return `array()`, that gets evaluated as "no constraint" and
      // we match everything. This works correctly for now, but should be
      // replaced with some more elegant/general approach eventually.
      $results[] = PhabricatorPHIDConstants::PHID_VOID;
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
      // Remove any project color on this token.
      $token->setColor(null);

      if ($token->isInvalid()) {
        $token
          ->setValue(pht('Repositories: Invalid Project'));
      } else {
        $token
          ->setTokenType(PhabricatorTypeaheadTokenView::TYPE_FUNCTION)
          ->setKey('tagged('.$token->getKey().')')
          ->setValue(pht('Tagged: %s', $token->getValue()));
      }
    }

    return $tokens;
  }

}
