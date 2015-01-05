<?php

final class DiffusionRepositoryRemarkupRule
  extends PhabricatorObjectRemarkupRule {

  protected function getObjectNamePrefix() {
    return 'r';
  }

  protected function getObjectIDPattern() {
    return '[A-Z]+';
  }

  public function getPriority() {
    return 460.0;
  }

  protected function loadObjects(array $ids) {
    $viewer = $this->getEngine()->getConfig('viewer');

    $repos = id(new PhabricatorRepositoryQuery())
      ->setViewer($viewer)
      ->withIdentifiers($ids);

    $repos->execute();
    return $repos->getIdentifierMap();
  }

}
