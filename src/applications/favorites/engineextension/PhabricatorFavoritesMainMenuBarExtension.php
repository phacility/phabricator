<?php

final class PhabricatorFavoritesMainMenuBarExtension
  extends PhabricatorMainMenuBarExtension {

  const MAINMENUBARKEY = 'favorites';

  public function isExtensionEnabledForViewer(PhabricatorUser $viewer) {
    return PhabricatorApplication::isClassInstalledForViewer(
      'PhabricatorFavoritesApplication',
      $viewer);
  }

  public function getExtensionOrder() {
    return 1100;
  }

  public function buildMainMenus() {
    $viewer = $this->getViewer();

    $dropdown = $this->newDropdown($viewer);
    if (!$dropdown) {
      return array();
    }

    $favorites_menu = id(new PHUIButtonView())
      ->setTag('a')
      ->setHref('#')
      ->setIcon('fa-bookmark')
      ->addClass('phabricator-core-user-menu')
      ->setNoCSS(true)
      ->setDropdown(true)
      ->setDropdownMenu($dropdown)
      ->setAuralLabel(pht('Favorites Menu'));

    return array(
      $favorites_menu,
    );
  }

  private function newDropdown(PhabricatorUser $viewer) {
    $applications = id(new PhabricatorApplicationQuery())
      ->setViewer($viewer)
      ->withClasses(array('PhabricatorFavoritesApplication'))
      ->withInstalled(true)
      ->execute();
    $favorites = head($applications);
    if (!$favorites) {
      return null;
    }

    $menu_engine = id(new PhabricatorFavoritesProfileMenuEngine())
      ->setViewer($viewer)
      ->setProfileObject($favorites)
      ->setCustomPHID($viewer->getPHID());

    $controller = $this->getController();
    if ($controller) {
      $menu_engine->setController($controller);
    }

    $filter_view = $menu_engine->newProfileMenuItemViewList()
      ->newNavigationView();

    $menu_view = $filter_view->getMenu();
    $item_views = $menu_view->getItems();

    $view = id(new PhabricatorActionListView())
      ->setViewer($viewer);
    foreach ($item_views as $item) {
      $action = id(new PhabricatorActionView())
        ->setName($item->getName())
        ->setHref($item->getHref())
        ->setIcon($item->getIcon())
        ->setType($item->getType());
      $view->addAction($action);
    }

    return $view;
  }

}
