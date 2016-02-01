<?php

final class PhabricatorSearchApplication extends PhabricatorApplication {

  public function getBaseURI() {
    return '/search/';
  }

  public function getName() {
    return pht('Search');
  }

  public function getShortDescription() {
    return pht('Full-Text Search');
  }

  public function getFlavorText() {
    return pht('Find stuff in big piles.');
  }

  public function getIcon() {
    return 'fa-search';
  }

  public function isLaunchable() {
    return false;
  }

  public function getRoutes() {
    return array(
      '/search/' => array(
        '(?:query/(?P<queryKey>[^/]+)/)?' => 'PhabricatorSearchController',
        'attach/(?P<phid>[^/]+)/(?P<type>\w+)/(?:(?P<action>\w+)/)?'
          => 'PhabricatorSearchAttachController',
        'select/(?P<type>\w+)/(?:(?P<action>\w+)/)?'
          => 'PhabricatorSearchSelectController',
        'index/(?P<phid>[^/]+)/' => 'PhabricatorSearchIndexController',
        'hovercard/'
          => 'PhabricatorSearchHovercardController',
        'edit/(?P<queryKey>[^/]+)/' => 'PhabricatorSearchEditController',
        'delete/(?P<queryKey>[^/]+)/(?P<engine>[^/]+)/'
          => 'PhabricatorSearchDeleteController',
        'order/(?P<engine>[^/]+)/' => 'PhabricatorSearchOrderController',
      ),
    );
  }

}
