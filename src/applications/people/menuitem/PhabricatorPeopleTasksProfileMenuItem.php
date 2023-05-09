<?php

final class PhabricatorPeopleTasksProfileMenuItem
  extends PhabricatorProfileMenuItem {

  const MENUITEMKEY = 'people.tasks';

  public function getMenuItemTypeName() {
    return pht('Tasks');
  }

  private function getDefaultName() {
    return pht('Tasks');
  }

  public function getDisplayName(
    PhabricatorProfileMenuItemConfiguration $config) {
    $default = $this->getDefaultName();
    return $this->getNameFromConfig($config, $default);
  }

  public function canHideMenuItem(
    PhabricatorProfileMenuItemConfiguration $config) {
    return true;
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
      ->setURI("/people/tasks/{$id}/")
      ->setName($this->getDisplayName($config))
      ->setIcon('fa-anchor');

    return array(
      $item,
    );
  }

}
