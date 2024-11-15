<?php

final class VacationApplication extends PhabricatorApplication {

  public function getBaseURI() {
    return '/vacation/';
  }

  public function getShortDescription() {
    return pht('Vacation');
  }

  public function getName() {
    return pht('Vacation');
  }

  public function getIcon() {
    return 'fa-glass';
  }
  
  public function isPinnedByDefault(PhabricatorUser $viewer) {
      return true;
  }

  public function getTitleGlyph() {
    return "\xE2\x8F\x9A";
  }

  public function getFlavorText() {
    return pht('A gallery of modern art.');
  }

  public function getApplicationGroup() {
    return self::GROUP_UTILITIES;
  }

  public function isPrototype() {
    return false;
  }

  public function getApplicationOrder() {
    return 0.1;
  }

  public function getRoutes() {
    return array(
      '/vacation/' => array(
        '' => 'VacationRenderController',
        'view/(?P<class>[^/]+)/' => 'VacationRenderController',
      ),
    );
  }

}
