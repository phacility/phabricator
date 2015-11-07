<?php

final class PhabricatorDatasourceEditField
  extends PhabricatorTokenizerEditField {

  private $datasource;

  public function setDatasource(PhabricatorTypeaheadDatasource $datasource) {
    $this->datasource = $datasource;
    return $this;
  }

  public function getDatasource() {
    return $this->datasource;
  }

  protected function newDatasource() {
    return id(clone $this->getDatasource());
  }

}
