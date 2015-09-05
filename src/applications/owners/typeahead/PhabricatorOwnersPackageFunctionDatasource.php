<?php

final class PhabricatorOwnersPackageFunctionDatasource
  extends PhabricatorTypeaheadCompositeDatasource {

  public function getBrowseTitle() {
    return pht('Browse Packages');
  }

  public function getPlaceholderText() {
    return pht('Type a package name or function...');
  }

  public function getDatasourceApplicationClass() {
    return 'PhabricatorOwnersApplication';
  }

  public function getComponentDatasources() {
    return array(
      new PhabricatorOwnersPackageDatasource(),
      new PhabricatorOwnersPackageOwnerDatasource(),
    );
  }

}
