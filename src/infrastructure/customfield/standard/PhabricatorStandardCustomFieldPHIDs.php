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

  public function getRequiredHandlePHIDsForPropertyView() {
    $value = $this->getFieldValue();
    if ($value) {
      return $value;
    }
    return array();
  }

  public function renderPropertyViewValue(array $handles) {
    $value = $this->getFieldValue();
    if (!$value) {
      return null;
    }

    $handles = mpull($handles, 'renderLink');
    $handles = phutil_implode_html(', ', $handles);
    return $handles;
  }

  public function getRequiredHandlePHIDsForEdit() {
    $value = $this->getFieldValue();
    if ($value) {
      return $value;
    } else {
      return array();
    }
  }

  public function getApplicationTransactionRequiredHandlePHIDs(
    PhabricatorApplicationTransaction $xaction) {

    $old = json_decode($xaction->getOldValue());
    if (!is_array($old)) {
      $old = array();
    }

    $new = json_decode($xaction->getNewValue());
    if (!is_array($new)) {
      $new = array();
    }

    $add = array_diff($new, $old);
    $rem = array_diff($old, $new);

    return array_merge($add, $rem);
  }

  public function getApplicationTransactionTitle(
    PhabricatorApplicationTransaction $xaction) {
    $author_phid = $xaction->getAuthorPHID();

    $old = json_decode($xaction->getOldValue());
    if (!is_array($old)) {
      $old = array();
    }

    $new = phutil_json_decode($xaction->getNewValue());
    if (!is_array($new)) {
      $new = array();
    }

    $add = array_diff($new, $old);
    $rem = array_diff($old, $new);

    if ($add && !$rem) {
      return pht(
        '%s updated %s, added %d: %s.',
        $xaction->renderHandleLink($author_phid),
        $this->getFieldName(),
        new PhutilNumber(count($add)),
        $xaction->renderHandleList($add));
    } else if ($rem && !$add) {
      return pht(
        '%s updated %s, removed %d: %s.',
        $xaction->renderHandleLink($author_phid),
        $this->getFieldName(),
        new PhutilNumber(count($rem)),
        $xaction->renderHandleList($rem));
    } else {
      return pht(
        '%s updated %s, added %d: %s; removed %d: %s.',
        $xaction->renderHandleLink($author_phid),
        $this->getFieldName(),
        new PhutilNumber(count($add)),
        $xaction->renderHandleList($add),
        new PhutilNumber(count($rem)),
        $xaction->renderHandleList($rem));
    }
  }

  public function shouldAppearInHerald() {
    return true;
  }

  public function getHeraldFieldConditions() {
    return array(
      HeraldAdapter::CONDITION_INCLUDE_ALL,
      HeraldAdapter::CONDITION_INCLUDE_ANY,
      HeraldAdapter::CONDITION_INCLUDE_NONE,
      HeraldAdapter::CONDITION_EXISTS,
      HeraldAdapter::CONDITION_NOT_EXISTS,
    );
  }

}
