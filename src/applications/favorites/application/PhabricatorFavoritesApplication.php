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
    return 'fa-star-o';
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

  public function getApplicationOrder() {
    return 9;
  }

}
