<?php

final class PhabricatorHomeMenuItemController
  extends PhabricatorHomeController {

  public function shouldAllowPublic() {
    return true;
  }

  public function isGlobalDragAndDropUploadEnabled() {
    return true;
  }

  public function handleRequest(AphrontRequest $request) {
    $viewer = $this->getViewer();

    // Test if we should show mobile users the menu or the page content:
    // if you visit "/", you just get the menu. If you visit "/home/", you
    // get the content.
    $is_content = $request->getURIData('content');

    $application = 'PhabricatorHomeApplication';
    $home_app = id(new PhabricatorApplicationQuery())
      ->setViewer($viewer)
      ->withClasses(array($application))
      ->withInstalled(true)
      ->executeOne();

    $engine = id(new PhabricatorHomeProfileMenuEngine())
      ->setProfileObject($home_app)
      ->setCustomPHID($viewer->getPHID())
      ->setController($this)
      ->setShowContentCrumbs(false);

    if (!$is_content) {
      $engine->addContentPageClass('phabricator-home');
    }

    return $engine->buildResponse();
  }

}
