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
    return PhabricatorEnv::getDoclink('Phriction User Guide');
  }

  public function getTitleGlyph() {
    return "\xE2\x9A\xA1";
  }

  public function getRemarkupRules() {
    return array(
      new PhrictionRemarkupRule(),
    );
  }

  public function getEventListeners() {
    return array(
      new PhrictionActionMenuEventListener(),
    );
  }

  public function getRoutes() {
    return array(
      // Match "/w/" with slug "/".
      '/w(?P<slug>/)'    => 'PhrictionDocumentController',
      // Match "/w/x/y/z/" with slug "x/y/z/".
      '/w/(?P<slug>.+/)' => 'PhrictionDocumentController',

      '/phriction/' => array(
        '(?:query/(?P<queryKey>[^/]+)/)?' => 'PhrictionListController',

        'history(?P<slug>/)'     => 'PhrictionHistoryController',
        'history/(?P<slug>.+/)'  => 'PhrictionHistoryController',

        'edit/(?:(?P<id>[1-9]\d*)/)?' => 'PhrictionEditController',
        'delete/(?P<id>[1-9]\d*)/'    => 'PhrictionDeleteController',
        'new/'                        => 'PhrictionNewController',
        'move/(?:(?P<id>[1-9]\d*)/)?'      => 'PhrictionMoveController',

        'preview/' => 'PhabricatorMarkupPreviewController',
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
