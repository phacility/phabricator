<?php

final class PhabricatorSimpleEditType extends PhabricatorEditType {

  private $valueType;
  private $valueDescription;

  public function setValueType($value_type) {
    $this->valueType = $value_type;
    return $this;
  }

  public function getValueType() {
    return $this->valueType;
  }

  public function generateTransaction(
    PhabricatorApplicationTransaction $template,
    array $spec) {

    $template
      ->setTransactionType($this->getTransactionType())
      ->setNewValue(idx($spec, 'value'));

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
