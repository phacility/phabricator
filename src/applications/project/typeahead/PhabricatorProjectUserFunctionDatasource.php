<?php

final class PhabricatorProjectUserFunctionDatasource
  extends PhabricatorTypeaheadCompositeDatasource {

  public function getBrowseTitle() {
    return pht('Browse User Projects');
  }

  public function getPlaceholderText() {
    return pht('Type projects(<user>)...');
  }

  public function getDatasourceApplicationClass() {
    return 'PhabricatorProjectApplication';
  }

  public function getComponentDatasources() {
    return array(
      new PhabricatorProjectLogicalUserDatasource(),
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
