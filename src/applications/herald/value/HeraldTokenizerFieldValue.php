<?php

final class HeraldTokenizerFieldValue
  extends HeraldFieldValue {

  private $key;
  private $datasource;

  public function setKey($key) {
    $this->key = $key;
    return $this;
  }

  public function getKey() {
    return $this->key;
  }

  public function setDatasource(PhabricatorTypeaheadDatasource $datasource) {
    $this->datasource = $datasource;
    return $this;
  }

  public function getDatasource() {
    return $this->datasource;
  }

  public function getFieldValueKey() {
    if ($this->getKey() === null) {
      throw new PhutilInvalidStateException('setKey');
    }
    return 'tokenizer.'.$this->getKey();
  }

  public function getControlType() {
    return self::CONTROL_TOKENIZER;
  }

  protected function getControlTemplate() {
    if ($this->getDatasource() === null) {
      throw new PhutilInvalidStateException('setDatasource');
    }

    $datasource = $this->getDatasource();
    $datasource->setViewer($this->getViewer());

    return array(
      'tokenizer' => array(
        'datasourceURI' => $datasource->getDatasourceURI(),
        'browseURI' => $datasource->getBrowseURI(),
        'placeholder' => $datasource->getPlaceholderText(),
      ),
    );
  }

  public function renderFieldValue($value) {
    $viewer = $this->getViewer();
    return $viewer->renderHandleList((array)$value)->setAsInline(true);
  }

}
