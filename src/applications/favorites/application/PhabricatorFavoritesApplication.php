<?php

final class PhabricatorFavoritesApplication extends PhabricatorApplication {

  public function getBaseURI() {
    return '/favorites/';
  }

  public function getName() {
    return pht('Favorites');
  }

  public function getShortDescription() {
    return pht('Favorite Items');
  }

  public function getIcon() {
    return 'fa-star';
  }

  public function getRoutes() {
    return array(
      '/favorites/' => array(
        '' => 'PhabricatorFavoritesMainController',
        '(?P<type>global|personal)/item/' => $this->getProfileMenuRouting(
          'PhabricatorFavoritesMenuItemController'),
      ),
    );
  }

  public function isLaunchable() {
    return false;
  }

  public function buildMainMenuExtraNodes(
    PhabricatorUser $viewer,
    PhabricatorController $controller = null) {

    $dropdown = $this->renderFavoritesDropdown($viewer);
    if (!$dropdown) {
      return null;
    }

    return id(new PHUIButtonView())
      ->setTag('a')
      ->setHref('#')
      ->setIcon('fa-star')
      ->addClass('phabricator-core-user-menu')
      ->setNoCSS(true)
      ->setDropdown(true)
      ->setDropdownMenu($dropdown);
  }

  private function renderFavoritesDropdown(PhabricatorUser $viewer) {
    $application = __CLASS__;

    $applications = id(new PhabricatorApplicationQuery())
      ->setViewer($viewer)
      ->withClasses(array($application))
      ->withInstalled(true)
      ->execute();
    $favorites = head($applications);
    if (!$favorites) {
      return null;
    }

    $menu_engine = id(new PhabricatorFavoritesProfileMenuEngine())
      ->setViewer($viewer)
      ->setProfileObject($favorites);

    if ($viewer->getPHID()) {
      $menu_engine
        ->setCustomPHID($viewer->getPHID())
        ->setMenuType(PhabricatorProfileMenuEngine::MENU_COMBINED);
    } else {
      $menu_engine
        ->setMenuType(PhabricatorProfileMenuEngine::MENU_GLOBAL);
    }

    $filter_view = $menu_engine->buildNavigation();

    $menu_view = $filter_view->getMenu();
    $item_views = $menu_view->getItems();

    $view = id(new PhabricatorActionListView())
      ->setViewer($viewer);
    foreach ($item_views as $item) {
      $type = null;
      if (!strlen($item->getName())) {
        $type = PhabricatorActionView::TYPE_DIVIDER;
      }
      $action = id(new PhabricatorActionView())
        ->setName($item->getName())
        ->setHref($item->getHref())
        ->setType($type);
      $view->addAction($action);
    }

    // Build out edit interface
    if ($viewer->isLoggedIn()) {
      $view->addAction(
        id(new PhabricatorActionView())
          ->setType(PhabricatorActionView::TYPE_DIVIDER));
      $view->addAction(
        id(new PhabricatorActionView())
          ->setName(pht('Edit Favorites'))
          ->setHref('/favorites/'));
    }

    return $view;
  }

}
