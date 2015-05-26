<?php

final class PhabricatorMetaMTAMailableFunctionDatasource
  extends PhabricatorTypeaheadCompositeDatasource {

  public function getBrowseTitle() {
    return pht('Browse Subscribers');
  }

  public function getPlaceholderText() {
    return pht('Type a username, project, mailing list, or function...');
  }

  public function getDatasourceApplicationClass() {
    return 'PhabricatorMetaMTAApplication';
  }

  public function getComponentDatasources() {
    return array(
      new PhabricatorViewerDatasource(),
      new PhabricatorPeopleDatasource(),
      new PhabricatorProjectMembersDatasource(),
      new PhabricatorProjectDatasource(),
      new PhabricatorMailingListDatasource(),
    );
  }

}
