<?php

final class PhabricatorMonogramQuickSearchEngineExtension
  extends PhabricatorQuickSearchEngineExtension {

  public function newQuickSearchDatasources() {
    return array(
      new PhabricatorTypeaheadMonogramDatasource(),
    );
  }
}
