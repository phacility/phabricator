<?php

final class PhabricatorApplicationPhriction extends PhabricatorApplication {

  public function getShortDescription() {
    return pht('Wiki');
  }

  public function getBaseURI() {
    return '/w/';
  }

  public function getIconName() {
    return 'phriction';
  }

  public function getHelpURI() {
    return PhabricatorEnv::getDoclink('article/Phriction_User_Guide.html');
  }

  public function isEnabled() {
    return PhabricatorEnv::getEnvConfig('phriction.enabled');
  }

  public function getTitleGlyph() {
    return "\xE2\x9A\xA1";
  }

  public function getRoutes() {
    return array(
      // Match "/w/" with slug "/".
      '/w(?P<slug>/)'    => 'PhrictionDocumentController',
      // Match "/w/x/y/z/" with slug "x/y/z/".
      '/w/(?P<slug>.+/)' => 'PhrictionDocumentController',

      '/phriction/' => array(
        ''                       => 'PhrictionListController',
        'list/(?P<view>[^/]+)/'  => 'PhrictionListController',

        'history(?P<slug>/)'     => 'PhrictionHistoryController',
        'history/(?P<slug>.+/)'  => 'PhrictionHistoryController',

        'edit/(?:(?P<id>[1-9]\d*)/)?' => 'PhrictionEditController',
        'delete/(?P<id>[1-9]\d*)/'    => 'PhrictionDeleteController',
        'new/'                        => 'PhrictionNewController',
        'move/(?:(?P<id>[1-9]\d*)/)?'      => 'PhrictionMoveController',

        'preview/' => 'PhrictionDocumentPreviewController',
        'diff/(?P<id>[1-9]\d*)/' => 'PhrictionDiffController',
      ),
    );
  }

  public function getApplicationGroup() {
    return self::GROUP_COMMUNICATION;
  }

  public function getApplicationOrder() {
    return 0.140;
  }

}

