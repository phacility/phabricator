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

  private static function parseRevisionIDFromURI($uri) {
    $path = id(new PhutilURI($uri))->getPath();

    $matches = null;
    if (preg_match('#^/D(\d+)$#', $path, $matches)) {
      $id = (int)$matches[1];
      // Make sure the URI is the same as our URI. Basically, we want to ignore
      // commits from other Phabricator installs.
      if ($uri == PhabricatorEnv::getProductionURI('/D'.$id)) {
        return $id;
      }

      $allowed_uris = PhabricatorEnv::getAllowedURIs('/D'.$id);

      foreach ($allowed_uris as $allowed_uri) {
        if ($uri == $allowed_uri) {
          return $id;
        }
      }
    }

    return null;
  }

}
