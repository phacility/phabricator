<?php

final class PhabricatorPhurlRemarkupRule
  extends PhabricatorObjectRemarkupRule {

  protected function getObjectNamePrefix() {
    return 'U';
  }

  protected function loadObjects(array $ids) {
    $viewer = $this->getEngine()->getConfig('viewer');

    return id(new PhabricatorPhurlURLQuery())
      ->setViewer($viewer)
      ->withIDs($ids)
      ->execute();
  }

}
