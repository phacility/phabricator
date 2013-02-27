<?php

/**
 * @group markup
 */
final class PhabricatorPasteRemarkupRule
  extends PhabricatorRemarkupRuleObject {

  protected function getObjectNamePrefix() {
    return 'P';
  }

  protected function loadObjects(array $ids) {
    $viewer = $this->getEngine()->getConfig('viewer');

    if (!$viewer) {
      return array();
    }

    return id(new PhabricatorPasteQuery())
      ->setViewer($viewer)
      ->withIDs($ids)
      ->execute();

  }

}
