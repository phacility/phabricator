<?php

final class PhabricatorPhurlApplication extends PhabricatorApplication {

  public function getName() {
    return pht('Phurl');
  }

  public function getShortDescription() {
    return pht('URL Shortener');
  }

  public function getFlavorText() {
    return pht('Shorten your favorite URL.');
  }

  public function getBaseURI() {
    return '/phurl/';
  }

  public function getFontIcon() {
    return 'fa-compress';
  }

  public function isPrototype() {
    return true;
  }

  public function getRoutes() {
    return array(
      '/U(?P<id>[1-9]\d*)' => 'PhabricatorPhurlURLViewController',
      '/phurl/' => array(
        '(?:query/(?P<queryKey>[^/]+)/)?'
          => 'PhabricatorPhurlURLListController',
        'url/' => array(
          'create/'
            => 'PhabricatorPhurlURLEditController',
          'edit/(?P<id>[1-9]\d*)/'
            => 'PhabricatorPhurlURLEditController',
        ),
      ),
    );
  }

}
