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

    $matches = null;
    if (preg_match('#^/D(\d+)$#', $path, $matches)) {
      $id = (int)$matches[1];

      $prod_uri = new PhutilURI(PhabricatorEnv::getProductionURI('/D'.$id));

      // Make sure the URI is the same as our URI. Basically, we want to ignore
      // commits from other Phabricator installs.
      if ($uri->getDomain() == $prod_uri->getDomain()) {
        return $id;
      }

      $allowed_uris = PhabricatorEnv::getAllowedURIs('/D'.$id);

      foreach ($allowed_uris as $allowed_uri) {
        if ($uri_string == $allowed_uri) {
          return $id;
        }
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
    if (!strlen($value)) {
      return null;
    }

    return PhabricatorEnv::getProductionURI('/D'.$value);
  }

  public function getFieldTransactions($value) {
    return array();
  }

}
