<?php

final class ManiphestAssigneeDatasource
  extends PhabricatorTypeaheadCompositeDatasource {

  public function getBrowseTitle() {
    return pht('Browse Assignees');
  }

  public function getPlaceholderText() {
    return pht('Type a username or "none"...');
  }

  public function getComponentDatasources() {
    return array(
      new PhabricatorPeopleDatasource(),
      new PhabricatorPeopleNoOwnerDatasource(),
    );
  }

}
