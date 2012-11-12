<?php

final class PhabricatorApplicationMacro extends PhabricatorApplication {

  public function getBaseURI() {
    return '/macro/';
  }

  public function getShortDescription() {
    return 'Image Macros and Memes';
  }

  public function getAutospriteName() {
    return 'macro';
  }

  public function getTitleGlyph() {
    return "\xE2\x9A\x98";
  }

  public function getApplicationGroup() {
    return self::GROUP_UTILITIES;
  }

  public function getRoutes() {
    return array(
      '/macro/' => array(
        '' => 'PhabricatorMacroListController',
        'edit/(?:(?P<id>[1-9]\d*)/)?' => 'PhabricatorMacroEditController',
        'delete/(?P<id>[1-9]\d*)/' => 'PhabricatorMacroDeleteController',
      ),
    );
  }

}
