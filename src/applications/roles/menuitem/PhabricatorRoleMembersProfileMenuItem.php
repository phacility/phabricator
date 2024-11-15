<?php

final class PhabricatorRoleMembersProfileMenuItem
  extends PhabricatorProfileMenuItem {

  const MENUITEMKEY = 'role.members';

  public function getMenuItemTypeName() {
    return pht('Role Members');
  }

  private function getDefaultName() {
    return pht('Members');
  }

  public function getMenuItemTypeIcon() {
    return 'fa-users';
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
    $icon = 'fa-group';
    $uri = "/role/members/{$id}/";

    $item = $this->newItemView()
      ->setURI($uri)
      ->setName($name)
      ->setIcon($icon);

    return array(
      $item,
    );
  }

}
