<?php

final class PhabricatorApplicationMacro extends PhabricatorApplication {

  public function getBaseURI() {
    return '/macro/';
  }

  public function getShortDescription() {
    return pht('Image Macros and Memes');
  }

  public function getIconName() {
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
        '((?P<filter>all|active|my)/)?' => 'PhabricatorMacroListController',
        'create/' => 'PhabricatorMacroEditController',
        'view/(?P<id>[1-9]\d*)/' => 'PhabricatorMacroViewController',
        'comment/(?P<id>[1-9]\d*)/' => 'PhabricatorMacroCommentController',
        'edit/(?P<id>[1-9]\d*)/' => 'PhabricatorMacroEditController',
        'disable/(?P<id>[1-9]\d*)/' => 'PhabricatorMacroDisableController',
        'meme/' => 'PhabricatorMacroMemeController',
        'meme/create/' => 'PhabricatorMacroMemeDialogController',
      ),
    );
  }

}
