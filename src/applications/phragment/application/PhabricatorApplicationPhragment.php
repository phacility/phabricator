<?php

final class PhabricatorApplicationPhragment extends PhabricatorApplication {

  public function getBaseURI() {
    return '/phragment/';
  }

  public function getShortDescription() {
    return pht('Versioned Artifact Storage');
  }

  public function getIconName() {
    return 'phragment';
  }

  public function getTitleGlyph() {
    return "\xE2\x26\xB6";
  }

  public function getApplicationGroup() {
    return self::GROUP_UTILITIES;
  }

  public function isBeta() {
    return true;
  }

  public function canUninstall() {
    return true;
  }

  public function getRoutes() {
    return array(
      '/phragment/' => array(
        '' => 'PhragmentBrowseController',
        'browse/(?P<dblob>.*)' => 'PhragmentBrowseController',
        'create/(?P<dblob>.*)' => 'PhragmentCreateController',
      ),
    );
  }

}

