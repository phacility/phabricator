<?php

final class PhabricatorHomeMenuItemController
  extends PhabricatorHomeController {

  public function handleRequest(AphrontRequest $request) {
    $viewer = $this->getViewer();
    $type = $request->getURIData('type');
    $custom_phid = null;
    if ($type == 'personal') {
      $custom_phid = $viewer->getPHID();
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
