<?php

final class PhabricatorRoleSubrolesProfileMenuItem
  extends PhabricatorProfileMenuItem {

  const MENUITEMKEY = 'role.subroles';

  public function getMenuItemTypeName() {
    return pht('Role Subroles');
  }

  private function getDefaultName() {
    return pht('Subroles');
  }

  public function getMenuItemTypeIcon() {
    return 'fa-sitemap';
  }

  public function shouldEnableForObject($object) {
    if ($object->isMilestone()) {
      return false;
    }

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
    $icon = 'fa-sitemap';
    $uri = "/role/subroles/{$id}/";

    $item = $this->newItemView()
      ->setURI($uri)
      ->setName($name)
      ->setIcon($icon);

    return array(
      $item,
    );
  }

}
