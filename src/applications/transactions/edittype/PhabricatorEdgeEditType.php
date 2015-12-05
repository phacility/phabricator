<?php

final class PhabricatorEdgeEditType extends PhabricatorEditType {

  private $edgeOperation;
  private $valueDescription;
  private $datasource;

  public function setEdgeOperation($edge_operation) {
    $this->edgeOperation = $edge_operation;
    return $this;
  }

  public function getEdgeOperation() {
    return $this->edgeOperation;
  }

  public function setDatasource($datasource) {
    $this->datasource = $datasource;
    return $this;
  }

  public function getDatasource() {
    return $this->datasource;
  }

  public function getValueType() {
    return 'list<phid>';
  }

  public function generateTransactions(
    PhabricatorApplicationTransaction $template,
    array $spec) {

    $value = idx($spec, 'value');

    if ($this->getEdgeOperation() !== null) {
      $value = array_fuse($value);
      $value = array(
        $this->getEdgeOperation() => $value,
      );
    }

    $xaction = $this->newTransaction($template)
      ->setNewValue($value);

    return array($xaction);
  }

  public function setValueDescription($value_description) {
    $this->valueDescription = $value_description;
    return $this;
  }

  public function getValueDescription() {
    return $this->valueDescription;
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

    return array(
      'markup' => $template->render(),
      'config' => array(
        'src' => $datasource->getDatasourceURI(),
        'browseURI' => $datasource->getBrowseURI(),
        'placeholder' => $datasource->getPlaceholderText(),
      ),
    );
  }

}
