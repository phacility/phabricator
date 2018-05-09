<?php

final class DiffusionIdentityAssigneeDatasource
  extends PhabricatorTypeaheadCompositeDatasource {

  public function getBrowseTitle() {
    return pht('Browse Assignee');
  }

  public function getPlaceholderText() {
    return pht('Type a username or function...');
  }

  public function getComponentDatasources() {
    return array(
      new PhabricatorPeopleDatasource(),
      new DiffusionIdentityUnassignedDatasource(),
    );
  }

}
