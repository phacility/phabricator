<?php

final class LegalpadDocumentRemarkupRule extends PhabricatorObjectRemarkupRule {

  protected function getObjectNamePrefix() {
    return 'L';
  }

  protected function loadObjects(array $ids) {
    $viewer = $this->getEngine()->getConfig('viewer');

    return id(new LegalpadDocumentQuery())
      ->setViewer($viewer)
      ->withIDs($ids)
      ->execute();
  }

}
