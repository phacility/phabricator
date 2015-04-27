<?php

final class PhabricatorPeopleOwnerDatasource
  extends PhabricatorTypeaheadCompositeDatasource {

  public function getBrowseTitle() {
    return pht('Browse Owners');
  }

  public function getPlaceholderText() {
    return pht('Type a username or function...');
  }

  public function getComponentDatasources() {
    return array(
      new PhabricatorViewerDatasource(),
      new PhabricatorPeopleNoOwnerDatasource(),
      new PhabricatorPeopleAnyOwnerDatasource(),
      new PhabricatorPeopleDatasource(),
      new PhabricatorProjectMembersDatasource(),
    );
  }

}
