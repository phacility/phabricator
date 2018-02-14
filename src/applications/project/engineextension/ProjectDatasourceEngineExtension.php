<?php

final class ProjectDatasourceEngineExtension
  extends PhabricatorDatasourceEngineExtension {

  public function newQuickSearchDatasources() {
    return array(
      new PhabricatorProjectDatasource(),
    );
  }
}
