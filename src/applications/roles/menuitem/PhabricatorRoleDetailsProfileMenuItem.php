<?php

final class PhabricatorRoleDetailsProfileMenuItem
  extends PhabricatorProfileMenuItem {

  const MENUITEMKEY = 'role.details';

  public function getMenuItemTypeName() {
    return pht('Role Details');
  }

  private function getDefaultName() {
    return pht('Role Details');
  }

  public function getMenuItemTypeIcon() {
    return 'fa-file-text-o';
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
    $name = $role->getName();
    $icon = $role->getDisplayIconIcon();

    $uri = "/role/profile/{$id}/";

    $item = $this->newItemView()
      ->setURI($uri)
      ->setName($name)
      ->setIcon($icon);

    return array(
      $item,
    );
  }

}
