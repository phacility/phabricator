<?php

final class PhabricatorEdgeEditType extends PhabricatorEditType {

  private $edgeOperation;
  private $valueDescription;

  public function setEdgeOperation($edge_operation) {
    $this->edgeOperation = $edge_operation;
    return $this;
  }

  public function getEdgeOperation() {
    return $this->edgeOperation;
  }

  public function getValueType() {
    return 'list<phid>';
  }

  public function generateTransaction(
    PhabricatorApplicationTransaction $template,
    array $spec) {

    $value = idx($spec, 'value');
    $value = array_fuse($value);
    $value = array(
      $this->getEdgeOperation() => $value,
    );

    $template
      ->setTransactionType($this->getTransactionType())
      ->setNewValue($value);

    foreach ($this->getMetadata() as $key => $value) {
      $template->setMetadataValue($key, $value);
    }

    return $template;
  }

  public function setValueDescription($value_description) {
    $this->valueDescription = $value_description;
    return $this;
  }

  public function getValueDescription() {
    return $this->valueDescription;
  }

}
