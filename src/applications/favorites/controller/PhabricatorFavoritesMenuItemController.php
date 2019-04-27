<?php

final class PhabricatorFavoritesMenuItemController
  extends PhabricatorFavoritesController {

  public function handleRequest(AphrontRequest $request) {
    $viewer = $this->getViewer();

    $application = 'PhabricatorFavoritesApplication';
    $favorites = id(new PhabricatorApplicationQuery())
      ->setViewer($viewer)
      ->withClasses(array($application))
      ->withInstalled(true)
      ->executeOne();

    $engine = id(new PhabricatorFavoritesProfileMenuEngine())
      ->setProfileObject($favorites)
      ->setCustomPHID($viewer->getPHID())
      ->setController($this);

    return $engine->buildResponse();
  }

}
