<?php

final class PhabricatorCustomFieldEditType
  extends PhabricatorEditType {

  private $customField;

  public function setCustomField(PhabricatorCustomField $custom_field) {
    $this->customField = $custom_field;
    return $this;
  }

  public function getCustomField() {
    return $this->customField;
  }

  public function getMetadata() {
    $field = $this->getCustomField();
    return parent::getMetadata() + $field->getApplicationTransactionMetadata();
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
