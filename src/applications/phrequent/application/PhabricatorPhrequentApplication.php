<?php

final class PhabricatorPhrequentApplication extends PhabricatorApplication {

  public function getName() {
    return pht('Phrequent');
  }

  public function getShortDescription() {
    return pht('Track Time Spent');
  }

  public function getBaseURI() {
    return '/phrequent/';
  }

  public function isPrototype() {
    return true;
  }

  public function getIcon() {
    return 'fa-clock-o';
  }

  public function getApplicationGroup() {
    return self::GROUP_UTILITIES;
  }

  public function getApplicationOrder() {
    return 0.110;
  }

  public function getEventListeners() {
    return array(
      new PhrequentUIEventListener(),
    );
  }

  public function getRoutes() {
    return array(
      '/phrequent/' => array(
        '(?:query/(?P<queryKey>[^/]+)/)?' => 'PhrequentListController',
        'track/(?P<verb>[a-z]+)/(?P<phid>[^/]+)/'
          => 'PhrequentTrackController',
      ),
    );
  }

}
