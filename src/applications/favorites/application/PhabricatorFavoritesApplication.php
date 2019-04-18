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
    return 'fa-bookmark';
  }

  public function getRoutes() {
    return array(
      '/favorites/' => array(
        '' => 'PhabricatorFavoritesMenuItemController',
        'menu/' => $this->getProfileMenuRouting(
          'PhabricatorFavoritesMenuItemController'),
      ),
    );
  }

  public function isLaunchable() {
    return false;
  }

}
