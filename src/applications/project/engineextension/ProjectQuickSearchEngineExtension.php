<?php

final class ProjectQuickSearchEngineExtension
  extends PhabricatorQuickSearchEngineExtension {

  public function newQuickSearchDatasources() {
    return array(
      new PhabricatorProjectDatasource(),
    );
  }
}
