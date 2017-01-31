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

    if ($viewer->getPHID()) {
      $custom_phid = $viewer->getPHID();
    } else {
      $custom_phid = null;
    }

    $application = 'PhabricatorHomeApplication';
    $home_app = id(new PhabricatorApplicationQuery())
      ->setViewer($viewer)
      ->withClasses(array($application))
      ->withInstalled(true)
      ->executeOne();

    $engine = id(new PhabricatorHomeProfileMenuEngine())
      ->setProfileObject($home_app)
      ->setCustomPHID($custom_phid)
      ->setController($this);

    return $engine->buildResponse();
  }

}
