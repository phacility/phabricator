<?php

final class PhabricatorApplicationSearch extends PhabricatorApplication {

  public function getBaseURI() {
    return '/search/';
  }

  public function getName() {
    return pht('Search');
  }

  public function getShortDescription() {
    return pht('Search & Find');
  }

  public function getFlavorText() {
    return pht('Find stuff in big piles.');
  }

  public function shouldAppearInLaunchView() {
    return false;
  }

  public function getRoutes() {
    return array(
      '/search/' => array(
        '' => 'PhabricatorSearchController',
        '(?P<key>[^/]+)/' => 'PhabricatorSearchController',
        'attach/(?P<phid>[^/]+)/(?P<type>\w+)/(?:(?P<action>\w+)/)?'
          => 'PhabricatorSearchAttachController',
        'select/(?P<type>\w+)/'
          => 'PhabricatorSearchSelectController',
        'index/(?P<phid>[^/]+)/' => 'PhabricatorSearchIndexController',
        'hovercard/(?P<mode>retrieve|test)/' =>
          'PhabricatorSearchHovercardController',
      ),
    );
  }

}
