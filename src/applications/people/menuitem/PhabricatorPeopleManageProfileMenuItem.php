<?php

final class PhabricatorPeopleManageProfileMenuItem
  extends PhabricatorProfileMenuItem {

  const MENUITEMKEY = 'people.manage';

  public function getMenuItemTypeName() {
    return pht('Manage User');
  }

  private function getDefaultName() {
    return pht('Manage');
  }

  public function getDisplayName(
    PhabricatorProfileMenuItemConfiguration $config) {
    $default = $this->getDefaultName();
    return $this->getNameFromConfig($config, $default);
  }

  public function canHideMenuItem(
    PhabricatorProfileMenuItemConfiguration $config) {
    return false;
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

    $user = $config->getProfileObject();
    $id = $user->getID();

    $item = $this->newItemView()
      ->setURI("/people/manage/{$id}/")
      ->setName($this->getDisplayName($config))
      ->setIcon('fa-gears');

    return array(
      $item,
    );
  }

}
