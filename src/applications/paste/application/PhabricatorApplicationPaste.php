<?php

final class PhabricatorApplicationPaste extends PhabricatorApplication {

  public function getBaseURI() {
    return '/paste/';
  }

  public function getAutospriteName() {
    return 'paste';
  }

  public function getTitleGlyph() {
    return "\xE2\x9C\x8E";
  }

  public function getApplicationGroup() {
    return self::GROUP_UTILITIES;
  }

  public function getRoutes() {
    return array(
      '/P(?P<id>[1-9]\d*)' => 'PhabricatorPasteViewController',
      '/paste/' => array(
        '' => 'PhabricatorPasteEditController',
        'edit/(?P<id>[1-9]\d*)/' => 'PhabricatorPasteEditController',
        'filter/(?P<filter>\w+)/' => 'PhabricatorPasteListController',
      ),
    );
  }

}
