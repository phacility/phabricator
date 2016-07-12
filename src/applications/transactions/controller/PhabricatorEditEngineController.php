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
    return $this->loadConfig($need_edit = true);
  }

  protected function loadConfigForView() {
    return $this->loadConfig($need_edit = false);
  }

  private function loadConfig($need_edit) {
    $request = $this->getRequest();
    $viewer = $this->getViewer();

    $engine_key = $request->getURIData('engineKey');
    $this->setEngineKey($engine_key);

    $key = $request->getURIData('key');

    if ($need_edit) {
      $capabilities = array(
        PhabricatorPolicyCapability::CAN_VIEW,
        PhabricatorPolicyCapability::CAN_EDIT,
      );
    } else {
      $capabilities = array(
        PhabricatorPolicyCapability::CAN_VIEW,
      );
    }

    $config = id(new PhabricatorEditEngineConfigurationQuery())
      ->setViewer($viewer)
      ->withEngineKeys(array($engine_key))
      ->withIdentifiers(array($key))
      ->requireCapabilities($capabilities)
      ->executeOne();
    if ($config) {
      $engine = $config->getEngine();
    } else {
      return null;
    }

    if (!$engine->isEngineConfigurable()) {
      return null;
    }

    return $config;
  }


}
