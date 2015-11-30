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

  protected function loadConfigForEdit() {
    $request = $this->getRequest();
    $viewer = $this->getViewer();

    $engine_key = $request->getURIData('engineKey');
    $this->setEngineKey($engine_key);

    $key = $request->getURIData('key');

    $config = id(new PhabricatorEditEngineConfigurationQuery())
      ->setViewer($viewer)
      ->withEngineKeys(array($engine_key))
      ->withIdentifiers(array($key))
      ->requireCapabilities(
        array(
          PhabricatorPolicyCapability::CAN_VIEW,
          PhabricatorPolicyCapability::CAN_EDIT,
        ))
      ->executeOne();

    if ($config) {
      $engine = $config->getEngine();

      // TODO: When we're editing the meta-engine, we need to set the engine
      // itself as its own target. This is hacky and it would be nice to find
      // a cleaner approach later.
      if ($engine instanceof PhabricatorEditEngineConfigurationEditEngine) {
        $engine->setTargetEngine($engine);
      }
    }

    return $config;
  }


}
