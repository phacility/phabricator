<?php

final class PhabricatorRoleWorkboardProfileMenuItem
  extends PhabricatorProfileMenuItem {

  const MENUITEMKEY = 'role.workboard';

  public function getMenuItemTypeName() {
    return pht('Role Workboard');
  }

  private function getDefaultName() {
    return pht('Workboard');
  }

  public function getMenuItemTypeIcon() {
    return 'fa-columns';
  }

  public function canMakeDefault(
    PhabricatorProfileMenuItemConfiguration $config) {
    return true;
  }

  public function shouldEnableForObject($object) {
    $viewer = $this->getViewer();

    // Workboards are only available if Maniphest is installed.
    $class = 'PhabricatorManiphestApplication';
    if (!PhabricatorApplication::isClassInstalledForViewer($class, $viewer)) {
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
    $uri = $role->getWorkboardURI();
    $name = $this->getDisplayName($config);

    $item = $this->newItemView()
      ->setURI($uri)
      ->setName($name)
      ->setIcon('fa-columns');

    return array(
      $item,
    );
  }

}
