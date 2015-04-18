<?php

final class PhabricatorProjectLogicalAndDatasource
  extends PhabricatorTypeaheadCompositeDatasource {

  public function getPlaceholderText() {
    return pht('Type a project name...');
  }

  public function getDatasourceApplicationClass() {
    return 'PhabricatorProjectApplication';
  }

  public function getComponentDatasources() {
    return array(
      new PhabricatorProjectDatasource(),
    );
  }

  public function evaluateTokens(array $tokens) {
    $results = parent::evaluateTokens($tokens);

    foreach ($results as $key => $result) {
      $results[$key] = new PhabricatorQueryConstraint(
        PhabricatorQueryConstraint::OPERATOR_AND,
        $result);
    }

    return $results;
  }

}
