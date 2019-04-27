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

  protected function newMenuItemViewList(
    PhabricatorProfileMenuItemConfiguration $config) {

    $user = $config->getProfileObject();
    $uri = urisprintf(
      '/p/%s/',
      $user->getUsername());

    $item = $this->newItemView()
      ->setURI($uri)
      ->setName(pht('Profile'))
      ->setIcon('fa-user');

    return array(
      $item,
    );
  }

}
