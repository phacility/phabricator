<?php

final class PhabricatorRoleOrUserDatasource
  extends PhabricatorTypeaheadCompositeDatasource {

  public function getBrowseTitle() {
    return pht('Browse Users and Roles');
  }

  public function getPlaceholderText() {
    return pht('Type a user or role name...');
  }

  public function getComponentDatasources() {
    return array(
      new PhabricatorPeopleDatasource(),
      new PhabricatorRoleDatasource(),
    );
  }

}
