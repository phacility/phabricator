<?php

final class PhabricatorRoleUserFunctionDatasource
  extends PhabricatorTypeaheadCompositeDatasource {

  public function getBrowseTitle() {
    return pht('Browse User Roles');
  }

  public function getPlaceholderText() {
    return pht('Type roles(<user>)...');
  }

  public function getDatasourceApplicationClass() {
    return 'PhabricatorRoleApplication';
  }

  public function getComponentDatasources() {
    return array(
      new PhabricatorRoleLogicalUserDatasource(),
    );
  }

  protected function evaluateFunction($function, array $argv_list) {
    $result = parent::evaluateFunction($function, $argv_list);

    foreach ($result as $k => $v) {
      if ($v instanceof PhabricatorQueryConstraint) {
        $result[$k] = $v->getValue();
      }
    }

    return $result;
  }

}
