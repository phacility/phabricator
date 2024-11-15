<?php

final class PhabricatorRoleManageProfileMenuItem
  extends PhabricatorProfileMenuItem {

  const MENUITEMKEY = 'role.manage';

  public function getMenuItemTypeName() {
    return pht('Manage Role');
  }

  private function getDefaultName() {
    return pht('Manage');
  }

  public function getMenuItemTypeIcon() {
    return 'fa-cog';
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

    if ($name !== null && $name !== '') {
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

  protected function newMenuItemViewList(
    PhabricatorProfileMenuItemConfiguration $config) {

    $role = $config->getProfileObject();

    $id = $role->getID();

    $name = $this->getDisplayName($config);
    $icon = 'fa-gears';
    $uri = "/role/manage/{$id}/";

    $item = $this->newItemView()
      ->setURI($uri)
      ->setName($name)
      ->setIcon($icon);

    return array(
      $item,
    );
  }

}
