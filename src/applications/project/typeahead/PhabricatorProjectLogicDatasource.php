<?php

final class PhabricatorProjectLogicDatasource
  extends PhabricatorTypeaheadCompositeDatasource {

  public function getPlaceholderText() {
    return pht('Type a project name or selector...');
  }

  public function getDatasourceApplicationClass() {
    return 'PhabricatorProjectApplication';
  }

  public function getComponentDatasources() {
    return array(
      new PhabricatorProjectNoProjectsDatasource(),
      new PhabricatorProjectLogicalAndDatasource(),
      new PhabricatorProjectLogicalOrNotDatasource(),
    );
  }

}
