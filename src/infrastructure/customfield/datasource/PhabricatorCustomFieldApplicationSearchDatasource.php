<?php

final class PhabricatorCustomFieldApplicationSearchDatasource
  extends PhabricatorTypeaheadProxyDatasource {

  public function getComponentDatasources() {
    $datasources = parent::getComponentDatasources();

    $datasources[] =
      new PhabricatorCustomFieldApplicationSearchAnyFunctionDatasource();
    $datasources[] =
      new PhabricatorCustomFieldApplicationSearchNoneFunctionDatasource();

    return $datasources;
  }

}
