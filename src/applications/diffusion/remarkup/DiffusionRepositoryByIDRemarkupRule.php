<?php

final class DiffusionRepositoryByIDRemarkupRule
  extends PhabricatorObjectRemarkupRule {

  protected function getObjectNamePrefix() {
    return 'R';
  }

  protected function getObjectIDPattern() {
    return '[0-9]+';
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
