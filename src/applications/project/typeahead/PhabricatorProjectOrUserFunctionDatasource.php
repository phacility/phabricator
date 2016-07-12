<?php

final class PhabricatorProjectOrUserFunctionDatasource
  extends PhabricatorTypeaheadCompositeDatasource {

  public function getBrowseTitle() {
    return pht('Browse Users and Projects');
  }

  public function getPlaceholderText() {
    return pht('Type a user, project name, or function...');
  }

  public function getComponentDatasources() {
    return array(
      new PhabricatorViewerDatasource(),
      new PhabricatorPeopleDatasource(),
      new PhabricatorProjectDatasource(),
      new PhabricatorProjectMembersDatasource(),
      new PhabricatorProjectUserFunctionDatasource(),
    );
  }


}
