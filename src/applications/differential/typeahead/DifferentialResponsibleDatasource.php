<?php

final class DifferentialResponsibleDatasource
  extends PhabricatorTypeaheadCompositeDatasource {

  public function getBrowseTitle() {
    return pht('Browse Responsible Users');
  }

  public function getPlaceholderText() {
    return pht('Type a user, project, or package name, or function...');
  }

  public function getDatasourceApplicationClass() {
    return 'PhabricatorDifferentialApplication';
  }

  public function getComponentDatasources() {
    return array(
      new DifferentialResponsibleUserDatasource(),
      new DifferentialExactUserFunctionDatasource(),
      new PhabricatorProjectDatasource(),
      new PhabricatorOwnersPackageDatasource(),
    );
  }

}
