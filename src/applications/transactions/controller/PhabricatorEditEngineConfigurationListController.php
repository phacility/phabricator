<?php

final class PhabricatorEditEngineConfigurationListController
  extends PhabricatorEditEngineController {

  public function shouldAllowPublic() {
    return true;
  }

  public function handleRequest(AphrontRequest $request) {
    $this->setEngineKey($request->getURIData('engineKey'));

    return id(new PhabricatorEditEngineConfigurationSearchEngine())
      ->setController($this)
      ->setEngineKey($this->getEngineKey())
      ->buildResponse();
  }

  protected function buildApplicationCrumbs() {
    $viewer = $this->getViewer();
    $crumbs = parent::buildApplicationCrumbs();

    $target_key = $this->getEngineKey();
    $target_engine = PhabricatorEditEngine::getByKey($viewer, $target_key);

    id(new PhabricatorEditEngineConfigurationEditEngine())
      ->setTargetEngine($target_engine)
      ->setViewer($viewer)
      ->addActionToCrumbs($crumbs);

    return $crumbs;
  }

}
