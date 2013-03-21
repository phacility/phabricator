<?php

final class PhabricatorApplicationPhlux extends PhabricatorApplication {

  public function getBaseURI() {
    return '/phlux/';
  }

  public function getShortDescription() {
    return pht('Configuration Store');
  }

  public function getIconName() {
    return 'phlux';
  }

  public function getTitleGlyph() {
    return "\xE2\x98\xBD";
  }

  public function getApplicationGroup() {
    return self::GROUP_UTILITIES;
  }

  public function isBeta() {
    return true;
  }

  public function getRoutes() {
    return array(
      '/phlux/' => array(
        '' => 'PhluxListController',
        'view/(?P<key>[^/]+)/' => 'PhluxViewController',
        'edit/(?:(?P<key>[^/]+)/)?' => 'PhluxEditController',
      ),
    );
  }

}
