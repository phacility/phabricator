<?php

final class PhabricatorMonogramDatasourceEngineExtension
  extends PhabricatorDatasourceEngineExtension {

  public function newQuickSearchDatasources() {
    return array(
      new PhabricatorTypeaheadMonogramDatasource(),
    );
  }
}
