<?php

final class DiffusionCommitRemarkupRule extends PhabricatorObjectRemarkupRule {

  protected function getObjectNamePrefix() {
    return '';
  }

  protected function getObjectNamePrefixBeginsWithWordCharacter() {
    return true;
  }

  protected function getObjectIDPattern() {
    return PhabricatorRepositoryCommitPHIDType::getCommitObjectNamePattern();
  }

  protected function getObjectNameText(
    $object,
    PhabricatorObjectHandle $handle,
    $id) {

    // If this commit is unreachable, return the handle name instead of the
    // normal text because it may be able to tell the user that the commit
    // was rewritten and where to find the new one.

    // By default, we try to preserve what the user actually typed as
    // faithfully as possible, but if they're referencing a deleted commit
    // it's more valuable to try to pick up any rewrite. See T11522.
    if ($object->isUnreachable()) {
      return $handle->getName();
    }

    return parent::getObjectNameText($object, $handle, $id);
  }

  protected function loadObjects(array $ids) {
    $viewer = $this->getEngine()->getConfig('viewer');

    $query = id(new DiffusionCommitQuery())
      ->setViewer($viewer)
      ->withIdentifiers($ids);

    $query->execute();
    return $query->getIdentifierMap();
  }

}
