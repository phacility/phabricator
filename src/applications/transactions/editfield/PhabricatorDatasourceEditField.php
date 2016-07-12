<?php

final class PhabricatorDatasourceEditField
  extends PhabricatorTokenizerEditField {

  private $datasource;

  public function setDatasource(PhabricatorTypeaheadDatasource $datasource) {
    $this->datasource = $datasource;
    return $this;
  }

  public function getDatasource() {
    if (!$this->datasource) {
      throw new PhutilInvalidStateException('setDatasource');
    }
    return $this->datasource;
  }

  protected function newDatasource() {
    return id(clone $this->getDatasource());
  }

}
