<?php

final class PhabricatorPeopleRevisionsProfileMenuItem
  extends PhabricatorProfileMenuItem {

  const MENUITEMKEY = 'people.revisions';

  public function getMenuItemTypeName() {
    return pht('Revisions');
  }

  private function getDefaultName() {
    return pht('Revisions');
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
      ->setURI("/people/revisions/{$id}/")
      ->setName($this->getDisplayName($config))
      ->setIcon('fa-gear');

    return array(
      $item,
    );
  }

}
