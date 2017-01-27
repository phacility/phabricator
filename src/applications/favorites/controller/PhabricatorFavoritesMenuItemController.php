<?php

final class PhabricatorFavoritesMenuItemController
  extends PhabricatorFavoritesController {

  public function handleRequest(AphrontRequest $request) {
    $viewer = $this->getViewer();
    $type = $request->getURIData('type');
    $custom_phid = null;
    $menu = PhabricatorProfileMenuEngine::MENU_GLOBAL;
    if ($type == 'personal') {
      $custom_phid = $viewer->getPHID();
      $menu = PhabricatorProfileMenuEngine::MENU_PERSONAL;
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
      ->setController($this)
      ->setMenuType($menu)
      ->setShowNavigation(false);

    return $engine->buildResponse();
  }

}
