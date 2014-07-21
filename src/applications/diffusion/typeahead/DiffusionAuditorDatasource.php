<?php

final class DiffusionAuditorDatasource
  extends PhabricatorTypeaheadCompositeDatasource {

  public function getPlaceholderText() {
    return pht('Type a user, project or package name...');
  }

  public function getDatasourceApplicationClass() {
    return 'PhabricatorApplicationDiffusion';
  }

  public function getComponentDatasources() {
    return array(
      new PhabricatorPeopleDatasource(),
      new PhabricatorProjectDatasource(),
      new PhabricatorOwnersPackageDatasource(),
    );
  }

}
