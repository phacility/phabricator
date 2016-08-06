<?php

final class HarbormasterBuildInitiatorDatasource
  extends PhabricatorTypeaheadCompositeDatasource {

  public function getBrowseTitle() {
    return pht('Browse Build Initiators');
  }

  public function getPlaceholderText() {
    return pht('Type the name of a user, application or Herald rule...');
  }

  public function getComponentDatasources() {
    return array(
      new PhabricatorApplicationDatasource(),
      new PhabricatorPeopleUserFunctionDatasource(),
      new HeraldRuleDatasource(),
    );
  }

}
