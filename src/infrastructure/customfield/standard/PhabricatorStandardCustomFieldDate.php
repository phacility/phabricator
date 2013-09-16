<?php

final class PhabricatorStandardCustomFieldDate
  extends PhabricatorStandardCustomField {

  public function getFieldType() {
    return 'date';
  }

  public function buildFieldIndexes() {
    $indexes = array();

    $value = $this->getFieldValue();
    if (strlen($value)) {
      $indexes[] = $this->newNumericIndex((int)$value);
    }

    return $indexes;
  }

  public function getValueForStorage() {
    $value = $this->getFieldValue();
    if (strlen($value)) {
      return (int)$value;
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

  public function renderEditControl() {
    return $this->newDateControl();
  }

  public function readValueFromRequest(AphrontRequest $request) {
    $control = $this->newDateControl();
    $control->setUser($request->getUser());
    $value = $control->readValueFromRequest($request);

    $this->setFieldValue($value);
  }

  public function renderPropertyViewValue() {
    $value = $this->getFieldValue();
    if (!$value) {
      return null;
    }

    return phabricator_datetime($value, $this->getViewer());
  }

  private function newDateControl() {
    $control = id(new AphrontFormDateControl())
      ->setLabel($this->getFieldName())
      ->setName($this->getFieldKey())
      ->setUser($this->getViewer())
      ->setAllowNull(true);

    $control->setValue($this->getFieldValue());

    return $control;
  }

  // TODO: Support ApplicationSearch for these fields. We build indexes above,
  // but don't provide a UI for searching. To do so, we need a reasonable date
  // range control and the ability to add a range constraint.

}
