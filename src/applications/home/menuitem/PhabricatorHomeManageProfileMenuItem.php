<?php

final class PhabricatorHomeManageProfileMenuItem
  extends PhabricatorProfileMenuItem {

  const MENUITEMKEY = 'home.manage.menu';

  public function getMenuItemTypeName() {
    return pht('Manage Home Menu');
  }

  private function getDefaultName() {
    return pht('Manage');
  }

  public function canHideMenuItem(
    PhabricatorProfileMenuItemConfiguration $config) {
    return false;
  }

  public function canMakeDefault(
    PhabricatorProfileMenuItemConfiguration $config) {
    return false;
  }

  public function getDisplayName(
    PhabricatorProfileMenuItemConfiguration $config) {
    $name = $config->getMenuItemProperty('name');

    if (strlen($name)) {
      return $name;
    }

    return $this->getDefaultName();
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

    if ($viewer->isLoggedIn()) {
      $admin = $viewer->getIsAdmin();
      $name = $this->getDisplayName($config);
      $icon = 'fa-pencil';
      $href = '/home/menu/personal/item/configure/';
      if ($admin) {
        $href = '/home/menu/';
      }

      $item = $this->newItem()
        ->setHref($href)
        ->setName($name)
        ->setIcon($icon);
    }

    return array(
      $item,
    );
  }

}
