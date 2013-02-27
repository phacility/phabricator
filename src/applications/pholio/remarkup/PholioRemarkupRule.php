<?php

final class PholioRemarkupRule
  extends PhabricatorRemarkupRuleObject {

  protected function getObjectNamePrefix() {
    return 'M';
  }

  protected function loadObjects(array $ids) {
    $viewer = $this->getEngine()->getConfig('viewer');

    if (!$viewer) {
      return array();
    }

    return id(new PholioMockQuery())
      ->setViewer($viewer)
      ->withIDs($ids)
      ->execute();
  }

}
