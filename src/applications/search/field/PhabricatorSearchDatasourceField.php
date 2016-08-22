<?php

final class PhabricatorSearchDatasourceField
  extends PhabricatorSearchTokenizerField {

  private $datasource;
  private $conduitParameterType;

  protected function newDatasource() {
    return id(clone $this->datasource);
  }

  public function setDatasource(PhabricatorTypeaheadDatasource $datasource) {
    $this->datasource = $datasource;
    return $this;
  }

  public function setConduitParameterType(ConduitParameterType $type) {
    $this->conduitParameterType = $type;
    return $this;
  }

  protected function newConduitParameterType() {
    if (!$this->conduitParameterType) {
      return id(new ConduitStringListParameterType())
        ->setAllowEmptyList(false);
    }

    return $this->conduitParameterType;
  }

}
