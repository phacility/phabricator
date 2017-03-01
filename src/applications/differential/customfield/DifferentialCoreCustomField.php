<?php

/**
 * Base class for Differential fields with storage on the revision object
 * itself. This mostly wraps reading/writing field values to and from the
 * object.
 */
abstract class DifferentialCoreCustomField
  extends DifferentialCustomField {

  private $value;
  private $fieldError;
  private $fieldParser;

  abstract protected function readValueFromRevision(
    DifferentialRevision $revision);

  protected function writeValueToRevision(
    DifferentialRevision $revision,
    $value) {
    throw new PhabricatorCustomFieldImplementationIncompleteException($this);
  }

  protected function isCoreFieldRequired() {
    return false;
  }

  protected function isCoreFieldValueEmpty($value) {
    if (is_array($value)) {
      return !$value;
    }
    return !strlen(trim($value));
  }

  protected function getCoreFieldRequiredErrorString() {
    throw new PhabricatorCustomFieldImplementationIncompleteException($this);
  }

  public function validateApplicationTransactions(
    PhabricatorApplicationTransactionEditor $editor,
    $type,
    array $xactions) {

    $this->setFieldError(null);

    $errors = parent::validateApplicationTransactions(
      $editor,
      $type,
      $xactions);

    $transaction = null;
    foreach ($xactions as $xaction) {
      $value = $xaction->getNewValue();
      if ($this->isCoreFieldRequired()) {
        if ($this->isCoreFieldValueEmpty($value)) {
          $error = new PhabricatorApplicationTransactionValidationError(
            $type,
            pht('Required'),
            $this->getCoreFieldRequiredErrorString(),
            $xaction);
          $error->setIsMissingFieldError(true);
          $errors[] = $error;
          $this->setFieldError(pht('Required'));
          continue;
        }
      }
    }

    return $errors;
  }

  public function canDisableField() {
    return false;
  }

  public function shouldAppearInApplicationTransactions() {
    return true;
  }

  public function readValueFromObject(PhabricatorCustomFieldInterface $object) {
    if ($this->isCoreFieldRequired()) {
      $this->setFieldError(true);
    }
    $this->setValue($this->readValueFromRevision($object));
  }

  public function getOldValueForApplicationTransactions() {
    return $this->readValueFromRevision($this->getObject());
  }

  public function getNewValueForApplicationTransactions() {
    return $this->getValue();
  }

  public function applyApplicationTransactionInternalEffects(
    PhabricatorApplicationTransaction $xaction) {
    $this->writeValueToRevision($this->getObject(), $xaction->getNewValue());
  }

  public function setFieldError($field_error) {
    $this->fieldError = $field_error;
    return $this;
  }

  public function getFieldError() {
    return $this->fieldError;
  }

  public function setValue($value) {
    $this->value = $value;
    return $this;
  }

  public function getValue() {
    return $this->value;
  }

  public function getConduitDictionaryValue() {
    return $this->getValue();
  }

}
