<?php

final class PhabricatorMacroApplication extends PhabricatorApplication {

  public function getBaseURI() {
    return '/macro/';
  }

  public function getName() {
    return pht('Macro');
  }

  public function getShortDescription() {
    return pht('Image Macros and Memes');
  }

  public function getIcon() {
    return 'fa-file-image-o';
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
        '(query/(?P<key>[^/]+)/)?' => 'PhabricatorMacroListController',
        'create/' => 'PhabricatorMacroEditController',
        'view/(?P<id>[1-9]\d*)/' => 'PhabricatorMacroViewController',
        $this->getEditRoutePattern('edit/')
          => 'PhabricatorMacroEditController',
        'audio/(?P<id>[1-9]\d*)/' => 'PhabricatorMacroAudioController',
        'disable/(?P<id>[1-9]\d*)/' => 'PhabricatorMacroDisableController',
        'meme/' => 'PhabricatorMacroMemeController',
        'meme/create/' => 'PhabricatorMacroMemeDialogController',
      ),
    );
  }

  protected function getCustomCapabilities() {
    return array(
      PhabricatorMacroManageCapability::CAPABILITY => array(
        'caption' => pht('Allows creating and editing macros.'),
      ),
    );
  }

  public function getMailCommandObjects() {
    return array(
      'macro' => array(
        'name' => pht('Email Commands: Macros'),
        'header' => pht('Interacting with Macros'),
        'object' => new PhabricatorFileImageMacro(),
        'summary' => pht(
          'This page documents the commands you can use to interact with '.
          'image macros.'),
      ),
    );
  }

}
