<?php

/**
 * Common code for standard field types which store lists of PHIDs.
 */
abstract class PhabricatorStandardCustomFieldPHIDs
  extends PhabricatorStandardCustomField {

  public function buildFieldIndexes() {
    $indexes = array();

    $value = $this->getFieldValue();
    if (is_array($value)) {
      foreach ($value as $phid) {
        $indexes[] = $this->newStringIndex($phid);
      }
    }

    return $indexes;
  }

  public function readValueFromRequest(AphrontRequest $request) {
    $value = $request->getArr($this->getFieldKey());
    $this->setFieldValue($value);
  }

  public function getValueForStorage() {
    $value = $this->getFieldValue();
    if (!$value) {
      return null;
    }

    return json_encode(array_values($value));
  }

  public function setValueFromStorage($value) {
    $result = array();
    if ($value) {
      $value = json_decode($value, true);
      if (is_array($value)) {
        $result = array_values($value);
      }
    }
    $this->setFieldValue($value);
  }

  public function readApplicationSearchValueFromRequest(
    PhabricatorApplicationSearchEngine $engine,
    AphrontRequest $request) {
    return $request->getArr($this->getFieldKey());
  }

  public function applyApplicationSearchConstraintToQuery(
    PhabricatorApplicationSearchEngine $engine,
    PhabricatorCursorPagedPolicyAwareQuery $query,
    $value) {
    if ($value) {
      $query->withApplicationSearchContainsConstraint(
        $this->newStringIndex(null),
        $value);
    }
  }

  public function getRequiredHandlePHIDsForApplicationSearch($value) {
    if ($value) {
      return $value;
    }
    return array();
  }

  public function renderPropertyViewValue() {
    $value = $this->getFieldValue();
    if (!$value) {
      return null;
    }

    // TODO: Surface and batch this.

    $handles = id(new PhabricatorHandleQuery())
      ->setViewer($this->getViewer())
      ->withPHIDs($value)
      ->execute();

    $handles = mpull($handles, 'renderLink');
    $handles = phutil_implode_html(', ', $handles);
    return $handles;
  }

}
