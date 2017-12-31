<?php

final class PhabricatorPeopleQuickSearchEngineExtension
  extends PhabricatorQuickSearchEngineExtension {

  public function newQuickSearchDatasources() {
    return array(
      new PhabricatorPeopleDatasource(),
    );
  }
}
