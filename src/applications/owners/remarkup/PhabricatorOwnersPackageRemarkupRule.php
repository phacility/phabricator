<?php

final class PhabricatorOwnersPackageRemarkupRule
  extends PhabricatorObjectRemarkupRule {

  protected function getObjectNamePrefix() {
    return 'O';
  }

  protected function loadObjects(array $ids) {
    $viewer = $this->getEngine()->getConfig('viewer');

    return id(new PhabricatorOwnersPackageQuery())
      ->setViewer($viewer)
      ->withIDs($ids)
      ->execute();
  }

}
