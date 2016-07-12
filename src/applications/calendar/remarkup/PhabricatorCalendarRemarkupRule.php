<?php

final class PhabricatorCalendarRemarkupRule
  extends PhabricatorObjectRemarkupRule {

  protected function getObjectNamePrefix() {
    return 'E';
  }

  protected function loadObjects(array $ids) {
    $viewer = $this->getEngine()->getConfig('viewer');

    return id(new PhabricatorCalendarEventQuery())
      ->setViewer($viewer)
      ->withIDs($ids)
      ->execute();
  }

}
