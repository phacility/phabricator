<?php

final class PhabricatorEditEngineConfigurationEditController
  extends PhabricatorEditEngineController {

  public function handleRequest(AphrontRequest $request) {
    $viewer = $this->getViewer();

    $target_engine_key = $request->getURIData('engineKey');

    $target_engine = PhabricatorEditEngine::getByKey(
      $viewer,
      $target_engine_key);
    if (!$target_engine) {
      return new Aphront404Response();
    }

    $this->setEngineKey($target_engine->getEngineKey());

    return id(new PhabricatorEditEngineConfigurationEditEngine())
      ->setTargetEngine($target_engine)
      ->setController($this)
      ->buildResponse();
  }

}
