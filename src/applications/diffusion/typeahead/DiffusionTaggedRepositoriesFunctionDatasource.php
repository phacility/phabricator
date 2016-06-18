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
        ->setName('tagged '.$result->getName());
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
