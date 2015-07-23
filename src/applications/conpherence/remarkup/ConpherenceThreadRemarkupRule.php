<?php

final class ConpherenceThreadRemarkupRule
  extends PhabricatorObjectRemarkupRule {

  protected function getObjectNamePrefix() {
    return 'Z';
  }

  protected function loadObjects(array $ids) {
    $viewer = $this->getEngine()->getConfig('viewer');
    $threads = id(new ConpherenceThreadQuery())
      ->setViewer($viewer)
      ->withIDs($ids)
      ->execute();
    return mpull($threads, null, 'getID');
  }

}
