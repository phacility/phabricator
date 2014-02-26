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

  public function renderEditControl(array $handles) {
    return $this->newDateControl();
  }

  public function readValueFromRequest(AphrontRequest $request) {
    $control = $this->newDateControl();
    $control->setUser($request->getUser());
    $value = $control->readValueFromRequest($request);

    $this->setFieldValue($value);
  }

  public function renderPropertyViewValue(array $handles) {
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
      ->setCaption($this->getCaption())
      ->setAllowNull(!$this->getRequired());

    // If the value is already numeric, treat it as an epoch timestamp and set
    // it directly. Otherwise, it's likely a field default, which we let users
    // specify as a string. Parse the string into an epoch.

    $value = $this->getFieldValue();
    if (!ctype_digit($value)) {
      $value = PhabricatorTime::parseLocalTime($value, $this->getViewer());
    }

    // If we don't have anything valid, make sure we pass `null`, since the
    // control special-cases that.
    $control->setValue(nonempty($value, null));

    return $control;
  }

  // TODO: Support ApplicationSearch for these fields. We build indexes above,
  // but don't provide a UI for searching. To do so, we need a reasonable date
  // range control and the ability to add a range constraint.

  public function getApplicationTransactionTitle(
    PhabricatorApplicationTransaction $xaction) {
    $author_phid = $xaction->getAuthorPHID();
    $old = $xaction->getOldValue();
    $new = $xaction->getNewValue();

    $viewer = $this->getViewer();

    $old_date = null;
    if ($old) {
      $old_date = phabricator_datetime($old, $viewer);
    }

    $new_date = null;
    if ($new) {
      $new_date = phabricator_datetime($new, $viewer);
    }

    if (!$old) {
      return pht(
        '%s set %s to %s.',
        $xaction->renderHandleLink($author_phid),
        $this->getFieldName(),
        $new_date);
    } else if (!$new) {
      return pht(
        '%s removed %s.',
        $xaction->renderHandleLink($author_phid),
        $this->getFieldName());
    } else {
      return pht(
        '%s changed %s from %s to %s.',
        $xaction->renderHandleLink($author_phid),
        $this->getFieldName(),
        $old_date,
        $new_date);
    }
  }

}
