<?php

final class ManagementPanelApplication extends PhabricatorApplication {

  public function getBaseURI() {
    return '/managementpanel/';
  }

  public function getShortDescription() {
    return pht('Management panel');
  }

  public function getName() {
    return pht('Management panel');
  }

  public function getIcon() {
    return 'fa-users';
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
    return self::GROUP_ADMIN;
  }

  public function isPrototype() {
    return false;
  }

  public function getApplicationOrder() {
    return 0.1;
  }

  public function getRoutes() {
    return array(
      '/managementpanel/' => array(
        '' => 'ManagementPanelRenderController',
        'view/(?P<class>[^/]+)/' => 'ManagementPanelRenderController',
      ),
    );
  }

}
