<?php

final class PhabricatorQuickSearchApplicationEngineExtension
  extends PhabricatorQuickSearchEngineExtension {

  public function newQuickSearchDatasources() {
    return array(
      new PhabricatorApplicationDatasource(),
    );
  }
}
