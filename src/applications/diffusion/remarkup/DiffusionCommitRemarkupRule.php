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

  protected function loadObjects(array $ids) {
    $viewer = $this->getEngine()->getConfig('viewer');

    $query = id(new DiffusionCommitQuery())
      ->setViewer($viewer)
      ->withIdentifiers($ids);

    $query->execute();
    return $query->getIdentifierMap();
  }

}
