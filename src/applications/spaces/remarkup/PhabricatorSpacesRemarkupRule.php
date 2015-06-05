<?php

final class PhabricatorSpacesRemarkupRule
  extends PhabricatorObjectRemarkupRule {

  protected function getObjectNamePrefix() {
    return 'S';
  }

  protected function loadObjects(array $ids) {
    $viewer = $this->getEngine()->getConfig('viewer');
    return id(new PhabricatorSpacesNamespaceQuery())
      ->setViewer($viewer)
      ->withIDs($ids)
      ->execute();
  }

}
