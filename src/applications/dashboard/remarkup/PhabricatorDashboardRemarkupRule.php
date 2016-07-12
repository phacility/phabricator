<?php

final class PhabricatorDashboardRemarkupRule
  extends PhabricatorObjectRemarkupRule {

  const KEY_PARENT_PANEL_PHIDS = 'dashboard.parentPanelPHIDs';

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

    $engine = $this->getEngine();
    $viewer = $engine->getConfig('viewer');

    $parent_key = self::KEY_PARENT_PANEL_PHIDS;
    $parent_phids = $engine->getConfig($parent_key, array());

    return id(new PhabricatorDashboardPanelRenderingEngine())
      ->setViewer($viewer)
      ->setPanel($object)
      ->setParentPanelPHIDs($parent_phids)
      ->renderPanel();

  }
}
