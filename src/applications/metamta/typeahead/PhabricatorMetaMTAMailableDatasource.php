<?php

final class PhabricatorMetaMTAMailableDatasource
  extends PhabricatorTypeaheadCompositeDatasource {

  public function getBrowseTitle() {
    return pht('Browse Subscribers');
  }

  public function getPlaceholderText() {
    return pht('Type a user, project, package, or mailing list name...');
  }

  public function getDatasourceApplicationClass() {
    return 'PhabricatorMetaMTAApplication';
  }

  public function getComponentDatasources() {
    return array(
      new PhabricatorPeopleDatasource(),
      new PhabricatorProjectDatasource(),
      new PhabricatorOwnersPackageDatasource(),
    );
  }

}
