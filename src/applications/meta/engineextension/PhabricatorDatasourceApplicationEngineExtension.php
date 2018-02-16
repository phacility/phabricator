<?php

final class PhabricatorDatasourceApplicationEngineExtension
  extends PhabricatorDatasourceEngineExtension {

  public function newQuickSearchDatasources() {
    return array(
      new PhabricatorApplicationDatasource(),
    );
  }
}
