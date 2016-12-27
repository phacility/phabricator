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
    // If the value is just "D123" or similar, parse the ID from it directly.
    $value = trim($value);
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
