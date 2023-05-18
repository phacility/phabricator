<?php

final class DifferentialRevisionIDCommitMessageField
  extends DifferentialCommitMessageField {

  const FIELDKEY = 'revisionID';

  public function getFieldName() {
    return pht('Differential Revision');
  }

  public function getFieldOrder() {
    return 200000;
  }

  public function isTemplateField() {
    return false;
  }

  public function parseFieldValue($value) {
    // If the complete commit message we are parsing has unrecognized custom
    // fields at the end, they can end up parsed into the field value for this
    // field. For example, if the message looks like this:

    // Differential Revision: xyz
    // Some-Other-Field: abc

    // ...we will receive "xyz\nSome-Other-Field: abc" as the field value for
    // this field. Ideally, the install would define these fields so they can
    // parse formally, but we can reasonably assume that only the first line
    // of any value we encounter actually contains a revision identifier, so
    // start by throwing away any other lines.

    $value = trim($value);
    $value = phutil_split_lines($value, false);
    $value = head($value);
    $value = trim($value);

    // If the value is just "D123" or similar, parse the ID from it directly.
    $matches = null;
    if (preg_match('/^[dD]([1-9]\d*)\z/', $value, $matches)) {
      return (int)$matches[1];
    }

    // Otherwise, try to extract a URI value.
    return self::parseRevisionIDFromURI($value);
  }

  private static function parseRevisionIDFromURI($uri_string) {
    $uri = new PhutilURI($uri_string);
    $path = $uri->getPath();

    if (PhabricatorEnv::isSelfURI($uri_string)) {
      $matches = null;
      if (preg_match('#^/D(\d+)$#', $path, $matches)) {
        return (int)$matches[1];
      }
    }

    return null;
  }

  public function readFieldValueFromObject(DifferentialRevision $revision) {
    return $revision->getID();
  }

  public function readFieldValueFromConduit($value) {
    if (is_int($value)) {
      $value = (string)$value;
    }
    return $this->readStringFieldValueFromConduit($value);
  }

  public function renderFieldValue($value) {
    if ($value === null || !strlen($value)) {
      return null;
    }

    return PhabricatorEnv::getProductionURI('/D'.$value);
  }

  public function getFieldTransactions($value) {
    return array();
  }

}
