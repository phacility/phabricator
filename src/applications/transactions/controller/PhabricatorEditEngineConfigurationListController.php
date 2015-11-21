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
    $crumbs = parent::buildApplicationCrumbs();

    $engine_key = $this->getEngineKey();
    $edit_uri = "/transactions/editengine/{$engine_key}/edit/";

    $crumbs->addAction(
      id(new PHUIListItemView())
        ->setName(pht('New Form'))
        ->setHref($edit_uri)
        ->setIcon('fa-plus-square'));

    return $crumbs;
  }

}
