<?php

final class PhabricatorProjectLogicalAndDatasource
  extends PhabricatorTypeaheadCompositeDatasource {

  public function getBrowseTitle() {
    return pht('Browse Projects');
  }

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

  protected function didEvaluateTokens(array $results) {
    foreach ($results as $key => $result) {
      if (is_string($result)) {
        $results[$key] = new PhabricatorQueryConstraint(
          PhabricatorQueryConstraint::OPERATOR_AND,
          $result);
      }
    }

    return $results;
  }

}
