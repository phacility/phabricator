<?php

final class PhabricatorStandardCustomFieldInt
  extends PhabricatorStandardCustomField {

  public function getFieldType() {
    return 'int';
  }

  public function buildFieldIndexes() {
    $indexes = array();

    $value = $this->getFieldValue();
    if (strlen($value)) {
      $indexes[] = $this->newNumericIndex((int)$value);
    }

    return $indexes;
  }

  public function buildOrderIndex() {
    return $this->newNumericIndex(0);
  }

  public function getValueForStorage() {
    $value = $this->getFieldValue();
    if (strlen($value)) {
      return $value;
    } else {
      return null;
    }
  }

  public function setValueFromStorage($value) {
    if (strlen($value)) {
      $value = (int)$value;
    } else {
      $value = null;
    }
    return $this->setFieldValue($value);
  }

  public function readApplicationSearchValueFromRequest(
    PhabricatorApplicationSearchEngine $engine,
    AphrontRequest $request) {

    return $request->getStr($this->getFieldKey());
  }

  public function applyApplicationSearchConstraintToQuery(
    PhabricatorApplicationSearchEngine $engine,
    PhabricatorCursorPagedPolicyAwareQuery $query,
    $value) {

    if (strlen($value)) {
      $query->withApplicationSearchContainsConstraint(
        $this->newNumericIndex(null),
        $value);
    }
  }

  public function appendToApplicationSearchForm(
    PhabricatorApplicationSearchEngine $engine,
    AphrontFormView $form,
    $value) {

    $form->appendChild(
      id(new AphrontFormTextControl())
        ->setLabel($this->getFieldName())
        ->setName($this->getFieldKey())
        ->setValue($value));
  }

  public function validateApplicationTransactions(
    PhabricatorApplicationTransactionEditor $editor,
    $type,
    array $xactions) {

    $errors = parent::validateApplicationTransactions(
      $editor,
      $type,
      $xactions);

    foreach ($xactions as $xaction) {
      $value = $xaction->getNewValue();
      if (strlen($value)) {
        if (!preg_match('/^-?\d+/', $value)) {
          $errors[] = new PhabricatorApplicationTransactionValidationError(
            $type,
            pht('Invalid'),
            pht('%s must be an integer.', $this->getFieldName()),
            $xaction);
          $this->setFieldError(pht('Invalid'));
        }
      }
    }

    return $errors;
  }

  public function getApplicationTransactionHasEffect(
    PhabricatorApplicationTransaction $xaction) {

    $old = $xaction->getOldValue();
    $new = $xaction->getNewValue();
    if (!strlen($old) && strlen($new)) {
      return true;
    } else if (strlen($old) && !strlen($new)) {
      return true;
    } else {
      return ((int)$old !== (int)$new);
    }
  }

  protected function getHTTPParameterType() {
    return new AphrontIntHTTPParameterType();
  }

  protected function newConduitSearchParameterType() {
    return new ConduitIntParameterType();
  }

  protected function newConduitEditParameterType() {
    return new ConduitIntParameterType();
  }

}
