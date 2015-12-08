<?php

final class PhabricatorEdgeEditType
  extends PhabricatorPHIDListEditType {

  private $edgeOperation;
  private $valueDescription;

  public function setEdgeOperation($edge_operation) {
    $this->edgeOperation = $edge_operation;
    return $this;
  }

  public function getEdgeOperation() {
    return $this->edgeOperation;
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

}
