<?php

final class PhabricatorEditEngineQuery
  extends PhabricatorCursorPagedPolicyAwareQuery {

  private $engineKeys;

  public function withEngineKeys(array $keys) {
    $this->engineKeys = $keys;
    return $this;
  }

  protected function loadPage() {
    $engines = PhabricatorEditEngine::getAllEditEngines();

    if ($this->engineKeys !== null) {
      $engines = array_select_keys($engines, $this->engineKeys);
    }

    return $engines;
  }

  protected function willFilterPage(array $engines) {
    $viewer = $this->getViewer();

    foreach ($engines as $key => $engine) {
      $app_class = $engine->getEngineApplicationClass();
      if ($app_class === null) {
        continue;
      }

      $can_see = PhabricatorApplication::isClassInstalledForViewer(
        $app_class,
        $viewer);
      if (!$can_see) {
        $this->didRejectResult($engine);
        unset($engines[$key]);
        continue;
      }
    }

    return $engines;
  }

  public function getQueryApplicationClass() {
    return 'PhabricatorTransactionsApplication';
  }

}
