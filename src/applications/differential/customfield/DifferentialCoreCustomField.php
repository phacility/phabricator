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

      if (is_string($value)) {
        $parser = $this->getFieldParser();
        $result = $parser->parseCorpus($value);

        unset($result['__title__']);
        unset($result['__summary__']);

        if ($result) {
          $error = new PhabricatorApplicationTransactionValidationError(
            $type,
            pht('Invalid'),
            pht(
              'The value you have entered in "%s" can not be parsed '.
              'unambiguously when rendered in a commit message. Edit the '.
              'message so that keywords like "Summary:" and "Test Plan:" do '.
              'not appear at the beginning of lines. Parsed keys: %s.',
              $this->getFieldName(),
              implode(', ', array_keys($result))),
            $xaction);
          $errors[] = $error;
          $this->setFieldError(pht('Invalid'));
          continue;
        }
      }
    }

    return $errors;
  }

  private function getFieldParser() {
    if (!$this->fieldParser) {
      $viewer = $this->getViewer();
      $parser = DifferentialCommitMessageParser::newStandardParser($viewer);

      // Set custom title and summary keys so we can detect the presence of
      // "Summary:" in, e.g., a test plan.
      $parser->setTitleKey('__title__');
      $parser->setSummaryKey('__summary__');

      $this->fieldParser = $parser;
    }

    return $this->fieldParser;
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

  public function readValueFromCommitMessage($value) {
    $this->setValue($value);
    return $this;
  }

  public function renderCommitMessageValue(array $handles) {
    return $this->getValue();
  }

  public function getConduitDictionaryValue() {
    return $this->getValue();
  }

}
