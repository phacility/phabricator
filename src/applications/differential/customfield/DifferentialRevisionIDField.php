<?php

final class DifferentialRevisionIDField
  extends DifferentialCustomField {

  private $revisionID;

  public function getFieldKey() {
    return 'differential:revision-id';
  }

  public function getFieldKeyForConduit() {
    return 'revisionID';
  }

  public function getFieldName() {
    return pht('Differential Revision');
  }

  public function getFieldDescription() {
    return pht(
      'Ties commits to revisions and provides a permananent link between '.
      'them.');
  }

  public function canDisableField() {
    return false;
  }

  public function shouldAppearInCommitMessage() {
    return true;
  }

  public function parseValueFromCommitMessage($value) {
    // If the value is just "D123" or similar, parse the ID from it directly.
    $value = trim($value);
    $matches = null;
    if (preg_match('/^[dD]([1-9]\d*)\z/', $value, $matches)) {
      return (int)$matches[1];
    }

    // Otherwise, try to extract a URI value.
    return self::parseRevisionIDFromURI($value);
  }

  public function renderCommitMessageValue(array $handles) {
    $id = coalesce($this->revisionID, $this->getObject()->getID());
    if (!$id) {
      return null;
    }
    return PhabricatorEnv::getProductionURI('/D'.$id);
  }

  public function readValueFromCommitMessage($value) {
    $this->revisionID = $value;
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

}
