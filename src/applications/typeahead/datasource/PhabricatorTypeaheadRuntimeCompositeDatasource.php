<?php

final class PhabricatorTypeaheadRuntimeCompositeDatasource
  extends PhabricatorTypeaheadCompositeDatasource {

  private $datasources = array();

  public function getComponentDatasources() {
    return $this->datasources;
  }

  public function getPlaceholderText() {
    throw new Exception(pht('This source is not usable directly.'));
  }

  public function addDatasource(PhabricatorTypeaheadDatasource $source) {
    $this->datasources[] = $source;
    return $this;
  }

}
