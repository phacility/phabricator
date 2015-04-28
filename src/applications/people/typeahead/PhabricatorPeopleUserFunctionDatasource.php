<?php

final class PhabricatorPeopleUserFunctionDatasource
  extends PhabricatorTypeaheadCompositeDatasource {

  public function getBrowseTitle() {
    return pht('Browse Users');
  }

  public function getPlaceholderText() {
    return pht('Type a username or function...');
  }

  public function getComponentDatasources() {
    $sources = array(
      new PhabricatorViewerDatasource(),
      new PhabricatorPeopleDatasource(),
      new PhabricatorProjectMembersDatasource(),
    );

    return $sources;
  }

}
