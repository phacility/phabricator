<?php

final class HeraldRemarkupRule extends PhabricatorObjectRemarkupRule {

  protected function getObjectNamePrefix() {
    return 'H';
  }

  protected function loadObjects(array $ids) {
    $viewer = $this->getEngine()->getConfig('viewer');
    return id(new HeraldRuleQuery())
      ->setViewer($viewer)
      ->withIDs($ids)
      ->execute();
  }

}
