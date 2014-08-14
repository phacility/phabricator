<?php

final class PonderRemarkupRule extends PhabricatorObjectRemarkupRule {

  protected function getObjectNamePrefix() {
    return 'Q';
  }

  protected function loadObjects(array $ids) {
    $viewer = $this->getEngine()->getConfig('viewer');
    return id(new PonderQuestionQuery())
      ->setViewer($viewer)
      ->withIDs($ids)
      ->execute();
  }

}
