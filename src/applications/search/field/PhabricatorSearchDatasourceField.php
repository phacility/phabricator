<?php

final class PhabricatorSearchDatasourceField
  extends PhabricatorSearchTokenizerField {

  private $datasource;

  protected function newDatasource() {
    return id(clone $this->datasource);
  }

  public function setDatasource(PhabricatorTypeaheadDatasource $datasource) {
    $this->datasource = $datasource;
    return $this;
  }

}
