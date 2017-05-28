<?php

final class PhabricatorPeopleBadgesProfileMenuItem
  extends PhabricatorProfileMenuItem {

  const MENUITEMKEY = 'people.badges';

  public function getMenuItemTypeName() {
    return pht('Badges');
  }

  private function getDefaultName() {
    return pht('Badges');
  }

  public function canHideMenuItem(
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

    $user = $config->getProfileObject();
    $id = $user->getID();

    $item = $this->newItem()
      ->setHref("/people/badges/{$id}/")
      ->setName($this->getDisplayName($config))
      ->setIcon('fa-trophy');

    return array(
      $item,
    );
  }

}
