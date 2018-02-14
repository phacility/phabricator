<?php

final class PhabricatorPeopleDatasourceEngineExtension
  extends PhabricatorDatasourceEngineExtension {

  public function newQuickSearchDatasources() {
    return array(
      new PhabricatorPeopleDatasource(),
    );
  }
}
