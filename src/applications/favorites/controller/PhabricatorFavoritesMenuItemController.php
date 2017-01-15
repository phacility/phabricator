<?php

final class PhabricatorFavoritesMenuItemController
  extends PhabricatorFavoritesController {

  public function handleRequest(AphrontRequest $request) {
    $viewer = $this->getViewer();
    $type = $request->getURIData('type');
    $custom_phid = null;
    if ($type == 'personal') {
      $custom_phid = $viewer->getPHID();
    }

    $application = 'PhabricatorFavoritesApplication';
    $favorites = id(new PhabricatorApplicationQuery())
      ->setViewer($viewer)
      ->withClasses(array($application))
      ->withInstalled(true)
      ->executeOne();

    $engine = id(new PhabricatorFavoritesProfileMenuEngine())
      ->setProfileObject($favorites)
      ->setCustomPHID($custom_phid)
      ->setController($this);

    return $engine->buildResponse();
  }

}
