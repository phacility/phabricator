<?php

final class PhabricatorTypeaheadOwnerDatasource
  extends PhabricatorTypeaheadCompositeDatasource {

  public function getBrowseTitle() {
    return pht('Browse Owners');
  }

  public function getPlaceholderText() {
    return pht('Type a user name or "none"...');
  }

  public function getComponentDatasources() {
    return array(
      new PhabricatorPeopleDatasource(),
      new PhabricatorTypeaheadNoOwnerDatasource(),
    );
  }

}
