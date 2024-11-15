<?php

final class PhabricatorRoleOrUserFunctionDatasource
  extends PhabricatorTypeaheadCompositeDatasource {

  public function getBrowseTitle() {
    return pht('Browse Users and Roles');
  }

  public function getPlaceholderText() {
    return pht('Type a user, role name, or function...');
  }

  public function getComponentDatasources() {
    return array(
      new PhabricatorViewerDatasource(),
      new PhabricatorPeopleDatasource(),
      new PhabricatorRoleDatasource(),
      new PhabricatorRoleMembersDatasource(),
      new PhabricatorRoleUserFunctionDatasource(),
    );
  }


}
