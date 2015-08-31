<?php

final class PhabricatorOwnersApplication extends PhabricatorApplication {

  public function getName() {
    return pht('Owners');
  }

  public function getBaseURI() {
    return '/owners/';
  }

  public function getFontIcon() {
    return 'fa-gift';
  }

  public function getShortDescription() {
    return pht('Own Source Code');
  }

  public function getTitleGlyph() {
    return "\xE2\x98\x81";
  }

  public function getHelpDocumentationArticles(PhabricatorUser $viewer) {
    return array(
      array(
        'name' => pht('Owners User Guide'),
        'href' => PhabricatorEnv::getDoclink('Owners User Guide'),
      ),
    );
  }

  public function getFlavorText() {
    return pht('Adopt today!');
  }

  public function getApplicationGroup() {
    return self::GROUP_UTILITIES;
  }

  public function getRoutes() {
    return array(
      '/owners/' => array(
        '(?:query/(?P<queryKey>[^/]+)/)?' => 'PhabricatorOwnersListController',
        'edit/(?P<id>[1-9]\d*)/' => 'PhabricatorOwnersEditController',
        'new/' => 'PhabricatorOwnersEditController',
        'package/(?P<id>[1-9]\d*)/' => 'PhabricatorOwnersDetailController',
        'paths/(?P<id>[1-9]\d*)/' => 'PhabricatorOwnersPathsController',
      ),
    );
  }

}
