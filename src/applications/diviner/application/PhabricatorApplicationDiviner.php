<?php

final class PhabricatorApplicationDiviner extends PhabricatorApplication {

  public function getBaseURI() {
    return '/diviner/';
  }

  public function getIconName() {
    return 'diviner';
  }

  public function getShortDescription() {
    return 'Documentation';
  }

  public function getTitleGlyph() {
    return "\xE2\x97\x89";
  }

  public function getRoutes() {
    return array(
      '/diviner/' => 'DivinerListController',
    );
  }

  public function getApplicationGroup() {
    return self::GROUP_COMMUNICATION;
  }

  public function buildMainMenuItems(
    PhabricatorUser $user,
    PhabricatorController $controller = null) {

    $items = array();

    $application = null;
    if ($controller) {
      $application = $controller->getCurrentApplication();
    }

    if ($application && $application->getHelpURI()) {
      $item = new PhabricatorMenuItemView();
      $item->setName(pht('%s Help', $application->getName()));
      $item->setIcon('help');
      $item->setHref($application->getHelpURI());
      $items[] = $item;
    }

    return $items;
  }


}

