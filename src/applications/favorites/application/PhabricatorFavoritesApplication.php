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
        'menu/' => $this->getProfileMenuRouting(
          'PhabricatorFavoritesMenuItemController'),
      ),
    );
  }

  public function isLaunchable() {
    return false;
  }

}
