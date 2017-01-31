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

    $application = 'PhabricatorHomeApplication';
    $home_app = id(new PhabricatorApplicationQuery())
      ->setViewer($viewer)
      ->withClasses(array($application))
      ->withInstalled(true)
      ->executeOne();

    $engine = id(new PhabricatorHomeProfileMenuEngine())
      ->setProfileObject($home_app)
      ->setCustomPHID($viewer->getPHID())
      ->setController($this);

    return $engine->buildResponse();
  }

}
