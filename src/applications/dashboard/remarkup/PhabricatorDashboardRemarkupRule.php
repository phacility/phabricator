<?php

final class PhabricatorDashboardRemarkupRule
  extends PhabricatorObjectRemarkupRule {

  protected function getObjectNamePrefix() {
    return 'W';
  }

  protected function loadObjects(array $ids) {
    $viewer = $this->getEngine()->getConfig('viewer');

    return id(new PhabricatorDashboardPanelQuery())
      ->setViewer($viewer)
      ->withIDs($ids)
      ->execute();
  }

  protected function renderObjectEmbed(
    $object,
    PhabricatorObjectHandle $handle,
    $options) {

    $viewer = $this->getEngine()->getConfig('viewer');

    return id(new PhabricatorDashboardPanelRenderingEngine())
      ->setViewer($viewer)
      ->setPanel($object)
      ->setParentPanelPHIDs(array())
      ->renderPanel();

  }
}
