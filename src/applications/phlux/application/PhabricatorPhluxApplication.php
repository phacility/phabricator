<?php

final class PhabricatorPhluxApplication extends PhabricatorApplication {

  public function getName() {
    return pht('Phlux');
  }

  public function getBaseURI() {
    return '/phlux/';
  }

  public function getShortDescription() {
    return pht('Key/Value Configuration Store');
  }

  public function getFontIcon() {
    return 'fa-copy';
  }

  public function getTitleGlyph() {
    return "\xE2\x98\xBD";
  }

  public function getApplicationGroup() {
    return self::GROUP_UTILITIES;
  }

  public function isPrototype() {
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
