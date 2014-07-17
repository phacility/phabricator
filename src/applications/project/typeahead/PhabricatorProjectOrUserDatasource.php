<?php

final class PhabricatorProjectOrUserDatasource
  extends PhabricatorTypeaheadCompositeDatasource {

  public function getPlaceholderText() {
    return pht('Type a user or project name...');
  }

  public function getComponentDatasources() {
    return array(
      new PhabricatorPeopleDatasource(),
      new PhabricatorProjectDatasource(),
    );
  }

}
