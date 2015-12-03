<?php

final class PhabricatorCustomFieldEditType
  extends PhabricatorEditType {

  private $customField;
  private $valueType;

  public function setCustomField(PhabricatorCustomField $custom_field) {
    $this->customField = $custom_field;
    return $this;
  }

  public function getCustomField() {
    return $this->customField;
  }

  public function setValueType($value_type) {
    $this->valueType = $value_type;
    return $this;
  }

  public function getValueType() {
    return $this->valueType;
  }

  public function getMetadata() {
    $field = $this->getCustomField();
    return parent::getMetadata() + $field->getApplicationTransactionMetadata();
  }

  public function getValueDescription() {
    $field = $this->getCustomField();
    return $field->getFieldDescription();
  }

  public function generateTransactions(
    PhabricatorApplicationTransaction $template,
    array $spec) {

    $value = idx($spec, 'value');

    $xaction = $this->newTransaction($template)
      ->setNewValue($value);

    $custom_type = PhabricatorTransactions::TYPE_CUSTOMFIELD;
    if ($xaction->getTransactionType() == $custom_type) {
      $field = $this->getCustomField();

      $xaction
        ->setOldValue($field->getOldValueForApplicationTransactions())
        ->setMetadataValue('customfield:key', $field->getFieldKey());
    }

    return array($xaction);
  }

}
