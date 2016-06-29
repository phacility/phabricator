<?php

final class PhabricatorEditEngineCreateQuickActions
  extends PhabricatorQuickActions {

  const QUICKACTIONSKEY = 'editengine.create';

  public function getQuickMenuItems() {
    $viewer = $this->getViewer();

    $engines = PhabricatorEditEngine::getAllEditEngines();

    foreach ($engines as $key => $engine) {
      if (!$engine->hasQuickCreateActions()) {
        unset($engines[$key]);
      }
    }

    if (!$engines) {
      return array();
    }

    $engine_keys = array_keys($engines);

    $configs = id(new PhabricatorEditEngineConfigurationQuery())
      ->setViewer($viewer)
      ->withEngineKeys($engine_keys)
      ->withIsDefault(true)
      ->withIsDisabled(false)
      ->execute();
    $configs = msort($configs, 'getCreateSortKey');
    $configs = mgroup($configs, 'getEngineKey');

    $items = array();
    foreach ($engines as $key => $engine) {
      $engine_configs = idx($configs, $key, array());
      $engine_items = $engine->newQuickCreateActions($engine_configs);
      foreach ($engine_items as $engine_item) {
        $items[] = $engine_item;
      }
    }

    return $items;
  }

}
