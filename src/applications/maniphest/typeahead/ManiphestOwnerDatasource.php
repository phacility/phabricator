<?php

final class ManiphestOwnerDatasource
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
      new ManiphestNoOwnerDatasource(),
      new PhabricatorPeopleDatasource(),
      new PhabricatorProjectMembersDatasource(),
    );
  }

}
