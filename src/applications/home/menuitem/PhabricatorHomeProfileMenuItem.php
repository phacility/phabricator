<?php

final class PhabricatorHomeProfileMenuItem
  extends PhabricatorProfileMenuItem {

  const MENUITEMKEY = 'home.dashboard';

  public function getMenuItemTypeName() {
    return pht('Home');
  }

  private function getDefaultName() {
    return pht('Home');
  }

  public function canHideMenuItem(
    PhabricatorProfileMenuItemConfiguration $config) {
    return false;
  }

  public function canMakeDefault(
    PhabricatorProfileMenuItemConfiguration $config) {
    return true;
  }

  public function getDisplayName(
    PhabricatorProfileMenuItemConfiguration $config) {
    $name = $config->getMenuItemProperty('name');

    if (strlen($name)) {
      return $name;
    }

    return $this->getDefaultName();
  }

  public function newPageContent() {
    $viewer = $this->getViewer();

    return id(new PHUIHomeView())
      ->setViewer($viewer);
  }

  public function buildEditEngineFields(
    PhabricatorProfileMenuItemConfiguration $config) {
    return array(
      id(new PhabricatorTextEditField())
        ->setKey('name')
        ->setLabel(pht('Name'))
        ->setPlaceholder($this->getDefaultName())
        ->setValue($config->getMenuItemProperty('name')),
    );
  }

  protected function newNavigationMenuItems(
    PhabricatorProfileMenuItemConfiguration $config) {
    $viewer = $this->getViewer();

    if (!$viewer->isLoggedIn()) {
      return array();
    }

    $name = $this->getDisplayName($config);
    $icon = 'fa-home';
    $href = $this->getItemViewURI($config);

    $item = $this->newItem()
      ->setHref($href)
      ->setName($name)
      ->setIcon($icon);

    return array(
      $item,
    );
  }

}
