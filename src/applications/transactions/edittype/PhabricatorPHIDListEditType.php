<?php

abstract class PhabricatorPHIDListEditType
  extends PhabricatorEditType {

  private $datasource;
  private $isSingleValue;
  private $defaultValue;

  public function setDatasource(PhabricatorTypeaheadDatasource $datasource) {
    $this->datasource = $datasource;
    return $this;
  }

  public function getDatasource() {
    return $this->datasource;
  }

  public function setIsSingleValue($is_single_value) {
    $this->isSingleValue = $is_single_value;
    return $this;
  }

  public function getIsSingleValue() {
    return $this->isSingleValue;
  }

  public function setDefaultValue(array $default_value) {
    $this->defaultValue = $default_value;
    return $this;
  }

  public function getDefaultValue() {
    return $this->defaultValue;
  }

  public function getValueType() {
    if ($this->getIsSingleValue()) {
      return 'phid';
    } else {
      return 'list<phid>';
    }
  }

  public function getPHUIXControlType() {
    $datasource = $this->getDatasource();

    if (!$datasource) {
      return null;
    }

    return 'tokenizer';
  }

  public function getPHUIXControlSpecification() {
    $datasource = $this->getDatasource();

    if (!$datasource) {
      return null;
    }

    $template = new AphrontTokenizerTemplateView();

    if ($this->getIsSingleValue()) {
      $limit = 1;
    } else {
      $limit = null;
    }

    $default = $this->getDefaultValue();
    if ($default) {
      $value = $datasource->getWireTokens($default);
    } else {
      $value = array();
    }

    return array(
      'markup' => $template->render(),
      'config' => array(
        'src' => $datasource->getDatasourceURI(),
        'browseURI' => $datasource->getBrowseURI(),
        'placeholder' => $datasource->getPlaceholderText(),
        'limit' => $limit,
      ),
      'value' => $value,
    );
  }

  public function getCommentActionValueFromDraftValue($value) {
    $datasource = $this->getDatasource();

    if (!$datasource) {
      return array();
    }

    if (!is_array($value)) {
      return array();
    }

    return $datasource->getWireTokens($value);
  }


}
