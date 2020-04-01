<?php

final class HeraldTokenizerFieldValue
  extends HeraldFieldValue {

  private $key;
  private $datasource;
  private $valueMap;

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

  public function setValueMap(array $value_map) {
    $this->valueMap = $value_map;
    return $this;
  }

  public function getValueMap() {
    return $this->valueMap;
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
        'limit' => $datasource->getLimit(),
      ),
    );
  }

  public function renderFieldValue($value) {
    return $this->renderValueAsList($value, $for_transcript = false);
  }

  public function renderEditorValue($value) {
    $viewer = $this->getViewer();
    $value = (array)$value;

    $datasource = $this->getDatasource()
      ->setViewer($viewer);

    return $datasource->getWireTokens($value);
  }

  public function renderTranscriptValue($value) {
    return $this->renderValueAsList($value, $for_transcript = true);
  }

  private function renderValueAsList($value, $for_transcript) {
    $viewer = $this->getViewer();
    $value = (array)$value;

    if (!$value) {
      return phutil_tag('em', array(), pht('None'));
    }

    if ($this->valueMap !== null) {
      foreach ($value as $k => $v) {
        $value[$k] = idx($this->valueMap, $v, $v);
      }

      return implode(', ', $value);
    }

    $list = $viewer->renderHandleList($value);

    if (!$for_transcript) {
      $list->setAsInline(true);
    }

    return $list;
  }

}
