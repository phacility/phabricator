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

  public function buildOrderIndex() {
    return $this->newNumericIndex(0);
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

  public function readApplicationSearchValueFromRequest(
    PhabricatorApplicationSearchEngine $engine,
    AphrontRequest $request) {

    $key = $this->getFieldKey();

    return array(
      'min' => $request->getStr($key.'.min'),
      'max' => $request->getStr($key.'.max'),
    );
  }

  public function applyApplicationSearchConstraintToQuery(
    PhabricatorApplicationSearchEngine $engine,
    PhabricatorCursorPagedPolicyAwareQuery $query,
    $value) {

    $viewer = $this->getViewer();

    if (!is_array($value)) {
      $value = array();
    }

    $min_str = idx($value, 'min', '');
    if (strlen($min_str)) {
      $min = PhabricatorTime::parseLocalTime($min_str, $viewer);
    } else {
      $min = null;
    }

    $max_str = idx($value, 'max', '');
    if (strlen($max_str)) {
      $max = PhabricatorTime::parseLocalTime($max_str, $viewer);
    } else {
      $max = null;
    }

    if (($min !== null) || ($max !== null)) {
      $query->withApplicationSearchRangeConstraint(
        $this->newNumericIndex(null),
        $min,
        $max);
    }
  }

  public function appendToApplicationSearchForm(
    PhabricatorApplicationSearchEngine $engine,
    AphrontFormView $form,
    $value) {

    if (!is_array($value)) {
      $value = array();
    }

    $form
      ->appendChild(
        id(new AphrontFormTextControl())
          ->setLabel(pht('%s After', $this->getFieldName()))
          ->setName($this->getFieldKey().'.min')
          ->setValue(idx($value, 'min', '')))
      ->appendChild(
        id(new AphrontFormTextControl())
          ->setLabel(pht('%s Before', $this->getFieldName()))
          ->setName($this->getFieldKey().'.max')
          ->setValue(idx($value, 'max', '')));
  }

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

  public function getApplicationTransactionTitleForFeed(
    PhabricatorApplicationTransaction $xaction) {

    $viewer = $this->getViewer();

    $author_phid = $xaction->getAuthorPHID();
    $object_phid = $xaction->getObjectPHID();

    $old = $xaction->getOldValue();
    $new = $xaction->getNewValue();

    if (!$old) {
      return pht(
        '%s set %s to %s on %s.',
        $xaction->renderHandleLink($author_phid),
        $this->getFieldName(),
        phabricator_datetime($new, $viewer),
        $xaction->renderHandleLink($object_phid));
    } else if (!$new) {
      return pht(
        '%s removed %s on %s.',
        $xaction->renderHandleLink($author_phid),
        $this->getFieldName(),
        $xaction->renderHandleLink($object_phid));
    } else {
      return pht(
        '%s changed %s from %s to %s on %s.',
        $xaction->renderHandleLink($author_phid),
        $this->getFieldName(),
        phabricator_datetime($old, $viewer),
        phabricator_datetime($new, $viewer),
        $xaction->renderHandleLink($object_phid));
    }
  }

  protected function newConduitSearchParameterType() {
    // TODO: Build a new "pair<epoch|null, epoch|null>" type or similar.
    return null;
  }

  protected function newConduitEditParameterType() {
    return id(new ConduitEpochParameterType())
      ->setAllowNull(!$this->getRequired());
  }

  protected function newExportFieldType() {
    return new PhabricatorEpochExportField();
  }

}
