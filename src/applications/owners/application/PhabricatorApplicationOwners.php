<?php

final class PhabricatorApplicationOwners extends PhabricatorApplication {

  public function getBaseURI() {
    return '/owners/';
  }

  public function getIconName() {
    return 'owners';
  }

  public function getShortDescription() {
    return 'Group Source Code';
  }

  public function getTitleGlyph() {
    return "\xE2\x98\x81";
  }

  public function getHelpURI() {
    return PhabricatorEnv::getDoclink('article/Owners_Tool_User_Guide.html');
  }

  public function getFlavorText() {
    return pht('Adopt today!');
  }

  public function getApplicationGroup() {
    return self::GROUP_ORGANIZATION;
  }

  public function getRoutes() {
    return array(
      '/owners/' => array(
        '' => 'PhabricatorOwnersListController',
        'view/(?P<view>[^/]+)/' => 'PhabricatorOwnersListController',
        'edit/(?P<id>[1-9]\d*)/' => 'PhabricatorOwnersEditController',
        'new/' => 'PhabricatorOwnersEditController',
        'package/(?P<id>[1-9]\d*)/' => 'PhabricatorOwnersDetailController',
        'delete/(?P<id>[1-9]\d*)/' => 'PhabricatorOwnersDeleteController',
      ),
    );
  }

}
