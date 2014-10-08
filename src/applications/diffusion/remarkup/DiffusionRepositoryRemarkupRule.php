<?php

final class DiffusionRepositoryRemarkupRule
  extends PhabricatorObjectRemarkupRule {

  protected function getObjectNamePrefix() {
    return 'r';
  }

  protected function getObjectIDPattern() {
    return '[A-Z]+';
  }

  protected function loadObjects(array $ids) {
    $viewer = $this->getEngine()->getConfig('viewer');

    $repositories = id(new PhabricatorRepositoryQuery())
      ->setViewer($viewer)
      ->withCallsigns($ids)
      ->execute();

    return mpull($repositories, null, 'getCallsign');
  }

}
