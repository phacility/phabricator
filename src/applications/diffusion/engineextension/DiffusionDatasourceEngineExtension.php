<?php

final class DiffusionDatasourceEngineExtension
  extends PhabricatorDatasourceEngineExtension {

  public function newQuickSearchDatasources() {
    return array(
      new DiffusionRepositoryDatasource(),
      new DiffusionSymbolDatasource(),
    );
  }
}
