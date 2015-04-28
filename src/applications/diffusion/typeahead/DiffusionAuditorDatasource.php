<?php

final class DiffusionAuditorDatasource
  extends PhabricatorTypeaheadCompositeDatasource {

  public function getBrowseTitle() {
    return pht('Browse Auditors');
  }

  public function getPlaceholderText() {
    return pht('Type a user, project or package name...');
  }

  public function getDatasourceApplicationClass() {
    return 'PhabricatorDiffusionApplication';
  }

  public function getComponentDatasources() {
    return array(
      new PhabricatorPeopleDatasource(),
      new PhabricatorProjectDatasource(),
      new PhabricatorOwnersPackageDatasource(),
    );
  }

}
