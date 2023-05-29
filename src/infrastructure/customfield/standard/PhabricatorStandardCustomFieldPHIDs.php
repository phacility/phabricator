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
    // NOTE: We're accepting either a JSON string (a real storage value) or
    // an array (from HTTP parameter prefilling). This is a little hacky, but
    // should hold until this can get cleaned up more thoroughly.
    // TODO: Clean this up.

    $result = array();
    if ($value !== null && !is_array($value)) {
      $value = json_decode($value, true);
      if (is_array($value)) {
        $result = array_values($value);
      }
    }

    $this->setFieldValue($value);

    return $this;
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

    $handles = mpull($handles, 'renderHovercardLink');
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

    $old = $this->decodeValue($xaction->getOldValue());
    $new = $this->decodeValue($xaction->getNewValue());

    $add = array_diff($new, $old);
    $rem = array_diff($old, $new);

    return array_merge($add, $rem);
  }

  public function getApplicationTransactionTitle(
    PhabricatorApplicationTransaction $xaction) {
    $author_phid = $xaction->getAuthorPHID();

    $old = $this->decodeValue($xaction->getOldValue());
    $new = $this->decodeValue($xaction->getNewValue());

    $add = array_diff($new, $old);
    $rem = array_diff($old, $new);

    if ($add && !$rem) {
      return pht(
        '%s updated %s, added %d: %s.',
        $xaction->renderHandleLink($author_phid),
        $this->getFieldName(),
        phutil_count($add),
        $xaction->renderHandleList($add));
    } else if ($rem && !$add) {
      return pht(
        '%s updated %s, removed %s: %s.',
        $xaction->renderHandleLink($author_phid),
        $this->getFieldName(),
        phutil_count($rem),
        $xaction->renderHandleList($rem));
    } else {
      return pht(
        '%s updated %s, added %s: %s; removed %s: %s.',
        $xaction->renderHandleLink($author_phid),
        $this->getFieldName(),
        phutil_count($add),
        $xaction->renderHandleList($add),
        phutil_count($rem),
        $xaction->renderHandleList($rem));
    }
  }

  public function getApplicationTransactionTitleForFeed(
    PhabricatorApplicationTransaction $xaction) {
    $author_phid = $xaction->getAuthorPHID();
    $object_phid = $xaction->getObjectPHID();

    $old = $this->decodeValue($xaction->getOldValue());
    $new = $this->decodeValue($xaction->getNewValue());

    $add = array_diff($new, $old);
    $rem = array_diff($old, $new);

    if ($add && !$rem) {
      return pht(
        '%s updated %s for %s, added %d: %s.',
        $xaction->renderHandleLink($author_phid),
        $this->getFieldName(),
        $xaction->renderHandleLink($object_phid),
        phutil_count($add),
        $xaction->renderHandleList($add));
    } else if ($rem && !$add) {
      return pht(
        '%s updated %s for %s, removed %s: %s.',
        $xaction->renderHandleLink($author_phid),
        $this->getFieldName(),
        $xaction->renderHandleLink($object_phid),
        phutil_count($rem),
        $xaction->renderHandleList($rem));
    } else {
      return pht(
        '%s updated %s for %s, added %s: %s; removed %s: %s.',
        $xaction->renderHandleLink($author_phid),
        $this->getFieldName(),
        $xaction->renderHandleLink($object_phid),
        phutil_count($add),
        $xaction->renderHandleList($add),
        phutil_count($rem),
        $xaction->renderHandleList($rem));
    }
  }

  public function validateApplicationTransactions(
    PhabricatorApplicationTransactionEditor $editor,
    $type,
    array $xactions) {

    $errors = parent::validateApplicationTransactions(
      $editor,
      $type,
      $xactions);

    // If the user is adding PHIDs, make sure the new PHIDs are valid and
    // visible to the actor. It's OK for a user to edit a field which includes
    // some invalid or restricted values, but they can't add new ones.

    foreach ($xactions as $xaction) {
      $old = $this->decodeValue($xaction->getOldValue());
      $new = $this->decodeValue($xaction->getNewValue());

      $add = array_diff($new, $old);

      $invalid = PhabricatorObjectQuery::loadInvalidPHIDsForViewer(
        $editor->getActor(),
        $add);

      if ($invalid) {
        $error = new PhabricatorApplicationTransactionValidationError(
          $type,
          pht('Invalid'),
          pht(
            'Some of the selected PHIDs in field "%s" are invalid or '.
            'restricted: %s.',
            $this->getFieldName(),
            implode(', ', $invalid)),
          $xaction);
        $errors[] = $error;
        $this->setFieldError(pht('Invalid'));
      }
    }

    return $errors;
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

  public function getHeraldFieldStandardType() {
    return HeraldField::STANDARD_PHID_NULLABLE;
  }

  public function getHeraldFieldValue() {
    // If the field has a `null` value, make sure we hand an `array()` to
    // Herald.
    $value = parent::getHeraldFieldValue();
    if ($value) {
      return $value;
    }
    return array();
  }

  protected function decodeValue($value) {
    if ($value === null) {
      return array();
    }

    $value = json_decode($value);
    if (!is_array($value)) {
      $value = array();
    }

    return $value;
  }

  protected function getHTTPParameterType() {
    return new AphrontPHIDListHTTPParameterType();
  }

}
