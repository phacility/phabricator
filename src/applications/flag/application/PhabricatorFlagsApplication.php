<?php

final class PhabricatorFlagsApplication extends PhabricatorApplication {

  public function getName() {
    return pht('Flags');
  }

  public function getShortDescription() {
    return pht('Personal Bookmarks');
  }

  public function getBaseURI() {
    return '/flag/';
  }

  public function getIcon() {
    return 'fa-flag';
  }

  public function getEventListeners() {
    return array(
      new PhabricatorFlagsUIEventListener(),
    );
  }

  public function getTitleGlyph() {
    return "\xE2\x9A\x90";
  }

  public function getApplicationGroup() {
    return self::GROUP_UTILITIES;
  }

  public function getRoutes() {
    return array(
      '/flag/' => array(
        '(?:query/(?P<queryKey>[^/]+)/)?' => 'PhabricatorFlagListController',
        'view/(?P<view>[^/]+)/' => 'PhabricatorFlagListController',
        'edit/(?P<phid>[^/]+)/' => 'PhabricatorFlagEditController',
        'delete/(?P<id>[1-9]\d*)/' => 'PhabricatorFlagDeleteController',
      ),
    );
  }

}
