<?php

final class PhabricatorRoleLogicalDatasource
  extends PhabricatorTypeaheadCompositeDatasource {

  public function getBrowseTitle() {
    return pht('Browse Roles');
  }

  public function getPlaceholderText() {
    return pht('Type a role name or function...');
  }

  public function getDatasourceApplicationClass() {
    return 'PhabricatorRoleApplication';
  }

  public function getComponentDatasources() {
    return array(
      new PhabricatorRoleNoRolesDatasource(),
      new PhabricatorRoleLogicalAncestorDatasource(),
      new PhabricatorRoleLogicalOrNotDatasource(),
      new PhabricatorRoleLogicalViewerDatasource(),
      new PhabricatorRoleLogicalOnlyDatasource(),
      new PhabricatorRoleLogicalUserDatasource(),
    );
  }

}
