<?php

final class PhabricatorPeopleDetailsProfileMenuItem
  extends PhabricatorProfileMenuItem {

  const MENUITEMKEY = 'people.details';

  public function getMenuItemTypeName() {
    return pht('User Details');
  }

  private function getDefaultName() {
    return pht('User Details');
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
        ->setValue($config->getMenuProperty('name')),
    );
  }

  protected function newNavigationMenuItems(
    PhabricatorProfileMenuItemConfiguration $config) {

    $user = $config->getProfileObject();

    $picture = $user->getProfileImageURI();
    $name = $user->getUsername();
    $href = urisprintf(
      '/p/%s/',
      $user->getUsername());

    $item = $this->newItem()
      ->setHref($href)
      ->setName($name)
      ->setProfileImage($picture);

    return array(
      $item,
    );
  }

}
