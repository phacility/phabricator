<?php

final class DiffusionQuickSearchEngineExtension
  extends PhabricatorQuickSearchEngineExtension {

  public function newQuickSearchDatasources() {
    return array(
      new DiffusionRepositoryDatasource(),
      new DiffusionSymbolDatasource(),
    );
  }
}
