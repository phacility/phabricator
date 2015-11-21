<?php

abstract class PhabricatorEditEngineController
  extends PhabricatorApplicationTransactionController {

  private $engineKey;

  public function setEngineKey($engine_key) {
    $this->engineKey = $engine_key;
    return $this;
  }

  public function getEngineKey() {
    return $this->engineKey;
  }

  protected function buildApplicationCrumbs() {
    $crumbs = parent::buildApplicationCrumbs();

    $crumbs->addTextCrumb(pht('Edit Engines'), '/transactions/editengine/');

    $engine_key = $this->getEngineKey();
    if ($engine_key !== null) {
      $engine = PhabricatorEditEngine::getByKey(
        $this->getViewer(),
        $engine_key);
      if ($engine) {
        $crumbs->addTextCrumb(
          $engine->getEngineName(),
          "/transactions/editengine/{$engine_key}/");
      }
    }

    return $crumbs;
  }

}
