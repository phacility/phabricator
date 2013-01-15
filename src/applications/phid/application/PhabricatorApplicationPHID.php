<?php

final class PhabricatorApplicationPHID extends PhabricatorApplication {

  public function getName() {
    return 'PHIDs';
  }

  public function getBaseURI() {
    return '/phid/';
  }

  public function getIconName() {
    return 'phid';
  }

  public function getShortDescription() {
    return 'Lookup PHIDs';
  }

  public function getTitleGlyph() {
    return "#";
  }

  public function getApplicationGroup() {
    return self::GROUP_DEVELOPER;
  }

  public function getRoutes() {
    return array(
      '/phid/' => array(
        '' => 'PhabricatorPHIDLookupController',
      ),
    );
  }

}
