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

  public function getQueryApplicationClass() {
    return 'PhabricatorTransactionsApplication';
  }

  protected function getResultCursor($object) {
    return null;
  }

}
