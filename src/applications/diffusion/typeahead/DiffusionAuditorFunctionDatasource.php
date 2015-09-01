<?php

final class DiffusionAuditorFunctionDatasource
  extends PhabricatorTypeaheadCompositeDatasource {

  public function getBrowseTitle() {
    return pht('Browse Auditors');
  }

  public function getPlaceholderText() {
    return pht('Type a user, project, package name or function...');
  }

  public function getDatasourceApplicationClass() {
    return 'PhabricatorDiffusionApplication';
  }

  public function getComponentDatasources() {
    return array(
      new PhabricatorProjectOrUserFunctionDatasource(),
      new PhabricatorOwnersPackageFunctionDatasource(),
    );
  }

}
