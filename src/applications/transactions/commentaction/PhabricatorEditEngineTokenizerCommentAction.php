<?php

final class PhabricatorEditEngineTokenizerCommentAction
  extends PhabricatorEditEngineCommentAction {

  private $datasource;
  private $limit;

  public function setDatasource(PhabricatorTypeaheadDatasource $datasource) {
    $this->datasource = $datasource;
    return $this;
  }

  public function getDatasource() {
    return $this->datasource;
  }

  public function setLimit($limit) {
    $this->limit = $limit;
    return $this;
  }

  public function getLimit() {
    return $this->limit;
  }

  public function getPHUIXControlType() {
    return 'tokenizer';
  }

  public function getPHUIXControlSpecification() {
    $template = new AphrontTokenizerTemplateView();

    $datasource = $this->getDatasource();
    $limit = $this->getLimit();

    $value = $this->getValue();
    if (!$value) {
      $value = array();
    }
    $value = $datasource->getWireTokens($value);

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

}
